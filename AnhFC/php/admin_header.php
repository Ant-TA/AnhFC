<?php
include 'dbconnection.php';

$username = '';
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $username = $user['username'] ?? '';
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
            justify-content: flex-start;
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
        }

        .navbar a:hover {
            background-color: #555;
        }

        .navbar .user-menu {
            margin-left: auto;
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
    </style>
</head>
<body>
    <nav class="navbar">
        <ul>
            <li><a href="admin_home.php">Trang Chủ Admin</a></li>
            <li><a href="admin_menu.php">Quản Lý Menu</a></li>
            <li class="user-menu">
                <?php if (isset($_SESSION['user_id']) && !empty($username)): ?>
                    <div class="dropdown">
                        <a href="#" class="dropbtn"><?php echo htmlspecialchars($username); ?></a>
                        <div class="dropdown-content">
                            <a href="admin_logout.php">Đăng Xuất</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="admin_login.php">Đăng Nhập</a>
                <?php endif; ?>
            </li>
        </ul>
    </nav>
</body>
</html>