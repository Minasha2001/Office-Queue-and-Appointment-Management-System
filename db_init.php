<?php
// Initialize database connection
$host = "localhost";
$username = "root";
$password = "";

$conn = mysqli_connect($host, $username, $password);
if (!$conn) {
    die("Database Server Connection Failed: " . mysqli_connect_error());
}

// 1. Create Database if not exists
$sql_db = "CREATE DATABASE IF NOT EXISTS office_queue_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (mysqli_query($conn, $sql_db)) {
    echo "Database office_queue_system created or already exists.<br>";
} else {
    die("Error creating database: " . mysqli_error($conn));
}

// Select the database
mysqli_select_db($conn, "office_queue_system");

// 2. Read schema.sql and execute
$schema = file_get_contents("schema.sql");
// Split SQL by semicolon, but avoid breaking on triggers or comments
$queries = explode(";", $schema);

foreach ($queries as $query) {
    $trimmed_query = trim($query);
    if (!empty($trimmed_query)) {
        if (mysqli_query($conn, $trimmed_query)) {
            // Success
        } else {
            echo "Error executing query: " . mysqli_error($conn) . "<br>Query: " . htmlspecialchars($trimmed_query) . "<br>";
        }
    }
}
echo "Database tables checked/created successfully.<br>";

// 3. Seed Default Data

// A. Seed Admin User
$admin_username = "admin";
$admin_password_plain = "admin123";
$admin_password = password_hash($admin_password_plain, PASSWORD_DEFAULT);
$admin_fullname = "System Administrator";
$admin_email = "admin@office.gov.lk";
$admin_phone = "+94112345678";
$admin_role = "admin";

$check_admin = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
mysqli_stmt_bind_param($check_admin, "s", $admin_username);
mysqli_stmt_execute($check_admin);
mysqli_stmt_store_result($check_admin);

if (mysqli_stmt_num_rows($check_admin) == 0) {
    $insert_admin = mysqli_prepare($conn, "INSERT INTO users (username, password, full_name, email, phone, role) VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($insert_admin, "ssssss", $admin_username, $admin_password, $admin_fullname, $admin_email, $admin_phone, $admin_role);
    if (mysqli_stmt_execute($insert_admin)) {
        echo "Admin user seeded successfully. (User: admin, Pass: admin123)<br>";
    }
} else {
    echo "Admin user already exists.<br>";
}
mysqli_stmt_close($check_admin);

// B. Seed Default Services
$default_services = [
    ['Birth Certificate', 'BC', 'Apply for and receive certified copies of birth certificates.', 30, 'fa-baby'],
    ['Death Certificate', 'DC', 'Apply for and receive certified copies of death certificates.', 30, 'fa-file-invoice'],
    ['Residence Certificate', 'RC', 'Obtain official residency verification certificates.', 30, 'fa-home'],
    ['NIC Services', 'NIC', 'National Identity Card applications and updates.', 30, 'fa-id-card'],
    ['Marriage Registration', 'MR', 'Marriage registration services and copies of marriage certificates.', 30, 'fa-ring'],
    ['Business Registration', 'BR', 'Register sole proprietorships and partnerships.', 30, 'fa-briefcase'],
    ['Land Services', 'LS', 'Land registration, surveys, and ownership certificates.', 30, 'fa-map-marked-alt'],
    ['Samurdhi Services', 'SS', 'Social welfare, relief services, and benefit programs.', 30, 'fa-hands-helping'],
    ['Elderly Assistance', 'EA', 'Identity cards and support assistance for senior citizens.', 30, 'fa-blind'],
    ['Disability Assistance', 'DA', 'Support systems, equipment services, and financial aid registrations.', 30, 'fa-universal-access']
];

foreach ($default_services as $srv) {
    $srv_name = $srv[0];
    $srv_prefix = $srv[1];
    $srv_desc = $srv[2];
    $srv_dur = $srv[3];
    $srv_icon = $srv[4];

    $check_srv = mysqli_prepare($conn, "SELECT id FROM services WHERE prefix = ?");
    mysqli_stmt_bind_param($check_srv, "s", $srv_prefix);
    mysqli_stmt_execute($check_srv);
    mysqli_stmt_store_result($check_srv);

    if (mysqli_stmt_num_rows($check_srv) == 0) {
        $insert_srv = mysqli_prepare($conn, "INSERT INTO services (name, prefix, description, duration_minutes, icon) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($insert_srv, "sssis", $srv_name, $srv_prefix, $srv_desc, $srv_dur, $srv_icon);
        mysqli_stmt_execute($insert_srv);
    }
    mysqli_stmt_close($check_srv);
}
echo "Default services checked/seeded successfully.<br>";

// C. Seed Staff Users and Assign Services
// Create 2 officers for testing
$staff_users = [
    ['officer1', 'officer123', 'Officer Minu Perera', 'minu@office.gov.lk', '+94771234567', 'BC'],
    ['officer2', 'officer123', 'Officer Saman Silva', 'saman@office.gov.lk', '+94777654321', 'NIC']
];

foreach ($staff_users as $st) {
    $st_username = $st[0];
    $st_password = password_hash($st[1], PASSWORD_DEFAULT);
    $st_fullname = $st[2];
    $st_email = $st[3];
    $st_phone = $st[4];
    $st_prefix = $st[5];

    $check_user = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
    mysqli_stmt_bind_param($check_user, "s", $st_username);
    mysqli_stmt_execute($check_user);
    mysqli_stmt_store_result($check_user);

    if (mysqli_stmt_num_rows($check_user) == 0) {
        // Insert user
        $insert_user = mysqli_prepare($conn, "INSERT INTO users (username, password, full_name, email, phone, role) VALUES (?, ?, ?, ?, ?, 'staff')");
        mysqli_stmt_bind_param($insert_user, "sssss", $st_username, $st_password, $st_fullname, $st_email, $st_phone);
        mysqli_stmt_execute($insert_user);
        $user_id = mysqli_insert_id($conn);
        mysqli_stmt_close($insert_user);

        // Get Service ID
        $get_srv = mysqli_prepare($conn, "SELECT id FROM services WHERE prefix = ?");
        mysqli_stmt_bind_param($get_srv, "s", $st_prefix);
        mysqli_stmt_execute($get_srv);
        $res = mysqli_stmt_get_result($get_srv);
        $srv_row = mysqli_fetch_assoc($res);
        $service_id = $srv_row['id'];
        mysqli_stmt_close($get_srv);

        // Insert staff
        $insert_staff = mysqli_prepare($conn, "INSERT INTO staff (user_id, service_id, status) VALUES (?, ?, 'active')");
        mysqli_stmt_bind_param($insert_staff, "ii", $user_id, $service_id);
        mysqli_stmt_execute($insert_staff);
        mysqli_stmt_close($insert_staff);

        echo "Staff user seeded: $st_username (assigned to prefix $st_prefix)<br>";
    } else {
        mysqli_stmt_close($check_user);
    }
}

// D. Seed Default Schedules (Working Hours: Mon-Fri 08:30 - 16:30, slot duration 30 mins)
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
foreach ($days as $day) {
    $check_sched = mysqli_prepare($conn, "SELECT id FROM schedules WHERE day_of_week = ?");
    mysqli_stmt_bind_param($check_sched, "s", $day);
    mysqli_stmt_execute($check_sched);
    mysqli_stmt_store_result($check_sched);

    if (mysqli_stmt_num_rows($check_sched) == 0) {
        $insert_sched = mysqli_prepare($conn, "INSERT INTO schedules (day_of_week, start_time, end_time, slot_duration_minutes) VALUES (?, '08:30:00', '16:30:00', 30)");
        mysqli_stmt_bind_param($insert_sched, "s", $day);
        mysqli_stmt_execute($insert_sched);
        mysqli_stmt_close($insert_sched);
    } else {
        mysqli_stmt_close($check_sched);
    }
}
echo "Default schedules seeded successfully.<br>";

// E. Seed Citizen User for Demo
$citizen_username = "minu";
$citizen_password_plain = "minu123";
$citizen_password = password_hash($citizen_password_plain, PASSWORD_DEFAULT);
$citizen_fullname = "Minu Perera";
$citizen_email = "minu@gmail.com";
$citizen_phone = "+94770000000";
$citizen_role = "citizen";

$check_citizen = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
mysqli_stmt_bind_param($check_citizen, "s", $citizen_username);
mysqli_stmt_execute($check_citizen);
mysqli_stmt_store_result($check_citizen);

if (mysqli_stmt_num_rows($check_citizen) == 0) {
    $insert_citizen = mysqli_prepare($conn, "INSERT INTO users (username, password, full_name, email, phone, role) VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($insert_citizen, "ssssss", $citizen_username, $citizen_password, $citizen_fullname, $citizen_email, $citizen_phone, $citizen_role);
    if (mysqli_stmt_execute($insert_citizen)) {
        echo "Citizen demo user seeded successfully. (User: minu, Pass: minu123)<br>";
    }
    mysqli_stmt_close($insert_citizen);
} else {
    echo "Citizen demo user already exists.<br>";
    mysqli_stmt_close($check_citizen);
}

mysqli_close($conn);
echo "Database Initialization Completed Successfully!";
?>
