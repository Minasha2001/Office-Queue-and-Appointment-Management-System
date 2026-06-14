<?php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$page_title = "Operating Schedules & Holidays";
$success_msg = "";
$error_msg = "";

// 1. UPDATE SCHEDULE HOURS
if (isset($_POST['update_schedule'])) {
    $day_of_week = $_POST['day_of_week'] ?? '';
    $start_time = $_POST['start_time'] ?? '08:30:00';
    $end_time = $_POST['end_time'] ?? '16:30:00';
    $slot_duration = intval($_POST['slot_duration_minutes'] ?? 30);

    if (empty($day_of_week)) {
        $error_msg = "Please select a day of the week.";
    } else {
        $up = $conn->prepare("UPDATE schedules SET start_time = ?, end_time = ?, slot_duration_minutes = ? WHERE day_of_week = ?");
        $up->bind_param("ssis", $start_time, $end_time, $slot_duration, $day_of_week);
        if ($up->execute()) {
            // Log Activity
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Update Schedule', ?)");
            $details = "Updated hours for $day_of_week: $start_time - $end_time, duration $slot_duration mins";
            $log->bind_param("is", $_SESSION['user_id'], $details);
            $log->execute();
            $log->close();

            $success_msg = "Schedule updated successfully.";
        } else {
            $error_msg = "Failed to update schedule: " . $conn->error;
        }
        $up->close();
    }
}

// 2. ADD HOLIDAY
if (isset($_POST['add_holiday'])) {
    $holiday_date = trim($_POST['holiday_date'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($holiday_date) || empty($description)) {
        $error_msg = "Date and description are required for adding a holiday.";
    } else {
        // Check if date already defined
        $chk = $conn->prepare("SELECT id FROM holidays WHERE holiday_date = ?");
        $chk->bind_param("s", $holiday_date);
        $chk->execute();
        $exists = $chk->get_result()->num_rows > 0;
        $chk->close();

        if ($exists) {
            $error_msg = "Date '$holiday_date' is already registered as a holiday.";
        } else {
            $ins = $conn->prepare("INSERT INTO holidays (holiday_date, description) VALUES (?, ?)");
            $ins->bind_param("ss", $holiday_date, $description);
            if ($ins->execute()) {
                // Log Activity
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Add Holiday', ?)");
                $details = "Added holiday on $holiday_date: $description";
                $log->bind_param("is", $_SESSION['user_id'], $details);
                $log->execute();
                $log->close();

                $success_msg = "Holiday added successfully.";
            } else {
                $error_msg = "Failed to add holiday: " . $conn->error;
            }
            $ins->close();
        }
    }
}

// 3. DELETE HOLIDAY
if (isset($_POST['delete_holiday'])) {
    $id = intval($_POST['holiday_id']);
    
    $del = $conn->prepare("DELETE FROM holidays WHERE id = ?");
    $del->bind_param("i", $id);
    if ($del->execute()) {
        $success_msg = "Holiday deleted successfully.";
    } else {
        $error_msg = "Failed to delete holiday: " . $conn->error;
    }
    $del->close();
}

// Fetch all standard schedules
$schedules_res = mysqli_query($conn, "SELECT day_of_week, start_time, end_time, slot_duration_minutes FROM schedules ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')");
$schedules = [];
while ($row = mysqli_fetch_assoc($schedules_res)) {
    $schedules[$row['day_of_week']] = $row;
}

// Fetch all upcoming holidays
$holidays_res = mysqli_query($conn, "SELECT id, holiday_date, description FROM holidays ORDER BY holiday_date ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - Divisional Secretariat</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .schedule-grid-container {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 30px;
        }
        .schedule-day-box {
            background-color: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .schedule-day-box h4 {
            color: var(--primary-color);
        }
        @media (max-width: 1024px) {
            .schedule-grid-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="app-container">
    <!-- Sidebar Include -->
    <?php include 'includes/admin_sidebar.php'; ?>

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

        <div class="schedule-grid-container">
            <!-- Left Panel: Weekly Operating Hours -->
            <div>
                <div class="table-card" style="padding: 24px;">
                    <h3 style="color: var(--primary-color); margin-bottom: 20px;"><i class="fas fa-business-time"></i> Standard Weekly Schedules</h3>
                    
                    <?php foreach ($schedules as $day => $s): ?>
                        <div class="schedule-day-box">
                            <div>
                                <h4><?php echo $day; ?></h4>
                                <p style="font-size: 13px; color: var(--text-muted); margin-top:4px;">
                                    Working Hours: <strong><?php echo date('h:i A', strtotime($s['start_time'])); ?> - <?php echo date('h:i A', strtotime($s['end_time'])); ?></strong> 
                                    • Slot Duration: <strong><?php echo $s['slot_duration_minutes']; ?> mins</strong>
                                </p>
                            </div>
                            <button onclick="loadScheduleEdit('<?php echo $day; ?>', '<?php echo $s['start_time']; ?>', '<?php echo $s['end_time']; ?>', <?php echo $s['slot_duration_minutes']; ?>)" class="btn btn-secondary" style="padding: 6px 12px; font-size:12px; border:1px solid var(--border-color);">
                                <i class="fas fa-clock"></i> Modify
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Schedule Edit Form Area (Hidden by default, shown on click) -->
                <div class="form-card" id="schedFormPanel" style="display: none; margin-top:25px;">
                    <h3 style="color:var(--primary-color); margin-bottom:15px; border-bottom:1px solid var(--border-color); padding-bottom:10px;" id="schedFormTitle">Modify Monday Hours</h3>
                    <form action="admin_schedules.php" method="POST">
                        <input type="hidden" name="day_of_week" id="form_day_of_week">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="form_start_time">Start Time</label>
                                <input type="time" class="form-control" name="start_time" id="form_start_time" required>
                            </div>
                            <div class="form-group">
                                <label for="form_end_time">End Time</label>
                                <input type="time" class="form-control" name="end_time" id="form_end_time" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="form_slot_duration">Time Slot Duration (Minutes)</label>
                                <input type="number" class="form-control" name="slot_duration_minutes" id="form_slot_duration" min="5" max="120" required>
                            </div>
                            <div class="form-group" style="display:flex; align-items:flex-end;">
                                <button type="submit" name="update_schedule" class="btn btn-primary" style="width:100%; height:45px;">Save Schedule</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Panel: Holidays & Closed Dates -->
            <div>
                <!-- Add Holiday Form -->
                <div class="form-card" style="margin-bottom: 25px;">
                    <h3 style="color: var(--primary-color); border-bottom:1px solid var(--border-color); padding-bottom:15px; margin-bottom:20px;">
                        <i class="fas fa-calendar-minus"></i> Add Office Holiday
                    </h3>
                    <form action="admin_schedules.php" method="POST">
                        <div class="form-group">
                            <label for="holiday_date">Holiday Date</label>
                            <input type="date" class="form-control" name="holiday_date" id="holiday_date" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="description">Holiday Description</label>
                            <input type="text" class="form-control" name="description" id="description" placeholder="e.g. Sinhala & Tamil New Year" required>
                        </div>
                        <button type="submit" name="add_holiday" class="btn btn-primary" style="width:100%;">Add Holiday</button>
                    </form>
                </div>

                <!-- Holidays List -->
                <div class="table-card">
                    <div style="padding: 20px; border-bottom: 1px solid var(--border-color);">
                        <h3 style="color: var(--primary-color); font-size:16px;"><i class="fas fa-umbrella-beach"></i> Registered Closed Dates</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="custom-table" style="font-size:13px;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Occasion</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($holidays_res) > 0): ?>
                                    <?php while ($hol = mysqli_fetch_assoc($holidays_res)): ?>
                                        <tr>
                                            <td style="font-weight: 600;"><?php echo htmlspecialchars($hol['holiday_date']); ?></td>
                                            <td><?php echo htmlspecialchars($hol['description']); ?></td>
                                            <td>
                                                <form action="admin_schedules.php" method="POST" onsubmit="return confirm('Remove this holiday and open bookings?');">
                                                    <input type="hidden" name="holiday_id" value="<?php echo $hol['id']; ?>">
                                                    <button type="submit" name="delete_holiday" class="btn btn-primary" style="padding: 4px 8px; font-size:11px; background-color:#ea4335; border:none;">
                                                        <i class="far fa-trash-alt"></i> Remove
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" style="text-align:center; padding:20px; color:var(--text-muted);">No holidays scheduled.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function loadScheduleEdit(day, start, end, duration) {
    document.getElementById('schedFormTitle').innerText = "Modify " + day + " Hours";
    document.getElementById('form_day_of_week').value = day;
    document.getElementById('form_start_time').value = start;
    document.getElementById('form_end_time').value = end;
    document.getElementById('form_slot_duration').value = duration;
    document.getElementById('schedFormPanel').style.display = 'block';
    
    // Smooth scroll to panel
    document.getElementById('schedFormPanel').scrollIntoView({ behavior: 'smooth' });
}
</script>
</body>
</html>
