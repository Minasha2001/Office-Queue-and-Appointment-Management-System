<?php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$page_title = "Staff Management";
$success_msg = "";
$error_msg = "";

// Fetch all services for assignments dropdown
$srv_q = mysqli_query($conn, "SELECT id, name FROM services WHERE status = 'active' ORDER BY id ASC");
$services = [];
while ($row = mysqli_fetch_assoc($srv_q)) {
    $services[] = $row;
}

// 1. ADD STAFF OPERATION
if (isset($_POST['add_staff'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $service_id = intval($_POST['service_id'] ?? 0);
    $status = $_POST['status'] ?? 'active';

    if (empty($full_name) || empty($username) || empty($email) || empty($phone) || empty($password)) {
        $error_msg = "All fields are required to add staff.";
    } else {
        // Check if username/email already exists
        $chk = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $chk->bind_param("ss", $username, $email);
        $chk->execute();
        $exists = $chk->get_result()->num_rows > 0;
        $chk->close();

        if ($exists) {
            $error_msg = "Username or Email is already taken.";
        } else {
            $conn->begin_transaction();
            try {
                // Insert into users table
                $hashed_pw = password_hash($password, PASSWORD_DEFAULT);
                $role = 'staff';
                $ins_u = $conn->prepare("INSERT INTO users (username, password, full_name, email, phone, role) VALUES (?, ?, ?, ?, ?, ?)");
                $ins_u->bind_param("ssssss", $username, $hashed_pw, $full_name, $email, $phone, $role);
                $ins_u->execute();
                $user_id = $conn->insert_id;
                $ins_u->close();

                // Insert into staff table
                $srv_param = ($service_id > 0) ? $service_id : null;
                $ins_s = $conn->prepare("INSERT INTO staff (user_id, service_id, status) VALUES (?, ?, ?)");
                $ins_s->bind_param("iis", $user_id, $srv_param, $status);
                $ins_s->execute();
                $ins_s->close();

                // Log Activity
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Add Staff', ?)");
                $details = "Created officer: $username, assigned to service ID: $service_id";
                $log->bind_param("is", $_SESSION['user_id'], $details);
                $log->execute();
                $log->close();

                $conn->commit();
                $success_msg = "Officer registered and assigned successfully.";
            } catch (Exception $e) {
                $conn->rollback();
                $error_msg = "Error creating staff: " . $e->getMessage();
            }
        }
    }
}

// 2. EDIT STAFF OPERATION
if (isset($_POST['edit_staff'])) {
    $staff_id = intval($_POST['staff_id']);
    $user_id = intval($_POST['user_id']);
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $service_id = intval($_POST['service_id'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    $password = $_POST['password'] ?? '';

    if (empty($full_name) || empty($email) || empty($phone)) {
        $error_msg = "Full Name, Email, and Phone cannot be empty.";
    } else {
        $conn->begin_transaction();
        try {
            // Update users table details
            if (!empty($password)) {
                $hashed_pw = password_hash($password, PASSWORD_DEFAULT);
                $up_u = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, password = ? WHERE id = ?");
                $up_u->bind_param("ssssi", $full_name, $email, $phone, $hashed_pw, $user_id);
            } else {
                $up_u = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
                $up_u->bind_param("sssi", $full_name, $email, $phone, $user_id);
            }
            $up_u->execute();
            $up_u->close();

            // Update staff table details
            $srv_param = ($service_id > 0) ? $service_id : null;
            $up_s = $conn->prepare("UPDATE staff SET service_id = ?, status = ? WHERE id = ?");
            $up_s->bind_param("isi", $srv_param, $status, $staff_id);
            $up_s->execute();
            $up_s->close();

            // Log Activity
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Edit Staff', ?)");
            $details = "Updated officer ID: $staff_id, assigned to service ID: $service_id";
            $log->bind_param("is", $_SESSION['user_id'], $details);
            $log->execute();
            $log->close();

            $conn->commit();
            $success_msg = "Staff details updated successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Error updating staff: " . $e->getMessage();
        }
    }
}

// 3. DELETE STAFF OPERATION
if (isset($_POST['delete_staff'])) {
    $delete_user_id = intval($_POST['user_id']);
    
    // Deleting user account automatically deletes staff record due to CASCADE
    $del = $conn->prepare("DELETE FROM users WHERE id = ?");
    $del->bind_param("i", $delete_user_id);
    if ($del->execute()) {
        $success_msg = "Staff account deleted successfully.";
    } else {
        $error_msg = "Error deleting staff account: " . $conn->error;
    }
    $del->close();
}

// Fetch all staff members and their assigned services
$staff_query = "SELECT s.id as staff_id, s.service_id, s.status as staff_status, u.id as user_id, u.username, u.full_name, u.email, u.phone, sv.name as service_name 
                FROM staff s 
                JOIN users u ON s.user_id = u.id 
                LEFT JOIN services sv ON s.service_id = sv.id 
                ORDER BY s.id DESC";
$staff_list = mysqli_query($conn, $staff_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff registry - Divisional Secretariat</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .staff-manager {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 30px;
        }
        @media (max-width: 1024px) {
            .staff-manager {
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

        <div class="staff-manager">
            <!-- Left: Staff List -->
            <div class="table-card">
                <div style="padding: 20px; border-bottom: 1px solid var(--border-color);">
                    <h3 style="color: var(--primary-color);"><i class="fas fa-user-shield"></i> Registered Officers</h3>
                </div>
                <div class="table-responsive">
                    <table class="custom-table" style="font-size: 13px;">
                        <thead>
                            <tr>
                                <th>Officer</th>
                                <th>Service Assigned</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($staff_list) > 0): ?>
                                <?php while ($st = mysqli_fetch_assoc($staff_list)): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($st['full_name']); ?></div>
                                            <div style="font-size:11px; color:var(--text-muted);">@<?php echo htmlspecialchars($st['username']); ?> • <?php echo htmlspecialchars($st['phone']); ?></div>
                                        </td>
                                        <td style="font-weight: 500; color: var(--primary-light);">
                                            <?php echo htmlspecialchars($st['service_name'] ?? 'Unassigned'); ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo ($st['staff_status'] === 'active') ? 'badge-completed' : 'badge-absent'; ?>">
                                                <?php echo ucfirst($st['staff_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button onclick="loadEditForm(<?php echo htmlspecialchars(json_encode($st)); ?>)" class="btn btn-secondary" style="padding: 6px 12px; font-size:12px; border:1px solid var(--border-color);">
                                                <i class="far fa-edit"></i> Edit
                                            </button>
                                            
                                            <form action="admin_staff.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this officer account?');">
                                                <input type="hidden" name="user_id" value="<?php echo $st['user_id']; ?>">
                                                <button type="submit" name="delete_staff" class="btn btn-primary" style="padding: 6px 12px; font-size:12px; background-color: #ea4335; border:none;">
                                                    <i class="far fa-trash-alt"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 25px;">No officers registered.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right: Add / Edit Form Panel -->
            <div class="form-card" id="formPanel">
                <h3 id="formTitle" style="color: var(--primary-color); border-bottom:1px solid var(--border-color); padding-bottom:15px; margin-bottom:20px;">
                    <i class="fas fa-user-plus"></i> Add New Officer
                </h3>
                
                <form action="admin_staff.php" method="POST" id="staffForm">
                    <input type="hidden" name="staff_id" id="form_staff_id">
                    <input type="hidden" name="user_id" id="form_user_id">
                    
                    <div class="form-group">
                        <label for="form_full_name">Full Name</label>
                        <input type="text" class="form-control" name="full_name" id="form_full_name" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group" id="usernameGroup">
                            <label for="form_username">Username</label>
                            <input type="text" class="form-control" name="username" id="form_username" required>
                        </div>
                        <div class="form-group">
                            <label for="form_phone">Phone Number</label>
                            <input type="text" class="form-control" name="phone" id="form_phone" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="form_email">Email Address</label>
                        <input type="email" class="form-control" name="email" id="form_email" required>
                    </div>

                    <div class="form-group">
                        <label for="form_password" id="passLabel">Password</label>
                        <input type="password" class="form-control" name="password" id="form_password" required>
                        <small style="color:var(--text-muted); display:none;" id="passHelp">Leave empty to keep current password.</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="form_service_id">Service Assignment</label>
                            <select class="form-control" name="service_id" id="form_service_id">
                                <option value="0">Unassigned</option>
                                <?php foreach ($services as $srv): ?>
                                    <option value="<?php echo $srv['id']; ?>"><?php echo htmlspecialchars($srv['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="form_status">Status</label>
                            <select class="form-control" name="status" id="form_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="add_staff" id="formSubmitBtn" class="btn btn-primary" style="width:100%; height:45px; margin-top:10px;">
                        Register Officer
                    </button>
                    
                    <button type="button" onclick="resetForm()" id="formCancelBtn" class="btn btn-secondary" style="width:100%; margin-top:10px; display:none; border: 1px solid var(--border-color);">
                        Cancel Edit
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
function loadEditForm(data) {
    document.getElementById('formTitle').innerHTML = '<i class="far fa-edit"></i> Edit Officer Profile';
    document.getElementById('form_staff_id').value = data.staff_id;
    document.getElementById('form_user_id').value = data.user_id;
    document.getElementById('form_full_name').value = data.full_name;
    document.getElementById('form_phone').value = data.phone;
    document.getElementById('form_email').value = data.email;
    document.getElementById('form_service_id').value = data.service_id ? data.service_id : 0;
    document.getElementById('form_status').value = data.staff_status;
    
    // Hide username field on edit (unique key, shouldn't change)
    document.getElementById('usernameGroup').style.display = 'none';
    document.getElementById('form_username').required = false;
    
    // Make password optional
    document.getElementById('form_password').required = false;
    document.getElementById('passLabel').innerText = "Update Password (Optional)";
    document.getElementById('passHelp').style.display = 'block';
    
    // Configure buttons
    document.getElementById('formSubmitBtn').name = 'edit_staff';
    document.getElementById('formSubmitBtn').innerText = 'Save Changes';
    document.getElementById('formCancelBtn').style.display = 'block';
}

function resetForm() {
    document.getElementById('formTitle').innerHTML = '<i class="fas fa-user-plus"></i> Add New Officer';
    document.getElementById('staffForm').reset();
    document.getElementById('form_staff_id').value = '';
    document.getElementById('form_user_id').value = '';
    
    // Show username field
    document.getElementById('usernameGroup').style.display = 'block';
    document.getElementById('form_username').required = true;
    
    // Make password required
    document.getElementById('form_password').required = true;
    document.getElementById('passLabel').innerText = "Password";
    document.getElementById('passHelp').style.display = 'none';
    
    // Configure buttons
    document.getElementById('formSubmitBtn').name = 'add_staff';
    document.getElementById('formSubmitBtn').innerText = 'Register Officer';
    document.getElementById('formCancelBtn').style.display = 'none';
}
</script>
</body>
</html>
