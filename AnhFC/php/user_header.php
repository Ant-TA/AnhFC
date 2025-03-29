<?php
// Kiểm tra nếu chưa có session thì khởi tạo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra trạng thái đăng nhập
$isLoggedIn = isset($_SESSION['user_id']);

// Nếu đã đăng nhập, lấy thông tin người dùng và số lượng món trong giỏ hàng
if ($isLoggedIn) {
    include 'dbconnection.php';
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $headerUser = $result->fetch_assoc();
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
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Lexend', sans-serif;
        }

        .navbar {
            background-color: #333;
            padding: 10px 0;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
        }

        .navbar .container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .navbar ul {
            list-style-type: none;
            margin: 0;
            padding: 0;
            display: flex;
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
            font-family: 'Lexend', sans-serif;
            font-size: 1em;
        }

        .navbar a:hover {
            background-color: #555;
            border-radius: 5px;
        }

        .nav-left {
            display: flex;
            align-items: center;
        }

        .nav-right {
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
            z-index: 1001;
            right: 0;
            border-radius: 5px;
        }

        .dropdown-content.active {
            display: block;
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

        .dropbtn {
            cursor: pointer;
            color: white;
            padding: 10px 15px;
            display: block;
            font-family: 'Lexend', sans-serif;
            font-size: 1em;
        }

        .dropbtn:hover {
            background-color: #555;
            border-radius: 5px;
        }

        .cart-link {
            color: white;
            padding: 10px 15px;
            text-decoration: none;
        }

        .cart-link:hover {
            background-color: #555;
            border-radius: 5px;
        }

        .logout-link {
            background-color: #e74c3c;
            border-radius: 5px;
        }

        .logout-link:hover {
            background-color: #c0392b;
        }

        .login-link {
            color: white;
            padding: 10px 15px;
            text-decoration: none;
        }

        .login-link:hover {
            background-color: #555;
            border-radius: 5px;
        }

        /* Đảm bảo nội dung không bị che bởi header cố định */
        body {
            padding-top: 60px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <!-- Bên trái: Trang Chủ, Thực Đơn -->
            <ul class="nav-left">
                <li><a href="user_home.php">Trang Chủ</a></li>
                <li><a href="menu.php">Thực Đơn</a></li>
            </ul>

            <!-- Bên phải: Giỏ Hàng, Tên người dùng (nếu đã đăng nhập) hoặc Đăng Nhập (nếu chưa đăng nhập) -->
            <ul class="nav-right">
                <?php if ($isLoggedIn): ?>
                    <li><a href="user_cart.php" class="cart-link">Giỏ Hàng (<?php echo $cartCount; ?>)</a></li>
                    <li>
                        <div class="dropdown">
                            <span class="dropbtn"><?php echo htmlspecialchars($headerUser['username']); ?></span>
                            <div class="dropdown-content">
                                <a href="user_profile.php">Quản Lý Thông Tin</a>
                                <a href="user_logout.php" class="logout-link">Đăng Xuất</a>
                            </div>
                        </div>
                    </li>
                <?php else: ?>
                    <li><a href="user_login.php" class="login-link">Đăng Nhập</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropbtn = document.querySelector('.dropbtn');
            const dropdownContent = document.querySelector('.dropdown-content');

            // Kiểm tra nếu dropbtn tồn tại (chỉ khi người dùng đã đăng nhập)
            if (dropbtn && dropdownContent) {
                // Toggle dropdown khi bấm vào tên user
                dropbtn.addEventListener('click', function(event) {
                    event.stopPropagation();
                    dropdownContent.classList.toggle('active');
                });

                // Ẩn dropdown khi bấm ra ngoài
                document.addEventListener('click', function(event) {
                    if (!dropbtn.contains(event.target) && !dropdownContent.contains(event.target)) {
                        dropdownContent.classList.remove('active');
                    }
                });
            }
        });
    </script>
</body>
</html>