<?php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header("Location: login.php");
    exit();
}

$page_title = "Home";

// Fetch active services
$services_query = "SELECT id, name, description, icon FROM services WHERE status = 'active' ORDER BY id ASC";
$services_result = mysqli_query($conn, $services_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Home - Divisional Secretariat</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="app-container">
    <!-- Sidebar Include -->
    <?php include 'includes/citizen_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header Bar Include -->
        <?php include 'includes/header.php'; ?>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="banner-text">
                <h2>Welcome, <?php echo htmlspecialchars(explode(" ", $_SESSION['full_name'])[0]); ?>! 👋</h2>
                <p>Book appointments, check queue status and access government services easily from the comfort of your home.</p>
                <div class="banner-buttons">
                    <a href="citizen_book.php" class="btn btn-primary">
                        <i class="fas fa-calendar-plus"></i> Book an Appointment
                    </a>
                    <a href="citizen_queue.php" class="btn btn-secondary">
                        <i class="fas fa-users"></i> Check Queue Status
                    </a>
                </div>
            </div>
            <img src="image/background.jpg?v=4" alt="Divisional Secretariat" class="banner-img">
        </div>

        <!-- Available Services Section -->
        <h3 class="section-title">Available Services</h3>
        
        <div class="services-grid">
            <?php if (mysqli_num_rows($services_result) > 0): ?>
                <?php while ($service = mysqli_fetch_assoc($services_result)): ?>
                    <div class="service-card">
                        <div>
                            <div class="service-icon-box">
                                <i class="fas <?php echo htmlspecialchars($service['icon']); ?>"></i>
                            </div>
                            <h4><?php echo htmlspecialchars($service['name']); ?></h4>
                            <p><?php echo htmlspecialchars($service['description']); ?></p>
                        </div>
                        <div style="margin-top: 15px;">
                            <a href="citizen_book.php?service_id=<?php echo $service['id']; ?>" class="book-btn">Book Now</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="grid-column: span 4; text-align: center; color: var(--text-muted);">No services available at the moment.</p>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>
