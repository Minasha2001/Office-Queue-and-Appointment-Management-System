<?php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$token_id = intval($_GET['token_id'] ?? 0);
$appointment_id = intval($_GET['appointment_id'] ?? 0);
$token = null;

if ($token_id > 0) {
    // Fetch by queue token ID (walk-in or check-in)
    $stmt = $conn->prepare("SELECT q.token_number, q.citizen_name, q.token_date, q.type, q.created_at, s.name as service_name, s.prefix, s.duration_minutes, s.id as service_id, a.time_slot 
                            FROM queue_tokens q 
                            JOIN services s ON q.service_id = s.id 
                            LEFT JOIN appointments a ON q.appointment_id = a.id
                            WHERE q.id = ?");
    $stmt->bind_param("i", $token_id);
    $stmt->execute();
    $token = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} elseif ($appointment_id > 0) {
    // Fetch by appointment ID (citizen booking)
    $stmt = $conn->prepare("SELECT a.token_number, u.full_name as citizen_name, a.appointment_date as token_date, 'online' as type, a.created_at, s.name as service_name, s.prefix, s.duration_minutes, s.id as service_id, a.time_slot 
                            FROM appointments a 
                            JOIN users u ON a.citizen_id = u.id
                            JOIN services s ON a.service_id = s.id 
                            WHERE a.id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $token = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$token) {
    echo "<div style='padding:50px; text-align:center; font-family:sans-serif;'><h2>Error</h2><p>Token details not found.</p><a href='javascript:window.close();'>Close Window</a></div>";
    exit();
}

// Calculate Waiting Stats
$service_id = $token['service_id'];
$token_date = $token['token_date'];
$token_number = $token['token_number'];

// 1. Waiting customers ahead of this token
$ahead_q = $conn->prepare("SELECT COUNT(*) as ahead_count FROM queue_tokens 
                           WHERE service_id = ? AND token_date = ? AND status = 'waiting' AND token_number < ?");
$ahead_q->bind_param("iss", $service_id, $token_date, $token_number);
$ahead_q->execute();
$ahead_res = $ahead_q->get_result()->fetch_assoc();
$waiting_ahead = intval($ahead_res['ahead_count'] ?? 0);
$ahead_q->close();

// 2. Avg serving time fallback
$time_q = $conn->prepare("SELECT AVG(TIMESTAMPDIFF(MINUTE, called_at, completed_at)) as avg_time FROM queue_tokens WHERE service_id = ? AND status = 'completed' AND called_at IS NOT NULL AND completed_at IS NOT NULL");
$time_q->bind_param("i", $service_id);
$time_q->execute();
$time_res = $time_q->get_result()->fetch_assoc();
$avg_serving_time = floatval($time_res['avg_time'] ?? 0);
$time_q->close();

if ($avg_serving_time <= 0) {
    $avg_serving_time = $token['duration_minutes']; // Fallback
}

$est_waiting_time = ceil($avg_serving_time * ($waiting_ahead + 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Token Ticket - <?php echo htmlspecialchars($token_number); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .ticket {
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 380px;
            padding: 30px;
            text-align: center;
            border: 1px dashed #d1d5db;
            position: relative;
        }
        .ticket::before, .ticket::after {
            content: '';
            position: absolute;
            width: 24px;
            height: 24px;
            background-color: #f3f4f6;
            border-radius: 50%;
            top: 55%;
        }
        .ticket::before { left: -12px; }
        .ticket::after { right: -12px; }

        .logo {
            width: 65px;
            margin-bottom: 15px;
        }
        h2 {
            margin: 0;
            font-size: 16px;
            color: #0d2f80;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        h3 {
            margin: 5px 0 20px;
            font-size: 11px;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 15px;
        }
        .service-name {
            font-size: 16px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
        }
        .token-val {
            font-family: monospace;
            font-size: 48px;
            font-weight: 800;
            color: #1560ff;
            margin: 15px 0;
            letter-spacing: 2px;
        }
        .meta-info {
            background-color: #f9fafb;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: left;
            font-size: 13px;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            color: #4b5563;
        }
        .meta-row:last-child {
            margin-bottom: 0;
        }
        .meta-row strong {
            color: #1f2937;
        }
        .guidelines {
            font-size: 11px;
            color: #9ca3af;
            line-height: 1.4;
            margin-bottom: 25px;
            border-top: 1px dashed #e5e7eb;
            padding-top: 15px;
        }
        .btn {
            background-color: #0b8043;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: 0.2s;
        }
        .btn:hover {
            background-color: #0f9d58;
        }
        .btn-close {
            background-color: #6b7280;
            margin-top: 10px;
        }
        .btn-close:hover {
            background-color: #4b5563;
        }
        
        @media print {
            body {
                background-color: white;
                padding: 0;
                display: block;
            }
            .ticket {
                box-shadow: none;
                border: none;
                width: 100%;
                padding: 0;
            }
            .ticket::before, .ticket::after {
                display: none;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="ticket">
    <img src="image/gov.png" class="logo" alt="Government Emblem">
    <h2>Divisional Secretariat Office</h2>
    <h3>Queue & Appointment Management System</h3>
    
    <div class="service-name"><?php echo htmlspecialchars($token['service_name']); ?></div>
    <div class="token-val"><?php echo htmlspecialchars($token_number); ?></div>
    
    <div class="meta-info">
        <div class="meta-row">
            <span>Customer Name:</span>
            <strong><?php echo htmlspecialchars($token['citizen_name']); ?></strong>
        </div>
        <div class="meta-row">
            <span>Date:</span>
            <strong><?php echo htmlspecialchars($token_date); ?></strong>
        </div>
        <div class="meta-row">
            <span>Ticket Type:</span>
            <strong style="text-transform: uppercase;"><?php echo htmlspecialchars($token['type']); ?></strong>
        </div>
        
        <?php if ($token['type'] === 'online' && !empty($token['time_slot'])): ?>
            <div class="meta-row">
                <span>Appointment Slot:</span>
                <strong><?php echo date('h:i A', strtotime($token['time_slot'])); ?></strong>
            </div>
        <?php else: ?>
            <div class="meta-row">
                <span>Registered Time:</span>
                <strong><?php echo date('h:i A', strtotime($token['created_at'])); ?></strong>
            </div>
        <?php endif; ?>
        
        <div class="meta-row" style="border-top: 1px solid #e5e7eb; padding-top: 8px; margin-top: 8px;">
            <span>Customers Ahead:</span>
            <strong><?php echo $waiting_ahead; ?></strong>
        </div>
        <div class="meta-row">
            <span>Est. Waiting Time:</span>
            <strong>~<?php echo $est_waiting_time; ?> mins</strong>
        </div>
    </div>
    
    <div class="guidelines">
        Please present this ticket at the counter when your token number is called. Bring all required original documents for verification.
    </div>
    
    <button onclick="window.print()" class="btn no-print">
        <i class="fas fa-print"></i> Print Ticket
    </button>
    <button onclick="window.close()" class="btn btn-close no-print">
        Close Window
    </button>
</div>

</body>
</html>
