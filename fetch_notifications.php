<?php
session_start();
$conn = new mysqli("localhost", "root", "", "cnu_vpn");

$user = $_SESSION['user'];

$result = $conn->query("SELECT * FROM notifications WHERE user_id='$user' ORDER BY created_at DESC LIMIT 5");

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
?>