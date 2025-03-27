<?php
session_start();

// Xóa tất cả dữ liệu session
session_unset();
session_destroy();

// Ngăn cache trang trước đó
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Chuyển hướng về trang đăng nhập
header("Location: user_login.php");
exit;
?>