<?php
session_start();

$user = $_SESSION['user'];

$conn = new mysqli("localhost", "root", "", "cnu_vpn");

// LOGOUT NOTIFICATION
$msg = "User $user logged out";
$conn->query("INSERT INTO notifications (user_id, message) VALUES ('$user', '$msg')");

session_destroy();

header("Location: index.php");
exit();
?>