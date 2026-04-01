<?php
session_start();
$conn = new mysqli("localhost", "root", "", "cnu_vpn");

$user = $_SESSION['user'];

$conn->query("UPDATE notifications SET is_read=1 WHERE user_id='$user'");
echo "done";
?>