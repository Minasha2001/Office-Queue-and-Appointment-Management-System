<?php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$page_title = "Admin Dashboard";
$today_str = date('Y-m-d');

// 1. Fetch Daily Analytics
// A. Total appointments today
$appt_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM appointments WHERE appointment_date = '$today_str'");
$appt_count = mysqli_fetch_assoc($appt_q)['total'];

// B. Total completed services today
$comp_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM queue_tokens WHERE token_date = '$today_str' AND status = 'completed'");
$completed_count = mysqli_fetch_assoc($comp_q)['total'];

// C. Total waiting customers today
$wait_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM queue_tokens WHERE token_date = '$today_str' AND status = 'waiting'");
$waiting_count = mysqli_fetch_assoc($wait_q)['total'];

// D. Total absent customers today
$abs_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM queue_tokens WHERE token_date = '$today_str' AND status = 'absent'");
$absent_count = mysqli_fetch_assoc($abs_q)['total'];

// 2. Fetch Recent Bookings (Last 6 appointments)
$recent_q = "SELECT a.id, a.appointment_date, a.time_slot, a.token_number, a.status, s.name as service_name, u.full_name as citizen_name 
            FROM appointments a 
            JOIN services s ON a.service_id = s.id 
            JOIN users u ON a.citizen_id = u.id 
            ORDER BY a.id DESC LIMIT 6";
$recent_res = mysqli_query($conn, $recent_q);

// 3. Fetch Service Queue Status summary for today
$srv_sum_q = "SELECT s.name, s.prefix,
             (SELECT COUNT(*) FROM queue_tokens q WHERE q.service_id = s.id AND q.token_date = '$today_str') as total_today,
             (SELECT COUNT(*) FROM queue_tokens q WHERE q.service_id = s.id AND q.token_date = '$today_str' AND q.status = 'waiting') as waiting_today,
             (SELECT token_number FROM queue_tokens q WHERE q.service_id = s.id AND q.token_date = '$today_str' AND q.status IN ('called', 'serving') ORDER BY q.called_at DESC LIMIT 1) as active_now
             FROM services s WHERE s.status = 'active'";
$srv_sum_res = mysqli_query($conn, $srv_sum_q);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Divisional Secretariat</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 30px;
            margin-top: 30px;
        }
        @media (max-width: 1024px) {
            .dashboard-grid {
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

        <div style="margin-bottom: 30px; display:flex; justify-content:space-between; align-items:center;">
            <p style="color: var(--text-muted);">Welcome to the Divisional Secretariat Queue Administration Panel. Daily stats are auto-calculated.</p>
            <span style="background: white; border: 1px solid var(--border-color); padding: 8px 16px; border-radius:10px; font-weight:600; font-size:13px; color: var(--text-muted);">
                <i class="fas fa-calendar-alt"></i> Today: <?php echo date('F d, Y'); ?>
            </span>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-info">
                    <h3><?php echo $appt_count; ?></h3>
                    <p>Total Bookings (Daily)</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fas fa-check-double"></i></div>
                <div class="stat-info">
                    <h3><?php echo $completed_count; ?></h3>
                    <p>Total Completed Services</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-yellow"><i class="fas fa-user-clock"></i></div>
                <div class="stat-info">
                    <h3><?php echo $waiting_count; ?></h3>
                    <p>Waiting Customers</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-red"><i class="fas fa-user-times"></i></div>
                <div class="stat-info">
                    <h3><?php echo $absent_count; ?></h3>
                    <p>Absent / No-shows</p>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Left Column: Recent Bookings -->
            <div class="table-card">
                <div style="padding: 20px; border-bottom: 1px solid var(--border-color);">
                    <h3 style="color: var(--primary-color); font-size:18px;"><i class="fas fa-history"></i> Recent Booking Requests</h3>
                </div>
                <div class="table-responsive">
                    <table class="custom-table" style="font-size:13px;">
                        <thead>
                            <tr>
                                <th>Citizen</th>
                                <th>Service</th>
                                <th>Slot</th>
                                <th>Token</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($recent_res) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($recent_res)): ?>
                                    <tr>
                                        <td style="font-weight:600;"><?php echo htmlspecialchars($row['citizen_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['service_name']); ?></td>
                                        <td><?php echo date('h:i A', strtotime($row['time_slot'])); ?></td>
                                        <td style="font-family:monospace; font-weight:700; color:var(--primary-light);"><?php echo htmlspecialchars($row['token_number']); ?></td>
                                        <td>
                                            <?php 
                                            $st = $row['status'];
                                            $badge = 'badge-pending';
                                            if ($st === 'completed') $badge = 'badge-completed';
                                            elseif ($st === 'absent') $badge = 'badge-absent';
                                            elseif ($st === 'cancelled') $badge = 'badge-cancelled';
                                            elseif ($st === 'rescheduled') $badge = 'badge-rescheduled';
                                            ?>
                                            <span class="badge <?php echo $badge; ?>" style="font-size: 11px; padding: 4px 8px;"><?php echo ucfirst($st); ?></span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding: 25px; color:var(--text-muted);">No bookings found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Column: Today's Queues Summary -->
            <div class="table-card">
                <div style="padding: 20px; border-bottom: 1px solid var(--border-color);">
                    <h3 style="color: var(--primary-color); font-size:18px;"><i class="fas fa-users"></i> Services Queue Overview</h3>
                </div>
                <div class="table-responsive">
                    <table class="custom-table" style="font-size:13px;">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Active Token</th>
                                <th>Waiting</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($srv_sum_res) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($srv_sum_res)): ?>
                                    <tr>
                                        <td style="font-weight:600;">
                                            <?php echo htmlspecialchars($row['name']); ?> 
                                            <span style="font-size:10px; color:var(--text-muted); font-family:monospace; font-weight:normal;">[<?php echo htmlspecialchars($row['prefix']); ?>]</span>
                                        </td>
                                        <td style="font-family:monospace; font-weight:700; color:var(--secondary-color);">
                                            <?php echo htmlspecialchars($row['active_now'] ?? 'None'); ?>
                                        </td>
                                        <td style="font-weight:bold; color: <?php echo ($row['waiting_today'] > 0) ? '#d97706' : 'var(--text-color)'; ?>;">
                                            <?php echo $row['waiting_today']; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align:center; padding: 25px; color:var(--text-muted);">No active services found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>
