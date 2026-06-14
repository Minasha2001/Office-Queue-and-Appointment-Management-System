<?php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header("Location: login.php");
    exit();
}

$page_title = "Book Appointment";
$success_msg = "";
$error_msg = "";

// Get pre-selected service if any
$selected_service_id = intval($_GET['service_id'] ?? 0);

// Fetch all active services for the dropdown
$services_query = "SELECT id, name, prefix, description, icon FROM services WHERE status = 'active' ORDER BY id ASC";
$services_res = mysqli_query($conn, $services_query);
$services = [];
while ($row = mysqli_fetch_assoc($services_res)) {
    $services[$row['id']] = $row;
}

// Re-select first service if not specified
if ($selected_service_id === 0 && !empty($services)) {
    $selected_service_id = array_key_first($services);
}

// Document checklist helper
$required_docs = [
    'BC' => ['Hospital Birth Report', "Parents' NIC Copies", "Marriage Certificate (if applicable)", "Completed Application Form"],
    'DC' => ['Medical Declaration of Death', "Deceased Person's NIC", "Applicant's NIC Copy", "Declaration Form"],
    'RC' => ['Grama Niladhari Verification Report', 'Address Proof (Utility Bill/Rent Agreement)', 'NIC Copy', 'Application Form'],
    'NIC' => ['Original Birth Certificate', '4 passport-sized color photographs', 'Grama Niladhari Certificate', 'Completed Application Form'],
    'MR' => ['Original Birth Certificates of both parties', 'NIC Copies of both parties', 'Details of 2 Witnesses', 'Notice of Marriage Form'],
    'BR' => ['Approved Business Name Request', 'NIC Copies of Owners', 'Partnership Deed (if applicable)', 'Registration Form'],
    'LS' => ['Original Title Deed', 'Certified Survey Plan', 'NIC Copy', 'Land Information Form'],
    'SS' => ['Income Declaration Certificate', 'Grama Niladhari Recommendation', 'Family details sheet', 'Welfare Application Form'],
    'EA' => ['Birth Certificate Copy (showing 60+ years)', 'Grama Niladhari Residency Verification', '2 Passport Photos', 'Senior Citizen ID Application Form'],
    'DA' => ['Specialist Medical Assessment Report', 'Grama Niladhari Recommendation', 'NIC Copy', 'Social Services Request Form']
];

// Handle Booking Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $citizen_id = $_SESSION['user_id'];
    $service_id = intval($_POST['service_id'] ?? 0);
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $time_slot = trim($_POST['time_slot'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($service_id) || empty($appointment_date) || empty($time_slot)) {
        $error_msg = "Please select a service, date, and time slot.";
    } elseif (!isset($services[$service_id])) {
        $error_msg = "Invalid service selected.";
    } else {
        // Double check if date is holiday
        $check_h = $conn->prepare("SELECT id FROM holidays WHERE holiday_date = ?");
        $check_h->bind_param("s", $appointment_date);
        $check_h->execute();
        $res_h = $check_h->get_result();
        $is_holiday = $res_h->num_rows > 0;
        $check_h->close();

        // Check if day of week is weekend/closed
        $day_of_week = date('l', strtotime($appointment_date));
        $check_s = $conn->prepare("SELECT id FROM schedules WHERE day_of_week = ?");
        $check_s->bind_param("s", $day_of_week);
        $check_s->execute();
        $res_s = $check_s->get_result();
        $is_closed = $res_s->num_rows === 0;
        $check_s->close();

        if ($is_holiday) {
            $error_msg = "The office is closed on this date (Holiday).";
        } elseif ($is_closed) {
            $error_msg = "The office is closed on weekends.";
        } else {
            // Check if this time slot is already fully booked
            // Get active staff capacity
            $check_cap = $conn->prepare("SELECT COUNT(*) as active_staff FROM staff WHERE service_id = ? AND status = 'active'");
            $check_cap->bind_param("i", $service_id);
            $check_cap->execute();
            $res_cap = $check_cap->get_result()->fetch_assoc();
            $capacity = intval($res_cap['active_staff'] ?? 0);
            if ($capacity === 0) $capacity = 1;
            $check_cap->close();

            // Count existing bookings for this service, date, slot
            $check_bk = $conn->prepare("SELECT COUNT(*) as bk_count FROM appointments WHERE service_id = ? AND appointment_date = ? AND time_slot = ? AND status IN ('pending', 'completed')");
            $check_bk->bind_param("iss", $service_id, $appointment_date, $time_slot);
            $check_bk->execute();
            $res_bk = $check_bk->get_result()->fetch_assoc();
            $booked_count = intval($res_bk['bk_count']);
            $check_bk->close();

            if ($booked_count >= $capacity) {
                $error_msg = "This slot has just been fully booked. Please select another slot.";
            } else {
                // Generate Sequential Token Number per service per day
                $conn->begin_transaction();
                try {
                    // Count total tokens created for this service on this date (online + walk-ins)
                    $token_q = $conn->prepare("SELECT COUNT(*) as total_tokens FROM queue_tokens WHERE service_id = ? AND token_date = ?");
                    $token_q->bind_param("is", $service_id, $appointment_date);
                    $token_q->execute();
                    $token_res = $token_q->get_result()->fetch_assoc();
                    $seq_num = intval($token_res['total_tokens'] ?? 0) + 1;
                    $token_q->close();

                    $prefix = $services[$service_id]['prefix'];
                    $token_number = $prefix . '-' . str_pad($seq_num, 3, '0', STR_PAD_LEFT);

                    // Insert Appointment
                    $inst_ap = $conn->prepare("INSERT INTO appointments (citizen_id, service_id, appointment_date, time_slot, token_number, status, notes) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
                    $inst_ap->bind_param("iissss", $citizen_id, $service_id, $appointment_date, $time_slot, $token_number, $notes);
                    $inst_ap->execute();
                    $appointment_id = $conn->insert_id;
                    $inst_ap->close();

                    // Insert Queue Token
                    $citizen_name = $_SESSION['full_name'];
                    // Get citizen phone
                    $phone_q = $conn->prepare("SELECT phone FROM users WHERE id = ?");
                    $phone_q->bind_param("i", $citizen_id);
                    $phone_q->execute();
                    $phone_res = $phone_q->get_result()->fetch_assoc();
                    $citizen_phone = $phone_res['phone'] ?? '';
                    $phone_q->close();

                    $inst_tkn = $conn->prepare("INSERT INTO queue_tokens (appointment_id, service_id, citizen_name, phone, token_number, token_date, status, type) VALUES (?, ?, ?, ?, ?, ?, 'waiting', 'online')");
                    $inst_tkn->bind_param("iissss", $appointment_id, $service_id, $citizen_name, $citizen_phone, $token_number, $appointment_date);
                    $inst_tkn->execute();
                    $inst_tkn->close();

                    // Log Activity
                    $log_act = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Book Appointment', ?)");
                    $details = "Booked appointment for service prefix " . $prefix . " on " . $appointment_date . " at " . $time_slot . ". Token: " . $token_number;
                    $log_act->bind_param("is", $citizen_id, $details);
                    $log_act->execute();
                    $log_act->close();

                    $conn->commit();
                    
                    // Redirect to print page or details page
                    header("Location: citizen_appointments.php?success=" . urlencode("Appointment booked successfully! Your Token is " . $token_number));
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_msg = "Database error during booking: " . $e->getMessage();
                }
            }
        }
    }
}

$active_srv = $services[$selected_service_id] ?? null;
$active_prefix = $active_srv['prefix'] ?? 'BC';
$docs = $required_docs[$active_prefix] ?? ['Original NIC', 'Application Form'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - Divisional Secretariat</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .booking-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 30px;
        }
        .service-details-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--shadow);
            height: fit-content;
        }
        .doc-list {
            margin-top: 20px;
            list-style: none;
        }
        .doc-list li {
            font-size: 14px;
            color: var(--text-color);
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .doc-list li i {
            color: var(--secondary-light);
            margin-top: 3px;
        }
        .slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .slot-btn {
            background-color: #f9fafb;
            border: 1px solid var(--border-color);
            padding: 12px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-color);
            cursor: pointer;
            text-align: center;
            transition: var(--transition);
        }
        .slot-btn:hover:not(.disabled) {
            border-color: var(--primary-light);
            background-color: #f3f7ff;
            transform: translateY(-2px);
        }
        .slot-btn.active {
            background-color: var(--secondary-color) !important;
            color: white !important;
            border-color: var(--secondary-color) !important;
        }
        .slot-btn.disabled {
            background-color: #f3f4f6;
            color: #d1d5db;
            cursor: not-allowed;
            border-color: #e5e7eb;
        }
        .calendar-picker {
            margin-top: 10px;
        }
        @media (max-width: 900px) {
            .booking-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="app-container">
    <!-- Sidebar Include -->
    <?php include 'includes/citizen_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header Bar Include -->
        <?php include 'includes/header.php'; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <div class="booking-container">
            <!-- Left Column: Service Details & Required Documents -->
            <div class="service-details-card">
                <div class="service-icon-box" style="margin: 0 0 15px 0;">
                    <i class="fas <?php echo htmlspecialchars($active_srv['icon'] ?? 'fa-file-invoice'); ?>"></i>
                </div>
                <h3><?php echo htmlspecialchars($active_srv['name'] ?? 'Select Service'); ?></h3>
                <p style="font-size:13px; color: var(--text-muted); margin-top:5px; line-height:1.4;">
                    <?php echo htmlspecialchars($active_srv['description'] ?? ''); ?>
                </p>
                
                <h4 style="margin-top: 25px; border-top: 1px solid var(--border-color); padding-top: 20px;">Required Documents</h4>
                <ul class="doc-list">
                    <?php foreach ($docs as $d): ?>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span><?php echo htmlspecialchars($d); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div style="background-color: #fff9e6; border: 1px solid #ffeeba; border-radius: 12px; padding: 15px; margin-top: 25px; font-size: 13px; color: #856404;">
                    <i class="fas fa-exclamation-triangle"></i> Please bring the original documents listed above for verification at the counter.
                </div>
            </div>

            <!-- Right Column: Booking Details Form -->
            <div class="form-card">
                <h3 style="margin-bottom: 20px; color: var(--primary-color);">Select Appointment Details</h3>
                
                <form action="citizen_book.php" method="POST" id="bookingForm">
                    <div class="form-group">
                        <label for="service_select">Select Service</label>
                        <select class="form-control" name="service_id" id="service_select" onchange="changeService(this.value)">
                            <?php foreach ($services as $id => $s): ?>
                                <option value="<?php echo $id; ?>" <?php echo ($selected_service_id == $id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="office_select">Select Office</label>
                            <select class="form-control" id="office_select" disabled>
                                <option>Divisional Secretariat Office - Matara</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="appointment_date">Select Date</label>
                            <input type="date" class="form-control" name="appointment_date" id="appointment_date" 
                                   min="<?php echo date('Y-m-d'); ?>" 
                                   value="<?php echo date('Y-m-d'); ?>" 
                                   onchange="fetchSlots()">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Select Time Slot</label>
                        <div id="slots_loader" style="display:none; text-align:center; padding: 20px; color: var(--text-muted);">
                            <i class="fas fa-spinner fa-spin"></i> Loading available slots...
                        </div>
                        <div id="slots_error" style="display:none; color: #c53929; background-color: #fce8e6; border:1px solid #f8c9c4; padding:12px; border-radius:10px; font-size:14px;"></div>
                        <div class="slots-grid" id="slots_container">
                            <!-- Slots will load dynamically via JS -->
                        </div>
                        <input type="hidden" name="time_slot" id="selected_time_slot" required>
                    </div>

                    <div class="form-group">
                        <label for="notes">Additional Notes (Optional)</label>
                        <textarea class="form-control" name="notes" id="notes" rows="3" placeholder="Enter any additional information..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; height: 50px; font-size:16px;">
                        Confirm Appointment
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
function changeService(id) {
    window.location.href = 'citizen_book.php?service_id=' + id;
}

function selectSlot(element, slotTime) {
    if (element.classList.contains('disabled')) return;
    
    // Remove active class from all slots
    var slots = document.getElementsByClassName('slot-btn');
    for (var i = 0; i < slots.length; i++) {
        slots[i].classList.remove('active');
    }
    
    // Add active to selected
    element.classList.add('active');
    document.getElementById('selected_time_slot').value = slotTime;
}

function fetchSlots() {
    var serviceId = document.getElementById('service_select').value;
    var date = document.getElementById('appointment_date').value;
    
    if (!serviceId || !date) return;
    
    var container = document.getElementById('slots_container');
    var loader = document.getElementById('slots_loader');
    var errorDiv = document.getElementById('slots_error');
    
    container.innerHTML = '';
    document.getElementById('selected_time_slot').value = '';
    
    loader.style.display = 'block';
    errorDiv.style.display = 'none';
    
    fetch('get_slots.php?service_id=' + serviceId + '&date=' + date)
        .then(response => response.json())
        .then(data => {
            loader.style.display = 'none';
            if (data.error) {
                errorDiv.innerText = data.error;
                errorDiv.style.display = 'block';
            } else if (data.slots && data.slots.length > 0) {
                data.slots.forEach(slot => {
                    var btn = document.createElement('div');
                    btn.className = 'slot-btn';
                    btn.innerText = slot.display;
                    
                    if (slot.available) {
                        btn.onclick = function() { selectSlot(this, slot.time); };
                    } else {
                        btn.classList.add('disabled');
                    }
                    container.appendChild(btn);
                });
            } else {
                errorDiv.innerText = "No operating slots configured for this day.";
                errorDiv.style.display = 'block';
            }
        })
        .catch(err => {
            loader.style.display = 'none';
            errorDiv.innerText = "Error loading slots. Please refresh the page.";
            errorDiv.style.display = 'block';
            console.error(err);
        });
}

// Initial load
document.addEventListener("DOMContentLoaded", function() {
    fetchSlots();
    
    // Add Form Submit Validation
    document.getElementById('bookingForm').addEventListener('submit', function(e) {
        var slot = document.getElementById('selected_time_slot').value;
        if (!slot) {
            e.preventDefault();
            alert("Please select an available time slot before confirming.");
        }
    });
});
</script>
</body>
</html>
