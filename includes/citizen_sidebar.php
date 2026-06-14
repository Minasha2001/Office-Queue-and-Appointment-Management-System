<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div>
        <div class="sidebar-logo">
            <img src="image/gov.png" alt="Sri Lanka Emblem">
            <h3>Divisional<br>Secretariat</h3>
        </div>
        
        <ul class="sidebar-menu">
            <li class="<?php echo ($current_page == 'citizen_dashboard.php') ? 'active' : ''; ?>">
                <a href="citizen_dashboard.php">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
            </li>
            <li class="<?php echo ($current_page == 'citizen_book.php') ? 'active' : ''; ?>">
                <a href="citizen_book.php">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Book Appointment</span>
                </a>
            </li>
            <li class="<?php echo ($current_page == 'citizen_appointments.php') ? 'active' : ''; ?>">
                <a href="citizen_appointments.php">
                    <i class="fas fa-calendar-check"></i>
                    <span>My Appointments</span>
                </a>
            </li>
            <li class="<?php echo ($current_page == 'citizen_queue.php') ? 'active' : ''; ?>">
                <a href="citizen_queue.php">
                    <i class="fas fa-users"></i>
                    <span>Queue Status</span>
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

    <div class="sidebar-help">
        <p>Need Help?<br>Our support team is here to assist you.</p>
        <a href="#" class="help-btn">Contact Us</a>
    </div>
</div>
