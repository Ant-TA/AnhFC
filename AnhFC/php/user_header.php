<?php
include 'dbconnection.php';

// Không kiểm tra session hay is_admin ở đây, để trang chính xử lý
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $headerUser = $result->fetch_assoc(); // Đổi tên biến $user thành $headerUser
    $stmt->close();

    // Tính số lượng món trong giỏ hàng
    $cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            background-image: url('../assets/BG.jpg');
            background-size: cover;
            background-attachment: fixed;
            margin: 0;
            padding: 0;
        }

        .navbar {
            background-color: #333;
            padding: 10px 0;
        }

        .navbar ul {
            list-style-type: none;
            margin: 0;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar li {
            position: relative;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
        }

        .navbar a:hover {
            background-color: #555;
        }

        .navbar .user-menu {
            margin-left: auto;
            display: flex;
            align-items: center;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #000;
            min-width: 120px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            z-index: 1;
            right: 0;
            border-radius: 5px;
        }

        .dropdown-content a {
            color: #fff;
            padding: 10px 15px;
            text-decoration: none;
            display: block;
        }

        .dropdown-content a:hover {
            background-color: #333;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .dropdown:hover .dropbtn {
            background-color: #555;
        }

        .dropbtn {
            cursor: pointer;
        }

        .cart-link {
            color: white;
            padding: 10px 15px;
            text-decoration: none;
        }

        .cart-link:hover {
            background-color: #555;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <ul>
            <li><a href="../index.php">Trang Chủ</a></li>
            <li><a href="menu.php">Danh Sách Món Ăn</a></li>
            <li class="user-menu">
                <?php
                if (isset($_SESSION['user_id'])) {
                    echo '<a href="user_cart.php" class="cart-link">Giỏ hàng (' . $cartCount . ')</a>';
                    echo '<div class="dropdown">';
                    echo '<a href="#" class="dropbtn">' . htmlspecialchars($headerUser['username']) . '</a>'; // Sử dụng $headerUser
                    echo '<div class="dropdown-content">';
                    echo '<a href="user_logout.php">Đăng Xuất</a>';
                    echo '</div>';
                    echo '</div>';
                } else {
                    echo '<a href="user_login.php">Đăng Nhập</a>';
                }
                ?>
            </li>
        </ul>
    </nav>
</body>
</html>