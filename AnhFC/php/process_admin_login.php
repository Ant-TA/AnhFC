<?php
session_start();
include 'dbconnection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password, is_admin FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password']) && $user['is_admin'] == 1) {
        $_SESSION['user_id'] = $user['id'];
        header("Location: admin_home.php");
        exit;
    } else {
        header("Location: admin_login.php?error=1");
        exit;
    }

    $stmt->close();
}
$conn->close();
?>