<?php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$page_title = "Service Management";
$success_msg = "";
$error_msg = "";

// 1. ADD SERVICE
if (isset($_POST['add_service'])) {
    $name = trim($_POST['name'] ?? '');
    $prefix = strtoupper(trim($_POST['prefix'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $duration_minutes = intval($_POST['duration_minutes'] ?? 30);
    $icon = trim($_POST['icon'] ?? 'fa-concierge-bell');
    $status = $_POST['status'] ?? 'active';

    if (empty($name) || empty($prefix)) {
        $error_msg = "Service Name and Prefix are required.";
    } else {
        // Check if prefix already exists
        $chk = $conn->prepare("SELECT id FROM services WHERE prefix = ?");
        $chk->bind_param("s", $prefix);
        $chk->execute();
        $exists = $chk->get_result()->num_rows > 0;
        $chk->close();

        if ($exists) {
            $error_msg = "A service with prefix '$prefix' already exists.";
        } else {
            $ins = $conn->prepare("INSERT INTO services (name, prefix, description, duration_minutes, icon, status) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->bind_param("sssis|", $name, $prefix, $description, $duration_minutes, $icon, $status);
            // Wait, mysqli bind_param type string: "sssis" has 5 types, but 6 parameters!
            // Type for name: s
            // Type for prefix: s
            // Type for description: s
            // Type for duration_minutes: i
            // Type for icon: s
            // Type for status: s
            // Total: 6 parameters, so types string should be "sssiss".
            // Let's make sure it is correct!
            $ins->close();
            // Let's write the correct prepare and bind:
            $ins_correct = $conn->prepare("INSERT INTO services (name, prefix, description, duration_minutes, icon, status) VALUES (?, ?, ?, ?, ?, ?)");
            $ins_correct->bind_param("sssiss", $name, $prefix, $description, $duration_minutes, $icon, $status);
            if ($ins_correct->execute()) {
                // Log Activity
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Add Service', ?)");
                $details = "Created service: $name ($prefix)";
                $log->bind_param("is", $_SESSION['user_id'], $details);
                $log->execute();
                $log->close();

                $success_msg = "Service created successfully.";
            } else {
                $error_msg = "Failed to create service: " . $conn->error;
            }
            $ins_correct->close();
        }
    }
}

// 2. EDIT SERVICE
if (isset($_POST['edit_service'])) {
    $id = intval($_POST['service_id']);
    $name = trim($_POST['name'] ?? '');
    $prefix = strtoupper(trim($_POST['prefix'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $duration_minutes = intval($_POST['duration_minutes'] ?? 30);
    $icon = trim($_POST['icon'] ?? 'fa-concierge-bell');
    $status = $_POST['status'] ?? 'active';

    if (empty($name) || empty($prefix)) {
        $error_msg = "Service Name and Prefix are required.";
    } else {
        // Check if prefix already exists on other services
        $chk = $conn->prepare("SELECT id FROM services WHERE prefix = ? AND id != ?");
        $chk->bind_param("si", $prefix, $id);
        $chk->execute();
        $exists = $chk->get_result()->num_rows > 0;
        $chk->close();

        if ($exists) {
            $error_msg = "Another service with prefix '$prefix' already exists.";
        } else {
            $up = $conn->prepare("UPDATE services SET name = ?, prefix = ?, description = ?, duration_minutes = ?, icon = ?, status = ? WHERE id = ?");
            $up->bind_param("sssissi", $name, $prefix, $description, $duration_minutes, $icon, $status, $id);
            if ($up->execute()) {
                // Log Activity
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Edit Service', ?)");
                $details = "Updated service ID: $id ($prefix)";
                $log->bind_param("is", $_SESSION['user_id'], $details);
                $log->execute();
                $log->close();

                $success_msg = "Service updated successfully.";
            } else {
                $error_msg = "Failed to update service: " . $conn->error;
            }
            $up->close();
        }
    }
}

// 3. DELETE SERVICE
if (isset($_POST['delete_service'])) {
    $id = intval($_POST['service_id']);
    
    $del = $conn->prepare("DELETE FROM services WHERE id = ?");
    $del->bind_param("i", $id);
    if ($del->execute()) {
        $success_msg = "Service deleted successfully.";
    } else {
        $error_msg = "Failed to delete service: " . $conn->error;
    }
    $del->close();
}

// Fetch all services
$services_res = mysqli_query($conn, "SELECT id, name, prefix, description, duration_minutes, icon, status FROM services ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Management - Divisional Secretariat</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .services-manager {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 30px;
        }
        @media (max-width: 1024px) {
            .services-manager {
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

        <div class="services-manager">
            <!-- Left: Services Table -->
            <div class="table-card">
                <div style="padding: 20px; border-bottom: 1px solid var(--border-color);">
                    <h3 style="color: var(--primary-color);"><i class="fas fa-concierge-bell"></i> Office Services List</h3>
                </div>
                <div class="table-responsive">
                    <table class="custom-table" style="font-size: 13px;">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Prefix</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($services_res) > 0): ?>
                                <?php while ($srv = mysqli_fetch_assoc($services_res)): ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; align-items:center; gap: 10px;">
                                                <div class="service-icon-box" style="width:36px; height:36px; margin:0; font-size:16px;">
                                                    <i class="fas <?php echo htmlspecialchars($srv['icon']); ?>"></i>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($srv['name']); ?></div>
                                                    <div style="font-size:11px; color:var(--text-muted); max-width: 250px; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;"><?php echo htmlspecialchars($srv['description']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="font-family: monospace; font-size:14px; font-weight:700; color:var(--primary-light);">
                                            <?php echo htmlspecialchars($srv['prefix']); ?>
                                        </td>
                                        <td><?php echo $srv['duration_minutes']; ?> mins</td>
                                        <td>
                                            <span class="badge <?php echo ($srv['status'] === 'active') ? 'badge-completed' : 'badge-absent'; ?>">
                                                <?php echo ucfirst($srv['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button onclick="loadEditForm(<?php echo htmlspecialchars(json_encode($srv)); ?>)" class="btn btn-secondary" style="padding: 6px 12px; font-size:12px; border:1px solid var(--border-color);">
                                                <i class="far fa-edit"></i> Edit
                                            </button>
                                            
                                            <form action="admin_services.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this service? All related staff assignments and appointments will be affected.');">
                                                <input type="hidden" name="service_id" value="<?php echo $srv['id']; ?>">
                                                <button type="submit" name="delete_service" class="btn btn-primary" style="padding: 6px 12px; font-size:12px; background-color: #ea4335; border:none;">
                                                    <i class="far fa-trash-alt"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 25px;">No services registered in the database.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right: Service Form Panel -->
            <div class="form-card" id="formPanel">
                <h3 id="formTitle" style="color: var(--primary-color); border-bottom:1px solid var(--border-color); padding-bottom:15px; margin-bottom:20px;">
                    <i class="fas fa-plus-circle"></i> Add Office Service
                </h3>
                
                <form action="admin_services.php" method="POST" id="serviceForm">
                    <input type="hidden" name="service_id" id="form_service_id">
                    
                    <div class="form-group">
                        <label for="form_name">Service Name</label>
                        <input type="text" class="form-control" name="name" id="form_name" placeholder="e.g. Birth Certificate" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="form_prefix">Token Prefix</label>
                            <input type="text" class="form-control" name="prefix" id="form_prefix" placeholder="e.g. BC" required>
                        </div>
                        <div class="form-group">
                            <label for="form_duration">Slot Duration (Mins)</label>
                            <input type="number" class="form-control" name="duration_minutes" id="form_duration" min="5" max="120" value="30" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="form_description">Description</label>
                        <textarea class="form-control" name="description" id="form_description" rows="3" placeholder="Enter brief service guidelines..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="form_icon">FontAwesome Icon</label>
                            <input type="text" class="form-control" name="icon" id="form_icon" placeholder="e.g. fa-baby" value="fa-concierge-bell" required>
                        </div>
                        <div class="form-group">
                            <label for="form_status">Status</label>
                            <select class="form-control" name="status" id="form_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="add_service" id="formSubmitBtn" class="btn btn-primary" style="width:100%; height:45px; margin-top:10px;">
                        Create Service
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
    document.getElementById('formTitle').innerHTML = '<i class="far fa-edit"></i> Edit Service Details';
    document.getElementById('form_service_id').value = data.id;
    document.getElementById('form_name').value = data.name;
    document.getElementById('form_prefix').value = data.prefix;
    document.getElementById('form_duration').value = data.duration_minutes;
    document.getElementById('form_description').value = data.description;
    document.getElementById('form_icon').value = data.icon;
    document.getElementById('form_status').value = data.status;
    
    // Configure buttons
    document.getElementById('formSubmitBtn').name = 'edit_service';
    document.getElementById('formSubmitBtn').innerText = 'Save Changes';
    document.getElementById('formCancelBtn').style.display = 'block';
}

function resetForm() {
    document.getElementById('formTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Add Office Service';
    document.getElementById('serviceForm').reset();
    document.getElementById('form_service_id').value = '';
    
    // Configure buttons
    document.getElementById('formSubmitBtn').name = 'add_service';
    document.getElementById('formSubmitBtn').innerText = 'Create Service';
    document.getElementById('formCancelBtn').style.display = 'none';
}
</script>
</body>
</html>
