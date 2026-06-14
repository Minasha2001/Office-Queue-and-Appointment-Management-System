<?php
session_start();
require_once 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Basic Validation
    if (empty($full_name) || empty($username) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check if username already exists
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ?");
        mysqli_stmt_bind_param($check_stmt, "ss", $username, $email);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $error = "Username or Email already registered.";
            mysqli_stmt_close($check_stmt);
        } else {
            mysqli_stmt_close($check_stmt);

            // Hash password and insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'citizen';

            $insert_stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, full_name, email, phone, role) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($insert_stmt, "ssssss", $username, $hashed_password, $full_name, $email, $phone, $role);

            if (mysqli_stmt_execute($insert_stmt)) {
                $success = "Registration successful! You can now login.";
            } else {
                $error = "Registration failed. Please try again later.";
            }
            mysqli_stmt_close($insert_stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Divisional Secretariat - Citizen Registration</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<style>
*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:'Segoe UI',sans-serif;
}

body{
min-height:100vh;
background:#eef4fc;
display:flex;
justify-content:center;
align-items:center;
padding:20px;
}

.container{
width:100%;
max-width:1500px;
min-height:900px;
background:#fff;
border-radius:30px;
overflow:hidden;
display:flex;
box-shadow:0 15px 40px rgba(0,0,0,.15);
}

.left{
width:45%;
background: linear-gradient(135deg, rgba(13, 47, 128, 0.9), rgba(21, 96, 255, 0.8)), url('image/background.jpg') center center/cover no-repeat;
position:relative;
padding:60px;
display:flex;
flex-direction:column;
justify-content:space-between;
color: white;
}

.left-content{
text-align:center;
margin-top: 50px;
}

.logo{
width:110px;
margin-bottom:20px;
}

.left-content h1{
font-size:48px;
color:#fff;
font-weight:700;
}

.left-content h3{
color:#fff;
margin-top:10px;
font-size:24px;
opacity: 0.9;
}

.line{
width:120px;
height:4px;
background:#fff;
margin:20px auto;
border-radius:10px;
}

.points{
font-size:20px;
margin-top:20px;
opacity: 0.8;
}

.right{
width:55%;
display:flex;
justify-content:center;
align-items:center;
padding:50px;
background:#fff;
}

.register-card{
width:100%;
max-width:600px;
}

.icon-circle{
width:90px;
height:90px;
border-radius:50%;
background:linear-gradient(135deg,#1560ff,#003db8);
display:flex;
justify-content:center;
align-items:center;
margin:auto;
color:white;
font-size:35px;
}

.register-title{
text-align:center;
margin-top:20px;
}

.register-title h1{
font-size:38px;
color:#0d2f80;
}

.register-title p{
font-size:16px;
color:#666;
margin-top:5px;
}

.divider{
width:100px;
height:4px;
background:#1560ff;
margin:15px auto;
border-radius:10px;
}

.alert {
padding: 15px;
border-radius: 10px;
margin-top: 15px;
font-size: 16px;
}
.alert-error {
background-color: #fce8e6;
color: #c53929;
border: 1px solid #f8c9c4;
}
.alert-success {
background-color: #e6f4ea;
color: #137333;
border: 1px solid #c2e7cd;
}

.input-grid {
display: grid;
grid-template-columns: 1fr 1fr;
gap: 15px;
margin-top: 20px;
}

.input-group{
display:flex;
border:1px solid #ddd;
border-radius:15px;
overflow:hidden;
height:60px;
}

.input-group.full-width {
grid-column: span 2;
}

.icon-box{
width:60px;
display:flex;
justify-content:center;
align-items:center;
background:#f3f7ff;
font-size:20px;
color:#0d2f80;
}

.input-group input{
flex:1;
border:none;
outline:none;
padding:0 15px;
font-size:16px;
}

.register-btn{
width:100%;
height:60px;
margin-top:25px;
border:none;
border-radius:15px;
background:linear-gradient(135deg,#1560ff,#003db8);
color:white;
font-size:22px;
font-weight:600;
cursor:pointer;
display:flex;
justify-content:center;
align-items:center;
gap: 10px;
transition: 0.3s;
}

.register-btn:hover {
opacity: 0.9;
transform: translateY(-2px);
}

.footer{
margin-top:20px;
text-align:center;
color:#555;
font-size: 16px;
}

.footer a {
color: #1560ff;
text-decoration: none;
font-weight: 600;
}

@media(max-width:1100px){
.container{
flex-direction:column;
height:auto;
}
.left,
.right{
width:100%;
}
.input-grid {
grid-template-columns: 1fr;
}
.input-group.full-width {
grid-column: span 1;
}
}
</style>
</head>
<body>

<div class="container">
    <div class="left">
        <div class="left-content">
            <img src="image/gov.png" class="logo" alt="Emblem">
            <h1>Divisional Secretariat</h1>
            <div class="line"></div>
            <h3>Queue & Appointment Management System</h3>
            <div class="points">
                Register as a citizen to easily book appointments and track queue status online.
            </div>
        </div>
        <div style="text-align:center; font-size:14px; opacity:0.8;">
            © 2026 Divisional Secretariat Office. All rights reserved.
        </div>
    </div>

    <div class="right">
        <div class="register-card">
            <div class="icon-circle">
                <i class="fas fa-user-plus"></i>
            </div>

            <div class="register-title">
                <h1>Citizen Registration</h1>
                <p>Create your citizen profile to proceed</p>
                <div class="divider"></div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" onsubmit="return validateForm()">
                <div class="input-grid">
                    <div class="input-group full-width">
                        <div class="icon-box"><i class="far fa-user-circle"></i></div>
                        <input type="text" name="full_name" placeholder="Full Name" required value="<?php echo htmlspecialchars($full_name ?? ''); ?>">
                    </div>

                    <div class="input-group">
                        <div class="icon-box"><i class="far fa-user"></i></div>
                        <input type="text" name="username" placeholder="Username" required value="<?php echo htmlspecialchars($username ?? ''); ?>">
                    </div>

                    <div class="input-group">
                        <div class="icon-box"><i class="fas fa-phone-alt"></i></div>
                        <input type="text" name="phone" placeholder="Phone Number (e.g. +94771234567)" required value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                    </div>

                    <div class="input-group full-width">
                        <div class="icon-box"><i class="far fa-envelope"></i></div>
                        <input type="email" name="email" placeholder="Email Address" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    </div>

                    <div class="input-group">
                        <div class="icon-box"><i class="fas fa-lock"></i></div>
                        <input type="password" id="password" name="password" placeholder="Password" required>
                    </div>

                    <div class="input-group">
                        <div class="icon-box"><i class="fas fa-shield-alt"></i></div>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                    </div>
                </div>

                <button class="register-btn">
                    <i class="fas fa-user-check"></i> Register
                </button>
            </form>

            <div class="footer">
                Already registered? <a href="login.php">Login Here</a>
            </div>
        </div>
    </div>
</div>

<script>
function validateForm() {
    var password = document.getElementById("password").value;
    var confirmPassword = document.getElementById("confirm_password").value;
    if (password !== confirmPassword) {
        alert("Passwords do not match.");
        return false;
    }
    if (password.length < 6) {
        alert("Password must be at least 6 characters long.");
        return false;
    }
    return true;
}
</script>
</body>
</html>
