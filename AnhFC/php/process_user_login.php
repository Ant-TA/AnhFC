<?php
session_start();
include 'dbconnection.php';

// Ngăn cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    // Truy vấn kiểm tra người dùng
    $stmt = $conn->prepare("SELECT id, username, password, is_admin FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
        // Kiểm tra nếu là admin
        if ($user['is_admin'] == 1) {
            // Không cho đăng nhập, hiển thị lỗi như sai thông tin
            header("Location: user_login.php?error=1");
            exit;
        } else {
            // Đăng nhập thành công cho user thường
            $_SESSION['user_id'] = $user['id'];
            header("Location: menu.php");
            exit;
        }
    } else {
        // Sai tên đăng nhập hoặc mật khẩu
        header("Location: user_login.php?error=1");
        exit;
    }
}

$conn->close();
?>