<?php
session_start();
require_once 'db.php';

// Access Control - Anyone logged in can view this page (Citizen, Admin, Staff)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$page_title = "Live Queue Status";

// Fetch all active services
$services_q = mysqli_query($conn, "SELECT id, name, prefix, duration_minutes, icon FROM services WHERE status = 'active' ORDER BY id ASC");
$queue_status = [];

while ($srv = mysqli_fetch_assoc($services_q)) {
    $service_id = $srv['id'];
    $today_str = date('Y-m-d');

    // 1. Get current serving token
    // We look for status 'serving' first, then 'called' as fallback
    $serving_q = $conn->prepare("SELECT token_number FROM queue_tokens WHERE service_id = ? AND token_date = ? AND status IN ('serving', 'called') ORDER BY called_at DESC LIMIT 1");
    $serving_q->bind_param("is", $service_id, $today_str);
    $serving_q->execute();
    $serving_res = $serving_q->get_result()->fetch_assoc();
    $current_serving = $serving_res['token_number'] ?? 'None';
    $serving_q->close();

    // 2. Count waiting customers
    $waiting_q = $conn->prepare("SELECT COUNT(*) as waiting_count FROM queue_tokens WHERE service_id = ? AND token_date = ? AND status = 'waiting'");
    $waiting_q->bind_param("is", $service_id, $today_str);
    $waiting_q->execute();
    $waiting_res = $waiting_q->get_result()->fetch_assoc();
    $waiting_count = intval($waiting_res['waiting_count'] ?? 0);
    $waiting_q->close();

    // 3. Calculate Average Serving Time (in minutes) from completed tokens
    $time_q = $conn->prepare("SELECT AVG(TIMESTAMPDIFF(MINUTE, called_at, completed_at)) as avg_time FROM queue_tokens WHERE service_id = ? AND status = 'completed' AND called_at IS NOT NULL AND completed_at IS NOT NULL");
    $time_q->bind_param("i", $service_id);
    $time_q->execute();
    $time_res = $time_q->get_result()->fetch_assoc();
    $avg_serving_time = floatval($time_res['avg_time'] ?? 0);
    $time_q->close();

    if ($avg_serving_time <= 0) {
        $avg_serving_time = $srv['duration_minutes']; // Fallback to service default
    }

    // Estimated waiting time for the next person in line
    $est_waiting_time = ceil($avg_serving_time * $waiting_count);

    $queue_status[] = [
        'name' => $srv['name'],
        'prefix' => $srv['prefix'],
        'icon' => $srv['icon'],
        'serving' => $current_serving,
        'waiting' => $waiting_count,
        'est_wait' => $est_waiting_time
    ];
}

// Check role for sidebar redirection
$sidebar_file = 'includes/citizen_sidebar.php';
if ($_SESSION['role'] === 'admin') {
    $sidebar_file = 'includes/admin_sidebar.php';
} elseif ($_SESSION['role'] === 'staff') {
    $sidebar_file = 'includes/staff_sidebar.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Queue - Divisional Secretariat</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .queue-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }
        .queue-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        .queue-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }
        .queue-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        .queue-body {
            text-align: center;
            padding: 15px 0;
            background-color: #f8fafc;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .serving-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .serving-token {
            font-size: 32px;
            font-weight: 800;
            color: var(--secondary-color);
            margin-top: 5px;
            font-family: monospace;
        }
        .serving-token.none {
            color: var(--text-muted);
            font-size: 24px;
            font-family: inherit;
        }
        .queue-stats-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
        }
        .queue-stat-item {
            text-align: center;
            flex: 1;
        }
        .queue-stat-item:first-child {
            border-right: 1px solid var(--border-color);
        }
        .queue-stat-val {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-color);
            margin-top: 4px;
        }
        .live-indicator {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 600;
            color: #ea4335;
        }
        .dot {
            width: 8px;
            height: 8px;
            background-color: #ea4335;
            border-radius: 50%;
            display: inline-block;
            animation: blink 1.2s infinite;
        }
        @keyframes blink {
            0% { opacity: 0.2; }
            50% { opacity: 1; }
            100% { opacity: 0.2; }
        }
    </style>
</head>
<body>

<div class="app-container">
    <!-- Sidebar Include dynamically based on role -->
    <?php include $sidebar_file; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header Bar Include -->
        <?php include 'includes/header.php'; ?>

        <div style="margin-bottom: 25px; display:flex; justify-content:space-between; align-items:center;">
            <p style="color: var(--text-muted);">Real-time status of divisional services queue for today.</p>
            <button onclick="window.location.reload();" class="btn btn-secondary" style="padding: 10px 18px;">
                <i class="fas fa-sync-alt"></i> Refresh Queue
            </button>
        </div>

        <div class="queue-grid">
            <?php foreach ($queue_status as $q): ?>
                <div class="queue-card">
                    <div class="live-indicator">
                        <span class="dot"></span> LIVE
                    </div>
                    
                    <div class="queue-header">
                        <div class="service-icon-box" style="margin: 0;">
                            <i class="fas <?php echo htmlspecialchars($q['icon']); ?>"></i>
                        </div>
                        <div>
                            <h4 style="margin: 0; font-size:15px;"><?php echo htmlspecialchars($q['name']); ?></h4>
                            <span style="font-size: 11px; color:var(--text-muted); font-weight:600; letter-spacing:0.5px;">
                                PREFIX: <?php echo htmlspecialchars($q['prefix']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="queue-body">
                        <div class="serving-label">Currently Serving</div>
                        <?php if ($q['serving'] === 'None'): ?>
                            <div class="serving-token none">None Active</div>
                        <?php else: ?>
                            <div class="serving-token"><?php echo htmlspecialchars($q['serving']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="queue-stats-row">
                        <div class="queue-stat-item">
                            <div style="color: var(--text-muted);">Waiting</div>
                            <div class="queue-stat-val"><?php echo $q['waiting']; ?></div>
                        </div>
                        <div class="queue-stat-item">
                            <div style="color: var(--text-muted);">Est. Wait Time</div>
                            <div class="queue-stat-val" style="color: <?php echo ($q['est_wait'] > 30) ? '#d97706' : 'var(--text-color)'; ?>;">
                                <?php echo $q['est_wait']; ?> mins
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<script>
// Auto-refresh page every 15 seconds to simulate real-time updates
setTimeout(function(){
   window.location.reload();
}, 15000);
</script>
</body>
</html>
