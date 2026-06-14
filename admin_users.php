<?php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$page_title = "User Account Management";
$success_msg = "";
$error_msg = "";

// Handle Delete User request
if (isset($_POST['delete_user'])) {
    $delete_user_id = intval($_POST['user_id']);
    
    // Prevent admin from deleting themselves
    if ($delete_user_id === intval($_SESSION['user_id'])) {
        $error_msg = "You cannot delete your own admin account.";
    } else {
        $del_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $del_stmt->bind_param("i", $delete_user_id);
        if ($del_stmt->execute()) {
            // Log Activity
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Delete User', ?)");
            $details = "Deleted user ID: " . $delete_user_id;
            $log->bind_param("is", $_SESSION['user_id'], $details);
            $log->execute();
            $log->close();

            $success_msg = "User account deleted successfully.";
        } else {
            $error_msg = "Error deleting user account: " . $conn->error;
        }
        $del_stmt->close();
    }
}

// Get filter and search values
$filter_role = $_GET['role'] ?? '';
$search_query = trim($_GET['search'] ?? '');

// Build Query
$query = "SELECT id, username, full_name, email, phone, role, created_at FROM users WHERE 1=1";
$params = [];
$types = "";

if (!empty($filter_role)) {
    $query .= " AND role = ?";
    $params[] = $filter_role;
    $types .= "s";
}

if (!empty($search_query)) {
    $query .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $like_search = "%" . $search_query . "%";
    $params[] = $like_search;
    $params[] = $like_search;
    $params[] = $like_search;
    $params[] = $like_search;
    $types .= "ssss";
}

$query .= " ORDER BY id DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Divisional Secretariat</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .filter-bar {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            box-shadow: var(--shadow);
        }
        .filter-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
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

        <!-- Search and Filter Bar -->
        <div class="filter-bar">
            <form action="admin_users.php" method="GET" class="filter-controls" style="width: 100%;">
                <div class="form-group" style="margin-bottom: 0; flex-grow: 1; max-width: 400px;">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, username, email, phone..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 0; min-width: 150px;">
                    <select name="role" class="form-control">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo ($filter_role === 'admin') ? 'selected' : ''; ?>>Admin</option>
                        <option value="staff" <?php echo ($filter_role === 'staff') ? 'selected' : ''; ?>>Staff (Officer)</option>
                        <option value="citizen" <?php echo ($filter_role === 'citizen') ? 'selected' : ''; ?>>Citizen</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary" style="padding: 11px 24px;">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <a href="admin_users.php" class="btn btn-secondary" style="padding: 11px 24px; border: 1px solid var(--border-color);">Reset</a>
            </form>
        </div>

        <!-- Users Table -->
        <div class="table-card">
            <div style="padding: 24px; border-bottom: 1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                <h3 style="color: var(--primary-color);">System User Accounts</h3>
                <span style="font-weight:600; font-size:14px; color:var(--text-muted);">Total: <?php echo $users->num_rows; ?> accounts</span>
            </div>

            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>User Details</th>
                            <th>Username</th>
                            <th>Contact Info</th>
                            <th>Role Badge</th>
                            <th>Registered Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users->num_rows > 0): ?>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:600; font-size:14px; color: var(--text-color);"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                        <div style="font-size:11px; color: var(--text-muted);">ID: #<?php echo $user['id']; ?></div>
                                    </td>
                                    <td style="font-weight:500;"><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td>
                                        <div><i class="far fa-envelope" style="width:16px;"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                                        <div style="margin-top:4px;"><i class="fas fa-phone-alt" style="width:16px; font-size:11px;"></i> <?php echo htmlspecialchars($user['phone']); ?></div>
                                    </td>
                                    <td>
                                        <?php 
                                        $role = $user['role'];
                                        $badge = 'badge-pending'; // Yellow for citizen
                                        $role_txt = 'Citizen';
                                        if ($role === 'admin') {
                                            $badge = 'badge-completed'; // Green for admin
                                            $role_txt = 'Administrator';
                                        } elseif ($role === 'staff') {
                                            $badge = 'badge-rescheduled'; // Blue for officer
                                            $role_txt = 'Officer';
                                        }
                                        ?>
                                        <span class="badge <?php echo $badge; ?>"><?php echo $role_txt; ?></span>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <?php if ($user['id'] !== intval($_SESSION['user_id'])): ?>
                                            <form action="admin_users.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user? This will remove all associated queue records.');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px; background-color: #ea4335; border:none;">
                                                    <i class="far fa-trash-alt"></i> Delete
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="font-size: 12px; font-style:italic; color: var(--text-muted);">Current Session</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                    No accounts match your query.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

</body>
</html>
