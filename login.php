<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Divisional Secretariat Login</title>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:'Segoe UI',sans-serif;
}

body{
overflow:hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    height: 100vh;
     background-attachment: fixed;
        background-size: cover;
        background-position: center;
}

.container{
width:100%;
max-width:1500px;
height:900px;
background:#fff;
border-radius:30px;
overflow:hidden;
display:flex;
box-shadow:0 15px 40px rgba(0,0,0,.15);
}

.left{
width:58%;
position:relative;
background:url('image/background.jpg') center center/cover no-repeat;
width:55%;
background:url('image/background.jpg?v=4') center center/cover no-repeat;
position:relative;
padding:50px;
}

.left::before{
content:'';
position:absolute;
top:0;
left:0;
width:100%;
height:100%;
background:rgba(255,255,255,.15);
}

.left-content{
position:relative;
z-index:2;
text-align:center;
}

.logo{
width:110px;
margin-bottom:20px;
}

.office-image{
    margin-top:40px;
    text-align:center;
    position:relative;
    z-index:2;
}

.office-image img{
    width:80%;
    max-width:4ch00px; /* Image size */
    height:auto;
    object-fit:cover;
    border-radius:20px;
    box-shadow:0 10px 25px rgba(0,0,0,.15);
}

.left-content h1{
font-size:55px;
color:#0d2f80;
font-weight:700;
}

.left-content h3{
color:#1560ff;
margin-top:10px;
font-size:28px;
}

.line{
width:120px;
height:4px;
background:#2b73ff;
margin:20px auto;
border-radius:10px;
}

.points{
font-size:22px;
margin-top:20px;
}

.office-box{
position:absolute;
bottom:40px;
left:50%;
transform:translateX(-50%);
background:#fff;
width:85%;
padding:25px;
border-radius:30px;
display:flex;
justify-content:space-around;
box-shadow:0 5px 20px rgba(0,0,0,.15);
z-index:2;
}

.feature{
text-align:center;
}

.feature i{
font-size:35px;
color:#1560ff;
margin-bottom:10px;
}

.right{
width:45%;
display:flex;
justify-content:center;
align-items:center;
padding:50px;
background:#fff;
}

.login-card{
    background:#fff;
    width:100%;
    max-width:430px;
    border-radius:26px;
    padding:44px 40px 36px;
    box-shadow:0 30px 60px rgba(20,55,110,.16);
}

.icon-circle{
width:100px;
height:100px;
border-radius:50%;
background:linear-gradient(135deg,#1560ff,#003db8);
display:flex;
justify-content:center;
align-items:center;
margin:auto;
color:white;
font-size:40px;
}

.login-title{
text-align:center;
margin-top:25px;
}

.login-title h1{
font-size:48px;
color:#0d2f80;
}

.login-title p{
font-size:18px;
color:#666;
margin-top:10px;
}

.divider{
width:100px;
height:4px;
background:#1560ff;
margin:20px auto;
border-radius:10px;
}

.input-group{
display:flex;
margin-top:20px;
border:1px solid #e3e7ef;
border-radius:15px;
overflow:hidden;
height:75px;
background:#fff;
box-shadow:0 3px 10px rgba(0,0,0,.05);
}

.icon-box{
width:75px;
display:flex;
justify-content:center;
align-items:center;
background:#f5f8ff;
font-size:24px;
color:#1560ff;
border-right:1px solid #e3e7ef;
}

.input-group input,
.input-group select{
flex:1;
border:none;
outline:none;
padding:0 20px;
font-size:18px;
background:transparent;
}

.input-group input,
.input-group select{
flex:1;
border:none;
outline:none;
padding:0 20px;
font-size:18px;
}

.options{
display:flex;
justify-content:space-between;
margin-top:20px;
font-size:16px;
}

.options a{
text-decoration:none;
color:#1560ff;
}

.login-btn{
width:100%;
height:75px;
margin-top:30px;
border:none;
border-radius:15px;
background:linear-gradient(135deg,#1b67ff,#0047d6);
color:white;
font-size:24px;
font-weight:600;
cursor:pointer;
box-shadow:0 10px 25px rgba(0,71,214,.35);
transition:all .3s ease;
}

.login-btn:hover{
transform:translateY(-3px);
box-shadow:0 15px 30px rgba(0,71,214,.45);
}

.login-btn:active{
transform:translateY(0);
}
.footer{
margin-top:30px;
text-align:center;
color:#555;
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

.office-box{
position:relative;
margin-top:40px;
bottom:0;
left:0;
transform:none;
width:100%;
}
}

</style>
</head>

<body>

<div class="container">

<div class="left">

<div class="left-content">


<img src="image/emblem.jpg" class="logo">
<img src="image/gov.png?v=4" class="logo">


<h3>Divisional Secretariat Office</h3>

<div class="line"></div>

<h5>Queue & Appointment Management System</h5>

<div class="points">
• Efficient Service • Less Waiting • Better Experience
</div>

</div>

<div class="office-image">
    <img src="image/background.jpg" alt="Office">
</div>

<div class="office-box">

<div class="feature">
<i class="fas fa-users"></i>
<h4>Manage Queue</h4>
<p>Organized Service</p>
</div>

<div class="feature">
<i class="fas fa-calendar-check"></i>
<h4>Book Appointment</h4>
<p>Easy & Fast</p>
</div>

<div class="feature">
<i class="fas fa-shield-alt"></i>
<h4>Secure System</h4>
<p>Your data is safe</p>
</div>

</div>

</div>

<div class="right">

<div class="login-card">

<div class="icon-circle">
<i class="fas fa-user-lock"></i>
</div>

<div class="login-title">
<h1>Welcome Back!</h1>
<p>Please login to continue</p>
<div class="divider"></div>
</div>

<?php if (isset($_GET['error'])): ?>
<div class="alert alert-error" style="background-color: #fce8e6; color: #c53929; border: 1px solid #f8c9c4; padding: 15px; border-radius: 10px; margin-top: 15px; font-size: 16px; text-align: center;">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
</div>
<?php endif; ?>

<form action="login_process.php" method="POST">

<div class="input-group">
<div class="icon-box">
<i class="far fa-user"></i>
</div>
<input type="text" name="username" placeholder="Username" required>
</div>

<div class="input-group">
<div class="icon-box">
<i class="fas fa-lock"></i>
</div>
<input type="password" name="password" placeholder="Password" required>
</div>

<div class="input-group">
<div class="icon-box">
<i class="fas fa-building"></i>
</div>

<select name="user_type" required>
<option value="">Select User Type</option>
<option>Admin</option>
<option>Officer</option>
<option>Citizen</option>
</select>
</div>

<div class="options">
<label><input type="checkbox"> Remember me</label>
<a href="#">Forgot Password?</a>
</div>

<button class="login-btn">
<i class="fas fa-sign-in-alt"></i> Login
</button>

</form>

<div class="footer">
Need Help? Contact System Administrator
<br><br>
Are you a Citizen? <a href="register.php" style="color: #1560ff; text-decoration: none; font-weight: 600;">Register Here</a>
</div>

</div>

</div>

</div>

</body>
</html>