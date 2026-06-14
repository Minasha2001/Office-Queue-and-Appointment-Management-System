<?php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$page_title = "Queue Control Panel";
$success_msg = "";
$error_msg = "";
$print_token_id = 0;

// Fetch active services for forms
$services_res = mysqli_query($conn, "SELECT id, name, prefix FROM services WHERE status = 'active' ORDER BY id ASC");
$services = [];
while ($row = mysqli_fetch_assoc($services_res)) {
    $services[$row['id']] = $row;
}

// Get selected service to display queue
$selected_service_id = intval($_GET['service_id'] ?? 0);
if ($selected_service_id === 0 && !empty($services)) {
    $selected_service_id = array_key_first($services);
}

$today_str = date('Y-m-d');

// 1. HANDLE ADD WALK-IN CUSTOMER
if (isset($_POST['add_walkin'])) {
    $walkin_service_id = intval($_POST['service_id'] ?? 0);
    $citizen_name = trim($_POST['citizen_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($walkin_service_id) || empty($citizen_name)) {
        $error_msg = "Please enter the customer name and select a service.";
    } elseif (!isset($services[$walkin_service_id])) {
        $error_msg = "Invalid service selected.";
    } else {
        $conn->begin_transaction();
        try {
            // Count total tokens created for this service on this date to get next sequence
            $token_q = $conn->prepare("SELECT COUNT(*) as total_tokens FROM queue_tokens WHERE service_id = ? AND token_date = ?");
            $token_q->bind_param("is", $walkin_service_id, $today_str);
            $token_q->execute();
            $token_res = $token_q->get_result()->fetch_assoc();
            $seq_num = intval($token_res['total_tokens'] ?? 0) + 1;
            $token_q->close();

            $prefix = $services[$walkin_service_id]['prefix'];
            $token_number = $prefix . '-' . str_pad($seq_num, 3, '0', STR_PAD_LEFT);

            // Insert Queue Token
            $inst_tkn = $conn->prepare("INSERT INTO queue_tokens (appointment_id, service_id, citizen_name, phone, token_number, token_date, status, type) VALUES (NULL, ?, ?, ?, ?, ?, 'waiting', 'walk-in')");
            $inst_tkn->bind_param("issss", $walkin_service_id, $citizen_name, $phone, $token_number, $today_str);
            $inst_tkn->execute();
            $new_token_id = $conn->insert_id;
            $inst_tkn->close();

            // Log Activity
            $log_act = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Add Walk-in', ?)");
            $details = "Registered walk-in customer: $citizen_name, Token: $token_number";
            $log_act->bind_param("is", $_SESSION['user_id'], $details);
            $log_act->execute();
            $log_act->close();

            $conn->commit();
            $print_token_id = $new_token_id;
            $success_msg = "Walk-in customer registered! Token: <strong>$token_number</strong>. <a href='print_token.php?token_id=$new_token_id' target='_blank' class='btn btn-secondary' style='padding:4px 8px; font-size:11px; margin-left:10px;'><i class='fas fa-print'></i> Print Token</a>";
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Registration failed: " . $e->getMessage();
        }
    }
}

// 2. HANDLE QUEUE CONTROLS (Call Next, Complete, Absent, Recall, Skip)
if (isset($_POST['queue_action'])) {
    $token_id = intval($_POST['token_id']);
    $action = $_POST['action_name'] ?? '';

    // Check if token exists
    $chk = $conn->prepare("SELECT id, token_number, appointment_id, status FROM queue_tokens WHERE id = ? AND token_date = ?");
    $chk->bind_param("is", $token_id, $today_str);
    $chk->execute();
    $token_data = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($token_data) {
        $token_number = $token_data['token_number'];
        $appt_id = $token_data['appointment_id'];

        $conn->begin_transaction();
        try {
            if ($action === 'call') {
                // Find if there is already a called token for this service that is not closed
                // Note: Admin can call multiple, but typically only one is serving.
                // We'll set status to 'called', called_at = NOW(), and serving_staff_id = null (served by admin at counter)
                $up = $conn->prepare("UPDATE queue_tokens SET status = 'called', called_at = NOW() WHERE id = ?");
                $up->bind_param("i", $token_id);
                $up->execute();
                $up->close();
                $success_msg = "Called token $token_number.";

            } elseif ($action === 'complete') {
                $up = $conn->prepare("UPDATE queue_tokens SET status = 'completed', completed_at = NOW() WHERE id = ?");
                $up->bind_param("i", $token_id);
                $up->execute();
                $up->close();

                if ($appt_id) {
                    $up_ap = $conn->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?");
                    $up_ap->bind_param("i", $appt_id);
                    $up_ap->execute();
                    $up_ap->close();
                }
                $success_msg = "Token $token_number marked as Completed.";

            } elseif ($action === 'absent') {
                $up = $conn->prepare("UPDATE queue_tokens SET status = 'absent', completed_at = NOW() WHERE id = ?");
                $up->bind_param("i", $token_id);
                $up->execute();
                $up->close();

                if ($appt_id) {
                    $up_ap = $conn->prepare("UPDATE appointments SET status = 'absent' WHERE id = ?");
                    $up_ap->bind_param("i", $appt_id);
                    $up_ap->execute();
                    $up_ap->close();
                }
                $success_msg = "Token $token_number marked as Absent.";

            } elseif ($action === 'recall') {
                $up = $conn->prepare("UPDATE queue_tokens SET called_at = NOW() WHERE id = ?");
                $up->bind_param("i", $token_id);
                $up->execute();
                $up->close();
                $success_msg = "Recalled token $token_number.";

            } elseif ($action === 'skip') {
                $up = $conn->prepare("UPDATE queue_tokens SET status = 'skipped', completed_at = NOW() WHERE id = ?");
                $up->bind_param("i", $token_id);
                $up->execute();
                $up->close();
                $success_msg = "Skipped token $token_number.";
            }

            // Log activity
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Admin Queue Action', ?)");
            $details = "Admin processed action: $action on token $token_number";
            $log->bind_param("is", $_SESSION['user_id'], $details);
            $log->execute();
            $log->close();

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Action failed: " . $e->getMessage();
        }
    } else {
        $error_msg = "Token not found or not in today's queue.";
    }
}

// Fetch today's queue for selected service
$queue_res = [];
if ($selected_service_id > 0) {
    $q_stmt = $conn->prepare("SELECT q.id, q.token_number, q.citizen_name, q.phone, q.type, q.status, q.created_at, q.called_at, a.time_slot 
                             FROM queue_tokens q 
                             LEFT JOIN appointments a ON q.appointment_id = a.id 
                             WHERE q.service_id = ? AND q.token_date = ? 
                             ORDER BY q.token_number ASC");
    $q_stmt->bind_param("is", $selected_service_id, $today_str);
    $q_stmt->execute();
    $queue_res = $q_stmt->get_result();
    $q_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Control - Divisional Secretariat</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .queue-control-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 30px;
        }
        @media (max-width: 1024px) {
            .queue-control-grid {
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
                <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <div class="queue-control-grid">
            <!-- Left: Active Queue Processor -->
            <div>
                <!-- Service Selector -->
                <div class="filter-bar" style="padding: 15px 24px; margin-bottom: 20px; display:flex; align-items:center; justify-content:space-between;">
                    <form action="admin_queue.php" method="GET" style="display:flex; align-items:center; gap: 15px;">
                        <label for="srv_sel" style="font-weight: 600; font-size:14px; color:var(--text-color);">Select Queue Line</label>
                        <select name="service_id" id="srv_sel" class="form-control" onchange="this.form.submit()" style="width: 250px;">
                            <?php foreach ($services as $id => $s): ?>
                                <option value="<?php echo $id; ?>" <?php echo ($selected_service_id === $id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['prefix']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <span style="font-size:12px; color: var(--text-muted); font-weight:600;"><i class="fas fa-calendar-alt"></i> Queue Date: <?php echo $today_str; ?></span>
                </div>

                <!-- Live Queue List -->
                <div class="table-card">
                    <div style="padding: 20px; border-bottom: 1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="color: var(--primary-color);">Queue List - <?php echo htmlspecialchars($services[$selected_service_id]['name'] ?? 'Select Service'); ?></h3>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="custom-table" style="font-size:13px;">
                            <thead>
                                <tr>
                                    <th>Token</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Time/Slot</th>
                                    <th>Status</th>
                                    <th>Action Controls</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($queue_res) && $queue_res->num_rows > 0): ?>
                                    <?php while ($q = $queue_res->fetch_assoc()): ?>
                                        <tr>
                                            <td style="font-family: monospace; font-size: 15px; font-weight: 700; color: var(--primary-light);">
                                                <?php echo htmlspecialchars($q['token_number']); ?>
                                            </td>
                                            <td>
                                                <div style="font-weight:600;"><?php echo htmlspecialchars($q['citizen_name']); ?></div>
                                                <?php if(!empty($q['phone'])): ?><div style="font-size:10px; color:var(--text-muted);"><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($q['phone']); ?></div><?php endif; ?>
                                            </td>
                                            <td>
                                                <span style="font-size: 10px; font-weight: 600; text-transform: uppercase; color: <?php echo ($q['type'] === 'online') ? 'var(--primary-light)' : '#d97706'; ?>;">
                                                    <?php echo htmlspecialchars($q['type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($q['type'] === 'online' && !empty($q['time_slot'])) {
                                                    echo date('h:i A', strtotime($q['time_slot']));
                                                } else {
                                                    echo date('h:i A', strtotime($q['created_at']));
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $st = $q['status'];
                                                $badge = 'badge-pending';
                                                if ($st === 'called') $badge = 'badge-calling';
                                                elseif ($st === 'completed') $badge = 'badge-completed';
                                                elseif ($st === 'absent') $badge = 'badge-absent';
                                                elseif ($st === 'skipped') $badge = 'badge-cancelled';
                                                ?>
                                                <span class="badge <?php echo $badge; ?>" style="font-size:11px; padding:4px 8px;"><?php echo ucfirst($st); ?></span>
                                            </td>
                                            <td>
                                                <form action="admin_queue.php?service_id=<?php echo $selected_service_id; ?>" method="POST" style="display:flex; gap: 5px;">
                                                    <input type="hidden" name="token_id" value="<?php echo $q['id']; ?>">
                                                    <input type="hidden" name="queue_action" value="1">
                                                    
                                                    <?php if ($st === 'waiting'): ?>
                                                        <button type="submit" name="action_name" value="call" class="btn btn-primary" style="padding: 5px 10px; font-size:11px; background-color: var(--primary-light); border:none;">
                                                            <i class="fas fa-bullhorn"></i> Call
                                                        </button>
                                                    <?php elseif ($st === 'called'): ?>
                                                        <button type="submit" name="action_name" value="complete" class="btn btn-primary" style="padding: 5px 10px; font-size:11px; background-color: var(--secondary-light); border:none;">
                                                            <i class="fas fa-check"></i> Complete
                                                        </button>
                                                        <button type="submit" name="action_name" value="absent" class="btn btn-primary" style="padding: 5px 10px; font-size:11px; background-color: #ea4335; border:none;">
                                                            <i class="fas fa-user-times"></i> Absent
                                                        </button>
                                                        <button type="submit" name="action_name" value="recall" class="btn btn-secondary" style="padding: 5px 10px; font-size:11px; border:1px solid var(--border-color);" title="Recall again">
                                                            <i class="fas fa-redo"></i> Recall
                                                        </button>
                                                        <button type="submit" name="action_name" value="skip" class="btn btn-secondary" style="padding: 5px 10px; font-size:11px; border:1px solid var(--border-color); color:#ea4335;" title="Skip/Mute">
                                                            <i class="fas fa-chevron-right"></i> Skip
                                                        </button>
                                                    <?php else: ?>
                                                        <span style="color:var(--text-muted); font-style:italic; font-size:12px;">Archived</span>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 25px;">
                                            No customers in today's queue for this service.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right: Add Walk-in Customer Panel -->
            <div>
                <div class="form-card">
                    <h3 style="color: var(--primary-color); border-bottom:1px solid var(--border-color); padding-bottom:15px; margin-bottom:20px;">
                        <i class="fas fa-user-plus"></i> Walk-in Registration
                    </h3>
                    
                    <form action="admin_queue.php?service_id=<?php echo $selected_service_id; ?>" method="POST">
                        <div class="form-group">
                            <label for="form_service">Select Service</label>
                            <select name="service_id" id="form_service" class="form-control" required>
                                <option value="">Select Service Line</option>
                                <?php foreach ($services as $id => $s): ?>
                                    <option value="<?php echo $id; ?>" <?php echo ($selected_service_id === $id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="citizen_name">Customer Full Name</label>
                            <input type="text" class="form-control" name="citizen_name" id="citizen_name" placeholder="Enter full name" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number (Optional)</label>
                            <input type="text" class="form-control" name="phone" id="phone" placeholder="e.g. +94771234567">
                        </div>

                        <button type="submit" name="add_walkin" class="btn btn-primary" style="width: 100%; height:45px; margin-top:10px;">
                            Generate Walk-in Token
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>
