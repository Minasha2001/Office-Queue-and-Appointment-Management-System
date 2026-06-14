<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div>
        <div class="sidebar-logo">
            <img src="image/gov.png" alt="Sri Lanka Emblem">
            <h3>Admin Panel<br>Secretariat</h3>
        </div>
        
        <ul class="sidebar-menu">
            <li class="<?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">
                <a href="admin_dashboard.php">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="<?php echo ($current_page == 'admin_queue.php') ? 'active' : ''; ?>">
                <a href="admin_queue.php">
                    <i class="fas fa-tasks"></i>
                    <span>Queue Control</span>
                </a>
            </li>
            <li class="<?php echo ($current_page == 'admin_users.php') ? 'active' : ''; ?>">
                <a href="admin_users.php">
                    <i class="fas fa-users-cog"></i>
                    <span>User Accounts</span>
                </a>
            </li>
            <li class="<?php echo ($current_page == 'admin_staff.php') ? 'active' : ''; ?>">
                <a href="admin_staff.php">
                    <i class="fas fa-user-shield"></i>
                    <span>Staff Registry</span>
                </a>
            </li>
            <li class="<?php echo ($current_page == 'admin_services.php') ? 'active' : ''; ?>">
                <a href="admin_services.php">
                    <i class="fas fa-concierge-bell"></i>
                    <span>Services CRUD</span>
                </a>
            </li>
            <li class="<?php echo ($current_page == 'admin_schedules.php') ? 'active' : ''; ?>">
                <a href="admin_schedules.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Schedules</span>
                </a>
            </li>
            <li class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                <a href="reports.php">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="<?php echo ($current_page == 'citizen_queue.php') ? 'active' : ''; ?>">
                <a href="citizen_queue.php">
                    <i class="fas fa-users"></i>
                    <span>Live Queue Status</span>
                </a>
            </li>
            <li class="<?php echo ($current_page == 'logout.php') ? 'active' : ''; ?>">
                <a href="logout.php" style="color: #dc2626;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="sidebar-help" style="background-color: #f1f8e9;">
        <p style="color: #2e7d32; font-size:12px;">Logged in as<br><strong>Administrator</strong></p>
    </div>
</div>
