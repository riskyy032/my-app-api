<?php

$host = "localhost";
$username = "root"; // default in XAMPP
$password = "";     // default is empty
$dbname = "compassdb";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
