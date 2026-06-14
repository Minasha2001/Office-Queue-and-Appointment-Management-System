<?php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$page_title = "Office Reports Module";

// Get filters
$report_type = $_GET['report_type'] ?? 'daily'; // daily, monthly, service, noshow
$filter_date = $_GET['filter_date'] ?? date('Y-m-d');
$filter_month = $_GET['filter_month'] ?? date('Y-m');
$filter_service_id = intval($_GET['filter_service_id'] ?? 0);

// Build SQL Query according to filters
$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($report_type === 'daily') {
    $where_clauses[] = "q.token_date = ?";
    $params[] = $filter_date;
    $types .= "s";
} elseif ($report_type === 'monthly') {
    $where_clauses[] = "DATE_FORMAT(q.token_date, '%Y-%m') = ?";
    $params[] = $filter_month;
    $types .= "s";
} elseif ($report_type === 'service') {
    if ($filter_service_id > 0) {
        $where_clauses[] = "q.service_id = ?";
        $params[] = $filter_service_id;
        $types .= "i";
    }
    // Also apply date or month filter to make service report specific
    $where_clauses[] = "q.token_date = ?";
    $params[] = $filter_date;
    $types .= "s";
} elseif ($report_type === 'noshow') {
    $where_clauses[] = "q.status = 'absent'";
    // Also filter by date
    $where_clauses[] = "q.token_date = ?";
    $params[] = $filter_date;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

$query = "SELECT q.id, q.token_number, q.citizen_name, q.phone, q.token_date, q.status, q.type, q.called_at, q.completed_at, s.name as service_name 
          FROM queue_tokens q 
          JOIN services s ON q.service_id = s.id 
          WHERE $where_sql 
          ORDER BY q.token_date DESC, q.token_number ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

// Calculate Summary Statistics
$total_issued = count($rows);
$total_completed = 0;
$total_absent = 0;
$total_serving_time = 0;
$serving_time_count = 0;

foreach ($rows as $row) {
    if ($row['status'] === 'completed') {
        $total_completed++;
        if (!empty($row['called_at']) && !empty($row['completed_at'])) {
            $start = new DateTime($row['called_at']);
            $end = new DateTime($row['completed_at']);
            $diff_minutes = abs($end->getTimestamp() - $start->getTimestamp()) / 60;
            $total_serving_time += $diff_minutes;
            $serving_time_count++;
        }
    } elseif ($row['status'] === 'absent') {
        $total_absent++;
    }
}

$avg_serving_time = ($serving_time_count > 0) ? round($total_serving_time / $serving_time_count, 1) : 0;

// HANDLE CSV EXPORT
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Clear output buffer
    ob_end_clean();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=office_report_' . $report_type . '_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Add Column Headers
    fputcsv($output, ['Token Number', 'Customer Name', 'Phone', 'Service', 'Date', 'Type', 'Status', 'Called Time', 'Completed Time']);
    
    // Add Rows
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['token_number'],
            $row['citizen_name'],
            $row['phone'],
            $row['service_name'],
            $row['token_date'],
            ucfirst($row['type']),
            ucfirst($row['status']),
            !empty($row['called_at']) ? date('h:i A', strtotime($row['called_at'])) : 'N/A',
            !empty($row['completed_at']) ? date('h:i A', strtotime($row['completed_at'])) : 'N/A'
        ]);
    }
    
    fclose($output);
    exit();
}

// Fetch active services for selector
$srv_res = mysqli_query($conn, "SELECT id, name FROM services WHERE status = 'active' ORDER BY id ASC");
$services_list = [];
while ($row = mysqli_fetch_assoc($srv_res)) {
    $services_list[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Dashboard - Divisional Secretariat</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            align-items: flex-end;
            width: 100%;
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

        <div style="margin-bottom: 25px;">
            <p style="color: var(--text-muted);">Generate service stats, daily metrics, monthly line load factors, and track no-show citizens.</p>
        </div>

        <!-- Filter Panel -->
        <div class="form-card" style="padding: 20px; margin-bottom: 30px; box-shadow: var(--shadow);">
            <form action="reports.php" method="GET" id="reportFilterForm">
                <div class="filter-grid">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="report_type">Report Type</label>
                        <select class="form-control" name="report_type" id="report_type" onchange="toggleFilterFields(this.value)">
                            <option value="daily" <?php echo ($report_type === 'daily') ? 'selected' : ''; ?>>Daily Report</option>
                            <option value="monthly" <?php echo ($report_type === 'monthly') ? 'selected' : ''; ?>>Monthly Report</option>
                            <option value="service" <?php echo ($report_type === 'service') ? 'selected' : ''; ?>>Service-wise Report</option>
                            <option value="noshow" <?php echo ($report_type === 'noshow') ? 'selected' : ''; ?>>No-Show Report</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;" id="dateField">
                        <label for="filter_date">Select Date</label>
                        <input type="date" class="form-control" name="filter_date" id="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;" id="monthField">
                        <label for="filter_month">Select Month</label>
                        <input type="month" class="form-control" name="filter_month" id="filter_month" value="<?php echo htmlspecialchars($filter_month); ?>">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;" id="serviceField">
                        <label for="filter_service_id">Select Service</label>
                        <select class="form-control" name="filter_service_id" id="filter_service_id">
                            <option value="0">All Services</option>
                            <?php foreach ($services_list as $srv): ?>
                                <option value="<?php echo $srv['id']; ?>" <?php echo ($filter_service_id === $srv['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($srv['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display:flex; gap:10px;">
                        <button type="submit" class="btn btn-primary" style="flex:1; height: 45px;">
                            <i class="fas fa-search"></i> Query
                        </button>
                        <button type="button" onclick="exportCSV()" class="btn btn-secondary" style="flex:1; height: 45px; border:1px solid var(--border-color); color:var(--secondary-color);">
                            <i class="fas fa-file-excel"></i> Export
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Summary Stats Grid -->
        <div class="stats-grid" style="margin-bottom: 30px;">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fas fa-ticket-alt"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_issued; ?></h3>
                    <p>Total Tokens Issued</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_completed; ?></h3>
                    <p>Served Customers</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-red"><i class="fas fa-user-times"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_absent; ?></h3>
                    <p>No-show / Absent</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-yellow"><i class="fas fa-stopwatch"></i></div>
                <div class="stat-info">
                    <h3><?php echo $avg_serving_time; ?> m</h3>
                    <p>Avg Servicing Time</p>
                </div>
            </div>
        </div>

        <!-- Report Results Table -->
        <div class="table-card">
            <div style="padding: 24px; border-bottom: 1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                <h3 style="color: var(--primary-color); text-transform: capitalize;"><?php echo $report_type; ?> Report Entries</h3>
                <span style="font-size:12px; font-weight:600; color:var(--text-muted);"><i class="fas fa-list"></i> Found: <?php echo count($rows); ?> rows</span>
            </div>

            <div class="table-responsive">
                <table class="custom-table" style="font-size: 13px;">
                    <thead>
                        <tr>
                            <th>Token</th>
                            <th>Customer Name</th>
                            <th>Service Line</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Called Time</th>
                            <th>Completed Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rows)): ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td style="font-family: monospace; font-size:14px; font-weight: 700; color: var(--primary-light);">
                                        <?php echo htmlspecialchars($row['token_number']); ?>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($row['citizen_name']); ?></div>
                                        <div style="font-size:10px; color:var(--text-muted);"><?php echo htmlspecialchars($row['phone']); ?></div>
                                    </td>
                                    <td style="font-weight:500;"><?php echo htmlspecialchars($row['service_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['token_date']); ?></td>
                                    <td>
                                        <span style="font-size: 10px; font-weight: 600; text-transform: uppercase; color: <?php echo ($row['type'] === 'online') ? 'var(--primary-light)' : '#d97706'; ?>;">
                                            <?php echo htmlspecialchars($row['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $st = $row['status'];
                                        $badge = 'badge-pending';
                                        if ($st === 'completed') $badge = 'badge-completed';
                                        elseif ($st === 'absent') $badge = 'badge-absent';
                                        elseif ($st === 'skipped') $badge = 'badge-cancelled';
                                        ?>
                                        <span class="badge <?php echo $badge; ?>" style="font-size: 11px; padding: 4px 8px;"><?php echo ucfirst($st); ?></span>
                                    </td>
                                    <td><?php echo !empty($row['called_at']) ? date('h:i A', strtotime($row['called_at'])) : 'N/A'; ?></td>
                                    <td><?php echo !empty($row['completed_at']) ? date('h:i A', strtotime($row['completed_at'])) : 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                    No records found for the selected query.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
function toggleFilterFields(type) {
    var dateField = document.getElementById('dateField');
    var monthField = document.getElementById('monthField');
    var serviceField = document.getElementById('serviceField');
    
    // Default hiding
    dateField.style.display = 'none';
    monthField.style.display = 'none';
    serviceField.style.display = 'none';
    
    if (type === 'daily') {
        dateField.style.display = 'block';
    } else if (type === 'monthly') {
        monthField.style.display = 'block';
    } else if (type === 'service') {
        dateField.style.display = 'block';
        serviceField.style.display = 'block';
    } else if (type === 'noshow') {
        dateField.style.display = 'block';
    }
}

function exportCSV() {
    var form = document.getElementById('reportFilterForm');
    var actionUrl = form.action;
    
    // Create new URL with export=csv query parameter
    var formData = new FormData(form);
    var queryParams = [];
    formData.forEach((value, key) => {
        queryParams.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
    });
    queryParams.push('export=csv');
    
    window.location.href = 'reports.php?' + queryParams.join('&');
}

// Initial display setup
document.addEventListener("DOMContentLoaded", function() {
    toggleFilterFields(document.getElementById('report_type').value);
});
</script>
</body>
</html>
