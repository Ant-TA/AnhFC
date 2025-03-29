<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'dbconnection.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id'])) {
    header("Location: admin_login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || $user['is_admin'] != 1) {
    session_unset();
    session_destroy();
    header("Location: admin_login.php?error=1");
    exit;
}
?>