<?php
session_start();
include 'dbconnection.php';

// Ngăn cache để tránh tự động đăng nhập lại
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

if ($user['is_admin'] != 1) {
    header("Location: ../index.php");
    exit;
}

// Xử lý thêm món ăn
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_menu'])) {
    $combo_name = $_POST['combo_name'];
    $description = $_POST['description'];
    $price = $_POST['price'];

    $image = '';
    if (!empty($_FILES['image']['name'])) {
        $image = $_FILES['image']['name'];
        $target = "../images/" . basename($image);
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
    }

    $stmt = $conn->prepare("INSERT INTO menu (combo_name, description, price, image, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssds", $combo_name, $description, $price, $image);
    $stmt->execute();
    $stmt->close();
}

// Xử lý xóa món ăn
if (isset($_GET['delete_id'])) {
    $deleteId = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM menu WHERE id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_menu.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Thêm meta tag chống cache -->
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Quản Lý Menu</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Lexend', sans-serif;
            background-image: url('../assets/BG.jpg');
            background-size: cover;
            background-attachment: fixed;
        }

        h1, h2 {
            font-family: 'Lexend', sans-serif;
            font-weight: 600;
        }

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

        .add-form {
            margin-bottom: 30px;
        }

        .add-form label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .add-form input, .add-form textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
            font-family: 'Lexend', sans-serif;
        }

        .add-form textarea {
            min-height: 100px; /* Chiều cao tối thiểu */
            resize: none; /* Xóa khả năng chỉnh kích thước bằng tay */
            overflow: hidden; /* Ẩn thanh cuộn khi không cần */
        }

        .add-form input[type="number"] {
            -webkit-appearance: none; /* Xóa mũi tên trên Chrome/Safari */
            -moz-appearance: textfield; /* Xóa mũi tên trên Firefox */
            appearance: none; /* Xóa mũi tên trên các trình duyệt khác */
        }

        .add-form input[type="number"]::-webkit-inner-spin-button,
        .add-form input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none; /* Xóa mũi tên trên Chrome/Safari */
            margin: 0;
        }

        .add-form button {
            width: 100%;
            padding: 10px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Lexend', sans-serif;
        }

        .add-form button:hover {
            background-color: #555;
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
            font-family: 'Lexend', sans-serif;
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
            margin: 0 5px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-family: 'Lexend', sans-serif;
        }

        .action-btn:hover {
            background-color: #555;
        }

        .delete-btn {
            background-color: #d9534f;
        }

        .delete-btn:hover {
            background-color: #c9302c;
        }
    </style>
    <script>
        // Tự động điều chỉnh chiều cao textarea khi nhập
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.querySelector('.add-form textarea');
            textarea.addEventListener('input', function() {
                this.style.height = 'auto'; // Reset chiều cao
                this.style.height = this.scrollHeight + 'px'; // Điều chỉnh theo nội dung
            });
        });
    </script>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    <div class="admin-container">
        <h1>Quản Lý Menu</h1>

        <!-- Form thêm món ăn -->
        <div class="add-form">
            <h2>Thêm Món Ăn</h2>
            <form action="" method="POST" enctype="multipart/form-data">
                <label for="combo_name">Tên Combo:</label>
                <input type="text" name="combo_name" id="combo_name" required>

                <label for="description">Mô Tả:</label>
                <textarea name="description" id="description"></textarea>

                <label for="price">Giá (VND):</label>
                <input type="number" name="price" id="price" required>

                <label for="image">Hình Ảnh:</label>
                <input type="file" name="image" id="image" required>

                <button type="submit" name="add_menu">Thêm Món Ăn</button>
            </form>
        </div>

        <!-- Danh sách món ăn -->
        <h2>Danh Sách Món Ăn</h2>
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
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['combo_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                    echo "<td>" . number_format($row['price'], 0, ',', '.') . " VND</td>";
                    echo "<td><img src='../images/" . htmlspecialchars($row['image']) . "' alt='" . htmlspecialchars($row['combo_name']) . "' style='max-width: 100px;'></td>";
                    echo "<td>";
                    echo "<a href='edit_menu.php?id=" . $row['id'] . "' class='action-btn'>Sửa</a>";
                    echo "<a href='admin_menu.php?delete_id=" . $row['id'] . "' class='action-btn delete-btn' onclick='return confirm(\"Bạn có chắc muốn xóa món này?\");'>Xóa</a>";
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6'>Không có món ăn nào!</td></tr>";
            }
            ?>
        </table>
    </div>
    <?php
    $conn->close();
    ?>
</body>
</html>