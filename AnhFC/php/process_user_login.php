<?php
session_start();
include 'dbconnection.php'; // Kết nối cơ sở dữ liệu

// Lấy dữ liệu từ biểu mẫu
$username = isset($_POST['username']) ? $_POST['username'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Kiểm tra tên đăng nhập trong bảng users
$sql = "SELECT * FROM users WHERE username = '$username'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    // Kiểm tra mật khẩu
    if (password_verify($password, $user['password'])) {
        // Đăng nhập thành công
        $_SESSION['user_loggedin'] = true;
        $_SESSION['username'] = $username;
        header("Location: user_dashboard.php");
        exit;
    } else {
        // Sai mật khẩu
        header("Location: user_login.php?error=1");
        exit;
    }
} else {
    // Sai tên đăng nhập
    header("Location: user_login.php?error=1");
    exit;
}

$conn->close();
?>