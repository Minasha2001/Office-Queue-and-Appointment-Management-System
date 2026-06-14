<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "office_queue_system";

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if(!$conn){
    die("Database Connection Failed: " . mysqli_connect_error());
}

?>