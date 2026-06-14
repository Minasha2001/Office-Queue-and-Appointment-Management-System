<?php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = "Officer Queue Panel";
$success_msg = "";
$error_msg = "";

// 1. Fetch Staff info and assigned service
$staff_q = $conn->prepare("SELECT s.id as staff_id, s.service_id, s.status as staff_status, sv.name as service_name, sv.prefix as service_prefix 
                           FROM staff s 
                           JOIN services sv ON s.service_id = sv.id 
                           WHERE s.user_id = ?");
$staff_q->bind_param("i", $user_id);
$staff_q->execute();
$staff_res = $staff_q->get_result();
$staff_info = $staff_res->fetch_assoc();
$staff_q->close();

if (!$staff_info) {
    echo "<div style='padding:50px; text-align:center; font-family:sans-serif;'><h2>Access Denied</h2><p>You are not registered in the Staff table. Please contact the administrator.</p><a href='logout.php'>Logout</a></div>";
    exit();
}

if ($staff_info['staff_status'] !== 'active') {
    echo "<div style='padding:50px; text-align:center; font-family:sans-serif;'><h2>Account Inactive</h2><p>Your officer profile is set to Inactive. Please contact the administrator.</p><a href='logout.php'>Logout</a></div>";
    exit();
}

$staff_id = $staff_info['staff_id'];
$service_id = $staff_info['service_id'];
$service_name = $staff_info['service_name'];
$service_prefix = $staff_info['service_prefix'];
$today_str = date('Y-m-d');

// 2. Fetch current active token for this staff (status: called or serving)
$active_q = $conn->prepare("SELECT id, token_number, citizen_name, type, appointment_id, status FROM queue_tokens 
                            WHERE serving_staff_id = ? AND token_date = ? AND status IN ('called', 'serving') 
                            LIMIT 1");
$active_q->bind_param("is", $staff_id, $today_str);
$active_q->execute();
$active_token = $active_q->get_result()->fetch_assoc();
$active_q->close();

// 3. Handle Queue Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // A. CALL NEXT CUSTOMER
    if ($action === 'call_next') {
        if ($active_token) {
            $error_msg = "Please finish serving the current customer first.";
        } else {
            // Find next waiting customer for this service today, sorted by token number
            $next_q = $conn->prepare("SELECT id, token_number, appointment_id FROM queue_tokens 
                                      WHERE service_id = ? AND token_date = ? AND status = 'waiting' 
                                      ORDER BY token_number ASC LIMIT 1");
            $next_q->bind_param("is", $service_id, $today_str);
            $next_q->execute();
            $next_cust = $next_q->get_result()->fetch_assoc();
            $next_q->close();

            if ($next_cust) {
                $token_id = $next_cust['id'];
                $token_number = $next_cust['token_number'];
                $appointment_id = $next_cust['appointment_id'];

                $conn->begin_transaction();
                try {
                    // Update queue token status to called
                    $up_tkn = $conn->prepare("UPDATE queue_tokens SET status = 'called', called_at = NOW(), serving_staff_id = ? WHERE id = ?");
                    $up_tkn->bind_param("ii", $staff_id, $token_id);
                    $up_tkn->execute();
                    $up_tkn->close();

                    // Log Activity
                    $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Call Token', ?)");
                    $details = "Called token " . $token_number . " for service prefix " . $service_prefix;
                    $log->bind_param("is", $user_id, $details);
                    $log->execute();
                    $log->close();

                    $conn->commit();
                    header("Location: staff_dashboard.php");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_msg = "Call failed: " . $e->getMessage();
                }
            } else {
                $error_msg = "No customers waiting in the queue.";
            }
        }
    }

    // B. MARK AS COMPLETED
    if ($action === 'mark_completed' && $active_token) {
        $token_id = $active_token['id'];
        $appt_id = $active_token['appointment_id'];
        $token_number = $active_token['token_number'];

        $conn->begin_transaction();
        try {
            // Update queue token
            $up_tkn = $conn->prepare("UPDATE queue_tokens SET status = 'completed', completed_at = NOW() WHERE id = ?");
            $up_tkn->bind_param("i", $token_id);
            $up_tkn->execute();
            $up_tkn->close();

            // Update appointment if online
            if ($appt_id) {
                $up_ap = $conn->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?");
                $up_ap->bind_param("i", $appt_id);
                $up_ap->execute();
                $up_ap->close();
            }

            // Log Activity
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Complete Token', ?)");
            $details = "Completed token " . $token_number;
            $log->bind_param("is", $user_id, $details);
            $log->execute();
            $log->close();

            $conn->commit();
            header("Location: staff_dashboard.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Update failed: " . $e->getMessage();
        }
    }

    // C. MARK AS ABSENT
    if ($action === 'mark_absent' && $active_token) {
        $token_id = $active_token['id'];
        $appt_id = $active_token['appointment_id'];
        $token_number = $active_token['token_number'];

        $conn->begin_transaction();
        try {
            // Update queue token
            $up_tkn = $conn->prepare("UPDATE queue_tokens SET status = 'absent', completed_at = NOW() WHERE id = ?");
            $up_tkn->bind_param("i", $token_id);
            $up_tkn->execute();
            $up_tkn->close();

            // Update appointment if online
            if ($appt_id) {
                $up_ap = $conn->prepare("UPDATE appointments SET status = 'absent' WHERE id = ?");
                $up_ap->bind_param("i", $appt_id);
                $up_ap->execute();
                $up_ap->close();
            }

            // Log Activity
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Absent Token', ?)");
            $details = "Marked token " . $token_number . " as Absent";
            $log->bind_param("is", $user_id, $details);
            $log->execute();
            $log->close();

            $conn->commit();
            header("Location: staff_dashboard.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Update failed: " . $e->getMessage();
        }
    }

    // D. RECALL TOKEN
    if ($action === 'recall' && $active_token) {
        $token_id = $active_token['id'];
        // Update called_at to bring it up on display systems
        $up = $conn->prepare("UPDATE queue_tokens SET called_at = NOW() WHERE id = ?");
        $up->bind_param("i", $token_id);
        $up->execute();
        $up->close();
        $success_msg = "Recalled token: " . $active_token['token_number'];
        header("Location: staff_dashboard.php?success=" . urlencode($success_msg));
        exit();
    }

    // E. SKIP / SKIP TOKEN
    if ($action === 'skip' && $active_token) {
        $token_id = $active_token['id'];
        $up = $conn->prepare("UPDATE queue_tokens SET status = 'skipped', completed_at = NOW() WHERE id = ?");
        $up->bind_param("i", $token_id);
        $up->execute();
        $up->close();
        
        $success_msg = "Skipped token: " . $active_token['token_number'];
        header("Location: staff_dashboard.php?success=" . urlencode($success_msg));
        exit();
    }
}

// Fetch today's queue for this service
$queue_q = $conn->prepare("SELECT q.id, q.token_number, q.citizen_name, q.type, q.status, q.created_at, a.time_slot 
                           FROM queue_tokens q 
                           LEFT JOIN appointments a ON q.appointment_id = a.id 
                           WHERE q.service_id = ? AND q.token_date = ? 
                           ORDER BY q.token_number ASC");
$queue_q->bind_param("is", $service_id, $today_str);
$queue_q->execute();
$queue_list = $queue_q->get_result();
$queue_q->close();

// Count waiting customers for stats card
$waiting_count = 0;
$completed_count = 0;
$absent_count = 0;
$rows = [];
while ($row = $queue_list->fetch_assoc()) {
    $rows[] = $row;
    if ($row['status'] === 'waiting') $waiting_count++;
    elseif ($row['status'] === 'completed') $completed_count++;
    elseif ($row['status'] === 'absent') $absent_count++;
}

$success_msg = $_GET['success'] ?? $success_msg;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Dashboard - Divisional Secretariat</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .staff-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        .active-serve-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow-md);
            text-align: center;
            height: fit-content;
        }
        .active-token-num {
            font-size: 55px;
            font-weight: 800;
            color: var(--primary-light);
            font-family: monospace;
            margin: 15px 0;
            letter-spacing: 2px;
        }
        .active-token-num.none {
            font-size: 32px;
            color: var(--text-muted);
            font-family: inherit;
            letter-spacing: 0;
        }
        .action-row {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        @media (max-width: 1024px) {
            .staff-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="app-container">
    <!-- Sidebar Include -->
    <?php include 'includes/staff_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header Bar Include -->
        <?php include 'includes/header.php'; ?>

        <div style="background-color: var(--primary-color); color: white; padding: 20px; border-radius: var(--border-radius); margin-bottom: 30px; box-shadow: var(--shadow);">
            <h3 style="margin-bottom: 5px;"><i class="fas fa-desktop"></i> Active Counter Panel</h3>
            <p style="opacity: 0.9;">Assigned Service: <strong><?php echo htmlspecialchars($service_name); ?> (<?php echo htmlspecialchars($service_prefix); ?>)</strong></p>
        </div>

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

        <!-- Stats Bar -->
        <div class="stats-grid" style="margin-bottom: 30px;">
            <div class="stat-card">
                <div class="stat-icon icon-yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3><?php echo $waiting_count; ?></h3>
                    <p>Waiting Customers</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo $completed_count; ?></h3>
                    <p>Served Today</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-red"><i class="fas fa-user-times"></i></div>
                <div class="stat-info">
                    <h3><?php echo $absent_count; ?></h3>
                    <p>Absent / No Show</p>
                </div>
            </div>
        </div>

        <div class="staff-grid">
            <!-- Left Column: Today's Queue List -->
            <div class="table-card">
                <div style="padding: 24px; border-bottom: 1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="color: var(--primary-color);">Queue List - <?php echo htmlspecialchars($service_name); ?></h3>
                    <span style="font-size:12px; color: var(--text-muted); font-weight:600;"><i class="fas fa-calendar"></i> <?php echo $today_str; ?></span>
                </div>

                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Token</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Time/Slot</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($rows)): ?>
                                <?php foreach ($rows as $row): ?>
                                    <tr style="<?php echo ($active_token && $active_token['id'] == $row['id']) ? 'background-color: #f1f5f9; font-weight: 600;' : ''; ?>">
                                        <td style="font-family: monospace; font-size:15px; font-weight: 700; color: var(--primary-light);">
                                            <?php echo htmlspecialchars($row['token_number']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['citizen_name']); ?></td>
                                        <td>
                                            <span style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: <?php echo ($row['type'] === 'online') ? 'var(--primary-light)' : '#d97706'; ?>;">
                                                <?php echo htmlspecialchars($row['type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($row['type'] === 'online' && !empty($row['time_slot'])) {
                                                echo date('h:i A', strtotime($row['time_slot']));
                                            } else {
                                                echo date('h:i A', strtotime($row['created_at']));
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $st = $row['status'];
                                            $badge = 'badge-pending';
                                            if ($st === 'called') $badge = 'badge-calling';
                                            elseif ($st === 'completed') $badge = 'badge-completed';
                                            elseif ($st === 'absent') $badge = 'badge-absent';
                                            elseif ($st === 'skipped') $badge = 'badge-cancelled';
                                            ?>
                                            <span class="badge <?php echo $badge; ?>"><?php echo ucfirst($st); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                        No customer registered in today's queue yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Column: Active Serving Area -->
            <div class="active-serve-card">
                <h3 style="color: var(--primary-color); border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">Active Serving</h3>
                
                <?php if ($active_token): ?>
                    <div style="font-size: 12px; color: var(--text-muted); text-transform:uppercase; margin-top: 15px;">Now Serving Counter</div>
                    <div class="active-token-num"><?php echo htmlspecialchars($active_token['token_number']); ?></div>
                    <h4 style="color: var(--text-color); margin-bottom: 5px;"><?php echo htmlspecialchars($active_token['citizen_name']); ?></h4>
                    <span style="font-size: 12px; color: var(--text-muted); font-weight:600; text-transform:uppercase;">
                        Type: <?php echo htmlspecialchars($active_token['type']); ?>
                    </span>
                    
                    <form action="staff_dashboard.php" method="POST" id="actionForm">
                        <input type="hidden" name="action" id="action_input">
                        <div class="action-row">
                            <button type="button" onclick="submitAction('mark_completed')" class="btn btn-primary" style="background-color: var(--secondary-light); flex:1; min-width: 120px;">
                                <i class="fas fa-check-circle"></i> Complete
                            </button>
                            <button type="button" onclick="submitAction('mark_absent')" class="btn btn-primary" style="background-color: #ea4335; flex:1; min-width: 120px;">
                                <i class="fas fa-user-times"></i> Absent
                            </button>
                        </div>
                        <div class="action-row" style="margin-top:10px;">
                            <button type="button" onclick="submitAction('recall')" class="btn btn-secondary" style="flex:1; border: 1px solid var(--border-color);">
                                <i class="fas fa-bullhorn"></i> Recall
                            </button>
                            <button type="button" onclick="submitAction('skip')" class="btn btn-secondary" style="flex:1; border: 1px solid var(--border-color); color:#ea4335;">
                                <i class="fas fa-chevron-right"></i> Skip
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="active-token-num none">No Active Token</div>
                    <p style="color: var(--text-muted); font-size:14px; margin-bottom: 25px;">Ready to serve the next customer waiting in the queue.</p>
                    
                    <form action="staff_dashboard.php" method="POST">
                        <input type="hidden" name="action" value="call_next">
                        <button type="submit" class="btn btn-primary" style="width:100%; height: 50px; font-size:16px;">
                            <i class="fas fa-bullhorn"></i> Call Next Customer
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
function submitAction(act) {
    if (confirm("Are you sure you want to perform this action?")) {
        document.getElementById('action_input').value = act;
        document.getElementById('actionForm').submit();
    }
}
</script>
</body>
</html>
