<?php
session_start();
$conn = new mysqli("localhost", "root", "", "cnu_vpn");

$student_id = $_POST['student_id'];
$password = md5($_POST['password']);

$sql = "SELECT * FROM users WHERE student_id='$student_id' AND password='$password'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $_SESSION['user'] = $student_id;

    // ADD NOTIFICATION
    $msg = "VPN session started for $student_id";
    $conn->query("INSERT INTO notifications (user_id, message) VALUES ('$student_id', '$msg')");

    echo "success";
} else {
    echo "error";
}
?>