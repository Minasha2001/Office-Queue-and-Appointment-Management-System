<?php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header("Location: login.php");
    exit();
}

$citizen_id = $_SESSION['user_id'];
$page_title = "My Appointments";
$success_msg = $_GET['success'] ?? '';
$error_msg = $_GET['error'] ?? '';

// Handle Appointment Cancellation
if (isset($_POST['cancel_appointment'])) {
    $appointment_id = intval($_POST['appointment_id']);
    
    // Verify appointment belongs to this citizen and is pending
    $verify_q = $conn->prepare("SELECT id FROM appointments WHERE id = ? AND citizen_id = ? AND status = 'pending'");
    $verify_q->bind_param("ii", $appointment_id, $citizen_id);
    $verify_q->execute();
    $belongs = $verify_q->get_result()->num_rows > 0;
    $verify_q->close();

    if ($belongs) {
        $conn->begin_transaction();
        try {
            // Update appointment status
            $up_ap = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
            $up_ap->bind_param("i", $appointment_id);
            $up_ap->execute();
            $up_ap->close();

            // Update queue token status
            $up_tkn = $conn->prepare("UPDATE queue_tokens SET status = 'skipped' WHERE appointment_id = ?");
            $up_tkn->bind_param("i", $appointment_id);
            $up_tkn->execute();
            $up_tkn->close();

            // Log Activity
            $log_act = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Cancel Appointment', ?)");
            $details = "Cancelled appointment ID: " . $appointment_id;
            $log_act->bind_param("is", $citizen_id, $details);
            $log_act->execute();
            $log_act->close();

            $conn->commit();
            $success_msg = "Appointment cancelled successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Failed to cancel appointment: " . $e->getMessage();
        }
    } else {
        $error_msg = "Unauthorized or invalid appointment.";
    }
}

// Handle Appointment Rescheduling
if (isset($_POST['reschedule_appointment'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $new_date = trim($_POST['new_date'] ?? '');
    $new_time = trim($_POST['new_time'] ?? '');

    if (empty($new_date) || empty($new_time)) {
        $error_msg = "Please select a date and time slot for rescheduling.";
    } else {
        // Verify appointment is pending and belongs to this citizen
        $chk = $conn->prepare("SELECT service_id, appointment_date FROM appointments WHERE id = ? AND citizen_id = ? AND status = 'pending'");
        $chk->bind_param("ii", $appointment_id, $citizen_id);
        $chk->execute();
        $res_chk = $chk->get_result();
        $appt_data = $res_chk->fetch_assoc();
        $chk->close();

        if ($appt_data) {
            $service_id = $appt_data['service_id'];
            $old_date = $appt_data['appointment_date'];

            // Double check availability
            // Get active staff capacity
            $check_cap = $conn->prepare("SELECT COUNT(*) as active_staff FROM staff WHERE service_id = ? AND status = 'active'");
            $check_cap->bind_param("i", $service_id);
            $check_cap->execute();
            $res_cap = $check_cap->get_result()->fetch_assoc();
            $capacity = intval($res_cap['active_staff'] ?? 0);
            if ($capacity === 0) $capacity = 1;
            $check_cap->close();

            // Count existing bookings
            $check_bk = $conn->prepare("SELECT COUNT(*) as bk_count FROM appointments WHERE service_id = ? AND appointment_date = ? AND time_slot = ? AND status IN ('pending', 'completed') AND id != ?");
            $check_bk->bind_param("issi", $service_id, $new_date, $new_time, $appointment_id);
            $check_bk->execute();
            $res_bk = $check_bk->get_result()->fetch_assoc();
            $booked_count = intval($res_bk['bk_count']);
            $check_bk->close();

            if ($booked_count >= $capacity) {
                $error_msg = "The selected slot is fully booked. Please select another slot.";
            } else {
                $conn->begin_transaction();
                try {
                    // Recalculate Token if date has changed
                    if ($old_date !== $new_date) {
                        // Count tokens for the service on the new date
                        $token_q = $conn->prepare("SELECT COUNT(*) as total_tokens FROM queue_tokens WHERE service_id = ? AND token_date = ?");
                        $token_q->bind_param("is", $service_id, $new_date);
                        $token_q->execute();
                        $token_res = $token_q->get_result()->fetch_assoc();
                        $seq_num = intval($token_res['total_tokens'] ?? 0) + 1;
                        $token_q->close();

                        // Get Service Prefix
                        $pref_q = $conn->prepare("SELECT prefix FROM services WHERE id = ?");
                        $pref_q->bind_param("i", $service_id);
                        $pref_q->execute();
                        $prefix = $pref_q->get_result()->fetch_assoc()['prefix'] ?? 'SRV';
                        $pref_q->close();

                        $new_token_number = $prefix . '-' . str_pad($seq_num, 3, '0', STR_PAD_LEFT);
                    } else {
                        // Keep the old token number
                        $token_num_q = $conn->prepare("SELECT token_number FROM appointments WHERE id = ?");
                        $token_num_q->bind_param("i", $appointment_id);
                        $token_num_q->execute();
                        $new_token_number = $token_num_q->get_result()->fetch_assoc()['token_number'];
                        $token_num_q->close();
                    }

                    // Update Appointment
                    $up_ap = $conn->prepare("UPDATE appointments SET appointment_date = ?, time_slot = ?, token_number = ?, status = 'rescheduled' WHERE id = ?");
                    $up_ap->bind_param("sssi", $new_date, $new_time, $new_token_number, $appointment_id);
                    $up_ap->execute();
                    $up_ap->close();

                    // Update Queue Token
                    $up_tkn = $conn->prepare("UPDATE queue_tokens SET token_number = ?, token_date = ?, status = 'waiting' WHERE appointment_id = ?");
                    $up_tkn->bind_param("ssi", $new_token_number, $new_date, $appointment_id);
                    $up_tkn->execute();
                    $up_tkn->close();

                    // Log Activity
                    $log_act = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Reschedule Appointment', ?)");
                    $details = "Rescheduled appointment ID: $appointment_id to $new_date at $new_time. New Token: $new_token_number";
                    $log_act->bind_param("is", $citizen_id, $details);
                    $log_act->execute();
                    $log_act->close();

                    $conn->commit();
                    $success_msg = "Appointment rescheduled successfully! Your new Token is: " . $new_token_number;
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_msg = "Rescheduling failed: " . $e->getMessage();
                }
            }
        } else {
            $error_msg = "Appointment not found or not in pending status.";
        }
    }
}

// Fetch Citizen's appointments
$query = "SELECT a.id, a.appointment_date, a.time_slot, a.token_number, a.status, a.notes, s.name as service_name, s.id as service_id 
          FROM appointments a 
          JOIN services s ON a.service_id = s.id 
          WHERE a.citizen_id = ? 
          ORDER BY a.appointment_date DESC, a.time_slot DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $citizen_id);
$stmt->execute();
$appointments = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Divisional Secretariat</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .reschedule-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 500px;
            box-shadow: var(--shadow-lg);
            position: relative;
        }
        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-muted);
        }
        .reschedule-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        .r-slot-btn {
            background-color: #f9fafb;
            border: 1px solid var(--border-color);
            padding: 10px 5px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            transition: var(--transition);
        }
        .r-slot-btn:hover:not(.disabled) {
            border-color: var(--primary-light);
            background-color: #f3f7ff;
        }
        .r-slot-btn.active {
            background-color: var(--secondary-color) !important;
            color: white !important;
            border-color: var(--secondary-color) !important;
        }
        .r-slot-btn.disabled {
            background-color: #f3f4f6;
            color: #d1d5db;
            cursor: not-allowed;
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

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <div class="table-card">
            <div style="padding: 24px; border-bottom: 1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                <h3 style="color: var(--primary-color);">Your Registered Appointments</h3>
                <a href="citizen_book.php" class="btn btn-primary" style="padding: 10px 20px;">
                    <i class="fas fa-plus"></i> New Appointment
                </a>
            </div>

            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Service Name</th>
                            <th>Date</th>
                            <th>Time Slot</th>
                            <th>Token Number</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($appointments->num_rows > 0): ?>
                            <?php while ($appt = $appointments->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($appt['service_name']); ?></td>
                                    <td><?php echo htmlspecialchars($appt['appointment_date']); ?></td>
                                    <td><?php echo date('h:i A', strtotime($appt['time_slot'])); ?></td>
                                    <td style="font-family: monospace; font-weight:700; font-size:16px; color: var(--primary-light);">
                                        <?php echo htmlspecialchars($appt['token_number']); ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = $appt['status'];
                                        $badge_class = 'badge-pending';
                                        if ($status === 'completed') $badge_class = 'badge-completed';
                                        elseif ($status === 'absent') $badge_class = 'badge-absent';
                                        elseif ($status === 'cancelled') $badge_class = 'badge-cancelled';
                                        elseif ($status === 'rescheduled') $badge_class = 'badge-rescheduled';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($status); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($status === 'pending' || $status === 'rescheduled'): ?>
                                            <a href="print_token.php?appointment_id=<?php echo $appt['id']; ?>" target="_blank" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;" title="Print Token Slip">
                                                <i class="fas fa-print"></i> Print
                                            </a>
                                            <button onclick="openReschedule(<?php echo $appt['id']; ?>, <?php echo $appt['service_id']; ?>, '<?php echo $appt['appointment_date']; ?>')" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px; background-color: var(--primary-light);">
                                                <i class="fas fa-redo"></i> Reschedule
                                            </button>
                                            
                                            <form action="citizen_appointments.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                                <button type="submit" name="cancel_appointment" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px; background-color: #ea4335;">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size:13px;">No actions available</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                    You have no appointments registered.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Reschedule Modal -->
<div class="reschedule-modal" id="rModal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeReschedule()">&times;</span>
        <h3 style="color: var(--primary-color); margin-bottom: 20px;">Reschedule Appointment</h3>
        
        <form action="citizen_appointments.php" method="POST" id="rescheduleForm">
            <input type="hidden" name="appointment_id" id="res_appointment_id">
            <input type="hidden" name="res_service_id" id="res_service_id">
            
            <div class="form-group">
                <label for="res_date">Select New Date</label>
                <input type="date" class="form-control" name="new_date" id="res_date" min="<?php echo date('Y-m-d'); ?>" onchange="fetchRescheduleSlots()">
            </div>
            
            <div class="form-group">
                <label>Select New Time Slot</label>
                <div id="res_loader" style="display:none; text-align:center; padding: 10px; color: var(--text-muted);">
                    <i class="fas fa-spinner fa-spin"></i> Loading slots...
                </div>
                <div id="res_slots_error" style="display:none; color: #c53929; background-color: #fce8e6; padding:8px; border-radius:8px; font-size:13px; margin-bottom:10px;"></div>
                <div class="reschedule-slots" id="res_slots_container">
                    <!-- Slots dynamic -->
                </div>
                <input type="hidden" name="new_time" id="res_selected_time" required>
            </div>
            
            <button type="submit" name="reschedule_appointment" class="btn btn-primary" style="width:100%; margin-top:20px;">Confirm Reschedule</button>
        </form>
    </div>
</div>

<script>
function openReschedule(apptId, serviceId, currentDate) {
    document.getElementById('res_appointment_id').value = apptId;
    document.getElementById('res_service_id').value = serviceId;
    document.getElementById('res_date').value = currentDate;
    document.getElementById('rModal').style.display = 'flex';
    fetchRescheduleSlots();
}

function closeReschedule() {
    document.getElementById('rModal').style.display = 'none';
}

function selectResSlot(element, time) {
    if (element.classList.contains('disabled')) return;
    var slots = document.getElementsByClassName('r-slot-btn');
    for (var i = 0; i < slots.length; i++) {
        slots[i].classList.remove('active');
    }
    element.classList.add('active');
    document.getElementById('res_selected_time').value = time;
}

function fetchRescheduleSlots() {
    var serviceId = document.getElementById('res_service_id').value;
    var date = document.getElementById('res_date').value;
    var container = document.getElementById('res_slots_container');
    var loader = document.getElementById('res_loader');
    var errorDiv = document.getElementById('res_slots_error');
    
    container.innerHTML = '';
    document.getElementById('res_selected_time').value = '';
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
                    btn.className = 'r-slot-btn';
                    btn.innerText = slot.display;
                    
                    if (slot.available) {
                        btn.onclick = function() { selectResSlot(this, slot.time); };
                    } else {
                        btn.classList.add('disabled');
                    }
                    container.appendChild(btn);
                });
            } else {
                errorDiv.innerText = "No slots available.";
                errorDiv.style.display = 'block';
            }
        })
        .catch(err => {
            loader.style.display = 'none';
            errorDiv.innerText = "Error loading slots.";
            errorDiv.style.display = 'block';
        });
}

document.addEventListener("DOMContentLoaded", function() {
    document.getElementById('rescheduleForm').addEventListener('submit', function(e) {
        var slot = document.getElementById('res_selected_time').value;
        if (!slot) {
            e.preventDefault();
            alert("Please select a time slot.");
        }
    });
});
</script>
</body>
</html>
