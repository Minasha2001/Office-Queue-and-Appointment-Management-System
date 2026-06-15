<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div>
        <div class="sidebar-logo">
            <img src="image/gov.png?v=4" alt="Sri Lanka Emblem">
            <h3>Officer Panel<br>Secretariat</h3>
        </div>
        
        <ul class="sidebar-menu">
            <li class="<?php echo ($current_page == 'staff_dashboard.php') ? 'active' : ''; ?>">
                <a href="staff_dashboard.php">
                    <i class="fas fa-desktop"></i>
                    <span>Today's Queue</span>
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

    <div class="sidebar-help" style="background-color: #e3f2fd;">
        <p style="color: #0d47a1;">Duty Officer<br>Please serve customers in sequential order.</p>
    </div>
</div>
