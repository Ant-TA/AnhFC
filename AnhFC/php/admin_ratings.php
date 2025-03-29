<?php
session_start();
include 'dbconnection.php'; // Sửa đường dẫn từ '../dbconnection.php' thành 'dbconnection.php'

// Ngăn cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

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
    session_unset();
    session_destroy();
    header("Location: user_login.php?error=3");
    exit;
}

// Xử lý xóa đánh giá
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $ratingId = intval($_GET['delete_id']);

    // Lấy menu_id trước khi xóa để cập nhật rating
    $stmt = $conn->prepare("SELECT menu_id FROM ratings WHERE id = ?");
    $stmt->bind_param("i", $ratingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rating = $result->fetch_assoc();
    $menuId = $rating['menu_id'];
    $stmt->close();

    // Xóa đánh giá
    $stmt = $conn->prepare("DELETE FROM ratings WHERE id = ?");
    $stmt->bind_param("i", $ratingId);
    if ($stmt->execute()) {
        // Cập nhật rating và rating_count trong bảng menu
        $stmt = $conn->prepare("
            SELECT AVG(rating) as avg_rating, COUNT(*) as rating_count 
            FROM ratings 
            WHERE menu_id = ?
        ");
        $stmt->bind_param("i", $menuId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ratingData = $result->fetch_assoc();

        $avgRating = $ratingData['rating_count'] > 0 ? round($ratingData['avg_rating'], 2) : 0;
        $ratingCount = $ratingData['rating_count'];

        $stmt = $conn->prepare("UPDATE menu SET rating = ?, rating_count = ? WHERE id = ?");
        $stmt->bind_param("dii", $avgRating, $ratingCount, $menuId);
        $stmt->execute();
        $stmt->close();

        header("Location: admin_ratings.php?deleted=1");
        exit;
    } else {
        $error = "Có lỗi xảy ra khi xóa đánh giá. Vui lòng thử lại!";
    }
}

// Lấy danh sách đánh giá gần nhất
$ratings = [];
$stmt = $conn->prepare("
    SELECT r.id, r.rating, r.description, r.created_at, u.username, m.combo_name 
    FROM ratings r 
    JOIN users u ON r.user_id = u.id 
    JOIN menu m ON r.menu_id = m.id 
    ORDER BY r.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($rating = $result->fetch_assoc()) {
    $ratings[] = $rating;
}
$stmt->close();
?>

<?php include 'admin_header.php'; // Thêm admin_header.php ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Quản Lý Đánh Giá</title>
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
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .success-message {
            color: green;
            text-align: center;
            margin-bottom: 15px;
        }

        .error-message {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table th, table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        table th {
            background-color: #f5f5f5;
            font-weight: 600;
        }

        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .rating-stars {
            display: flex;
            align-items: center;
            font-size: 1.1em;
        }

        .rating-stars span {
            margin-right: 2px;
        }

        .delete-btn {
            padding: 5px 10px;
            background-color: #e74c3c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .delete-btn:hover {
            background-color: #c0392b;
        }

        .no-ratings {
            text-align: center;
            color: #666;
            font-size: 1.2em;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Quản Lý Đánh Giá</h1>

        <?php
        if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
            echo "<p class='success-message'>Đánh giá đã được xóa thành công!</p>";
        }
        if (isset($error)) {
            echo "<p class='error-message'>$error</p>";
        }
        ?>

        <?php if (!empty($ratings)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Món Ăn</th>
                        <th>Người Dùng</th>
                        <th>Số Sao</th>
                        <th>Mô Tả</th>
                        <th>Ngày Đăng</th>
                        <th>Hành Động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ratings as $rating): ?>
                        <tr>
                            <td><?= htmlspecialchars($rating['combo_name']); ?></td>
                            <td><?= htmlspecialchars($rating['username']); ?></td>
                            <td>
                                <div class="rating-stars">
                                    <?php
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($rating['rating'] >= $i) {
                                            echo "<span style='color: gold;'>★</span>";
                                        } else {
                                            echo "<span style='color: lightgray;'>★</span>";
                                        }
                                    }
                                    ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($rating['description']); ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($rating['created_at'])); ?></td>
                            <td>
                                <a href="admin_ratings.php?delete_id=<?= $rating['id']; ?>" class="delete-btn" onclick="return confirm('Bạn có chắc chắn muốn xóa đánh giá này?');">Xóa</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-ratings">Chưa có đánh giá nào!</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$conn->close();
?>