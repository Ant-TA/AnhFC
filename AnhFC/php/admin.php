<?php
session_start();
include 'dbconnection.php';

// Kiểm tra đăng nhập và quyền admin
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user['is_admin'] != 1) {
    header("Location: ../index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .admin-container {
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        h1 {
            text-align: center;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }

        th {
            background-color: #333;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .action-btn {
            padding: 5px 10px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .action-btn:hover {
            background-color: #555;
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    <div class="admin-container">
        <h1>Quản Lý Menu</h1>
        <table>
            <tr>
                <th>ID</th>
                <th>Tên Combo</th>
                <th>Mô Tả</th>
                <th>Giá</th>
                <th>Hình Ảnh</th>
                <th>Hành Động</th>
            </tr>
            <?php
            $result = $conn->query("SELECT * FROM menu");
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['combo_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                    echo "<td>" . number_format($row['price'], 0, ',', '.') . " VND</td>";
                    echo "<td><img src='../images/" . htmlspecialchars($row['image']) . "' alt='" . htmlspecialchars($row['combo_name']) . "' style='max-width: 100px;'></td>";
                    echo "<td><a href='edit_menu.php?id=" . $row['id'] . "' class='action-btn'>Chỉnh Sửa</a></td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6'>Không có món ăn nào!</td></tr>";
            }
            $conn->close();
            ?>
        </table>
    </div>
</body>
</html>