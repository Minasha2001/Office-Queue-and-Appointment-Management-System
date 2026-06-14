<?php
// Retrieve role display name
$role_display = 'Citizen';
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        $role_display = 'Administrator';
    } elseif ($_SESSION['role'] === 'staff') {
        $role_display = 'Officer';
    }
}

// Generate Avatar Initials
$full_name = $_SESSION['full_name'] ?? 'User';
$words = explode(" ", $full_name);
$initials = "";
foreach ($words as $w) {
    $initials .= strtoupper($w[0] ?? '');
    if (strlen($initials) >= 2) break;
}
if (empty($initials)) $initials = "U";
?>
<div class="header-bar">
    <div class="page-title">
        <!-- Will be set on the page or defaults to Dashboard -->
        <?php echo $page_title ?? 'Dashboard'; ?>
    </div>
    
    <div class="user-profile">
        <div class="notification-bell">
            <i class="far fa-bell"></i>
            <span class="notification-badge">3</span>
        </div>
        
        <div class="profile-info">
            <div class="profile-avatar">
                <?php echo htmlspecialchars($initials); ?>
            </div>
            <div>
                <div class="profile-name"><?php echo htmlspecialchars($full_name); ?></div>
                <div class="profile-role"><?php echo htmlspecialchars($role_display); ?></div>
            </div>
        </div>
    </div>
</div>
