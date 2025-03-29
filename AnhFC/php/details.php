<?php
session_start();
include 'dbconnection.php';

// Ngăn cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Kiểm tra trạng thái đăng nhập
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Khởi tạo giỏ hàng nếu người dùng đã đăng nhập
if ($isLoggedIn) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Xử lý thêm vào giỏ hàng
    if (isset($_POST['add_to_cart'])) {
        $itemId = intval($_POST['item_id']);
        if (!isset($_SESSION['cart'][$itemId])) {
            $_SESSION['cart'][$itemId] = 1;
        } else {
            $_SESSION['cart'][$itemId]++;
        }
        header("Location: details.php?id=$itemId&added=1");
        exit;
    }

    // Xử lý gửi hoặc chỉnh sửa đánh giá
    if (isset($_POST['submit_rating'])) {
        $menuId = intval($_POST['menu_id']);
        $rating = intval($_POST['rating']);
        $description = trim($_POST['description']);
        $isEdit = isset($_POST['is_edit']) && $_POST['is_edit'] == 1;

        // Kiểm tra rating hợp lệ (1-5)
        if ($rating < 1 || $rating > 5) {
            $error = "Số sao phải từ 1 đến 5!";
        } else {
            if ($isEdit) {
                // Cập nhật đánh giá
                $stmt = $conn->prepare("UPDATE ratings SET rating = ?, description = ? WHERE user_id = ? AND menu_id = ?");
                $stmt->bind_param("isii", $rating, $description, $userId, $menuId);
            } else {
                // Thêm đánh giá mới
                $stmt = $conn->prepare("INSERT INTO ratings (user_id, menu_id, rating, description) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiis", $userId, $menuId, $rating, $description);
            }

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

                $avgRating = round($ratingData['avg_rating'], 2);
                $ratingCount = $ratingData['rating_count'];

                $stmt = $conn->prepare("UPDATE menu SET rating = ?, rating_count = ? WHERE id = ?");
                $stmt->bind_param("dii", $avgRating, $ratingCount, $menuId);
                $stmt->execute();
                $stmt->close();

                header("Location: details.php?id=$menuId&rated=1");
                exit;
            } else {
                $error = "Có lỗi xảy ra khi gửi đánh giá. Vui lòng thử lại!";
            }
        }
    }
}

// Kiểm tra và lấy ID món ăn từ URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);

    $sql = "SELECT * FROM menu WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
    } else {
        echo "<p style='text-align:center;'>Món ăn không tồn tại!</p>";
        include 'footer.php';
        exit;
    }
    $stmt->close();
} else {
    echo "<p style='text-align:center;'>Không tìm thấy ID món ăn!</p>";
    include 'footer.php';
    exit;
}

// Kiểm tra xem người dùng đã đặt hàng món này chưa (nếu đã đăng nhập)
$hasOrdered = false;
if ($isLoggedIn) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as order_count 
        FROM orders o 
        JOIN order_items oi ON o.id = oi.order_id 
        WHERE o.user_id = ? AND oi.item_id = ? AND o.status = 'Completed'
    ");
    $stmt->bind_param("ii", $userId, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $orderData = $result->fetch_assoc();
    if ($orderData['order_count'] > 0) {
        $hasOrdered = true;
    }
    $stmt->close();
}

// Kiểm tra xem người dùng đã đánh giá món này chưa và lấy thông tin đánh giá (nếu đã đăng nhập)
$hasRated = false;
$currentRating = null;
if ($isLoggedIn) {
    $stmt = $conn->prepare("SELECT rating, description FROM ratings WHERE user_id = ? AND menu_id = ?");
    $stmt->bind_param("ii", $userId, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $hasRated = true;
        $currentRating = $result->fetch_assoc();
    }
    $stmt->close();
}

// Lấy danh sách đánh giá của người dùng khác
$filterRating = isset($_GET['filter_rating']) ? intval($_GET['filter_rating']) : 0;
$ratings = [];
$query = "SELECT r.*, u.username 
          FROM ratings r 
          JOIN users u ON r.user_id = u.id 
          WHERE r.menu_id = ?";
if ($filterRating > 0 && $filterRating <= 5) {
    $query .= " AND r.rating = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $id, $filterRating);
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
}
$stmt->execute();
$result = $stmt->get_result();
while ($rating = $result->fetch_assoc()) {
    $ratings[] = $rating;
}
$stmt->close();
?>

<?php include 'user_header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Chi Tiết Món Ăn - <?= htmlspecialchars($row['combo_name']); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Lexend', sans-serif;
            background-image: url('../assets/BG.jpg');
            background-size: cover;
            background-attachment: fixed;
        }

        h1, h2, h3 {
            font-family: 'Lexend', sans-serif;
            font-weight: 600;
        }

        .details-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .details-container img {
            max-width: 100%;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .details-container h1 {
            font-size: 2em;
            color: #333;
            margin-bottom: 10px;
        }

        .details-container p {
            font-size: 1.2em;
            margin: 5px 0;
        }

        .details-container .price {
            font-size: 1.5em;
            color: #f39c12;
            font-weight: bold;
            margin-top: 15px;
        }

        .details-container .rating-stars {
            display: flex;
            align-items: center;
            font-size: 1.2em;
            margin: 10px 0;
        }

        .details-container .rating-stars span {
            margin-right: 2px;
        }

        .details-container button {
            padding: 10px 20px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 20px;
        }

        .details-container button:hover {
            background-color: #555;
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

        .rating-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .rating-section h2 {
            font-size: 1.5em;
            color: #333;
            margin-bottom: 15px;
        }

        .rating-section .not-allowed {
            color: #999;
            font-size: 1.1em;
            text-align: center;
        }

        .rating-section .already-rated {
            color: #666;
            font-size: 1.1em;
            text-align: center;
        }

        .rating-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .rating-form label {
            font-size: 1.1em;
            color: #333;
        }

        .rating-form textarea {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
            width: 100%;
            box-sizing: border-box;
            resize: vertical;
            min-height: 100px;
        }

        .rating-form button {
            align-self: flex-start;
            background-color: #28a745;
        }

        .rating-form button:hover {
            background-color: #218838;
        }

        .star-rating {
            display: flex;
            gap: 5px;
            font-size: 1.5em;
            cursor: pointer;
        }

        .star-rating .star {
            color: #ddd;
            transition: color 0.2s;
        }

        .star-rating .star.filled {
            color: gold;
        }

        .reviews-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .reviews-section h2 {
            font-size: 1.5em;
            color: #333;
            margin-bottom: 15px;
        }

        .filter-rating {
            margin-bottom: 20px;
        }

        .filter-rating label {
            font-size: 1.1em;
            margin-right: 10px;
        }

        .filter-rating select {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
        }

        .review-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }

        .review-item .username {
            font-weight: bold;
            color: #333;
        }

        .review-item .rating {
            display: flex;
            align-items: center;
            font-size: 1.1em;
            margin: 5px 0;
        }

        .review-item .rating span {
            margin-right: 2px;
        }

        .review-item .description {
            color: #666;
            font-size: 1em;
        }

        .review-item .date {
            color: #999;
            font-size: 0.9em;
            margin-top: 5px;
        }

        .no-reviews {
            text-align: center;
            color: #666;
            font-size: 1.1em;
        }

        .login-prompt {
            text-align: center;
            color: #666;
            font-size: 1.1em;
            margin-top: 20px;
        }

        .login-prompt a {
            color: #333;
            text-decoration: underline;
        }

        .login-prompt a:hover {
            color: #555;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const stars = document.querySelectorAll('.star-rating .star');
            const ratingInput = document.getElementById('rating-input');

            const initialRating = parseInt(ratingInput?.value) || 0;
            if (initialRating > 0) {
                stars.forEach((star, index) => {
                    if (index < initialRating) {
                        star.classList.add('filled');
                    }
                });
            }

            stars.forEach((star, index) => {
                star.addEventListener('mouseover', () => {
                    stars.forEach((s, i) => {
                        if (i <= index) {
                            s.classList.add('filled');
                        } else {
                            s.classList.remove('filled');
                        }
                    });
                });

                star.addEventListener('mouseout', () => {
                    stars.forEach((s, i) => {
                        if (i < (ratingInput?.value || 0)) {
                            s.classList.add('filled');
                        } else {
                            s.classList.remove('filled');
                        }
                    });
                });

                star.addEventListener('click', () => {
                    const value = index + 1;
                    ratingInput.value = value;
                    stars.forEach((s, i) => {
                        if (i < value) {
                            s.classList.add('filled');
                        } else {
                            s.classList.remove('filled');
                        }
                    });
                });
            });
        });
    </script>
</head>
<body>
    <div class="details-container">
        <?php
        if (isset($_GET['added']) && $_GET['added'] == 1) {
            echo "<p class='success-message'>Món ăn đã được thêm vào giỏ hàng!</p>";
        }
        if (isset($_GET['rated']) && $_GET['rated'] == 1) {
            echo "<p class='success-message'>Cảm ơn bạn đã gửi đánh giá!</p>";
        }
        if (isset($error)) {
            echo "<p class='error-message'>$error</p>";
        }
        ?>
        <img src="../images/<?= htmlspecialchars($row['image']); ?>" alt="<?= htmlspecialchars($row['combo_name']); ?>">
        <h1><?= htmlspecialchars($row['combo_name']); ?></h1>
        <p><?= htmlspecialchars($row['description']); ?></p>
        <p class="price">Giá: <?= number_format($row['price'], 0, ',', '.') ?> VND</p>

        <div class="rating-stars">
            <?php
            $rating = $row['rating'];
            $rating_count = $row['rating_count'];

            for ($i = 1; $i <= 5; $i++) {
                if ($rating >= $i) {
                    echo "<span style='color: gold;'>★</span>";
                } elseif ($rating >= $i - 0.5) {
                    echo "<span style='color: gold;'>☆</span>";
                } else {
                    echo "<span style='color: lightgray;'>★</span>";
                }
            }
            ?>
            <span>(<?= $rating_count ?> đánh giá)</span>
        </div>

        <?php if ($isLoggedIn): ?>
            <form method="POST" action="">
                <input type="hidden" name="item_id" value="<?= $row['id']; ?>">
                <button type="submit" name="add_to_cart">Thêm vào giỏ hàng</button>
            </form>
        <?php else: ?>
            <p class="login-prompt">Vui lòng <a href="user_login.php">đăng nhập</a> để thêm món vào giỏ hàng.</p>
        <?php endif; ?>

        <!-- Phần đánh giá -->
        <?php if ($isLoggedIn): ?>
            <div class="rating-section">
                <h2>Đánh Giá Món Ăn</h2>
                <?php if (!$hasOrdered): ?>
                    <p class="not-allowed">Bạn phải đặt hàng món này 1 lần để có thể thêm đánh giá.</p>
                <?php else: ?>
                    <form method="POST" action="" class="rating-form">
                        <input type="hidden" name="menu_id" value="<?= $row['id']; ?>">
                        <input type="hidden" name="rating" id="rating-input" value="<?= $hasRated ? $currentRating['rating'] : 0 ?>" required>
                        <?php if ($hasRated): ?>
                            <input type="hidden" name="is_edit" value="1">
                        <?php endif; ?>

                        <label>Chọn số sao:</label>
                        <div class="star-rating">
                            <span class="star" data-value="1">★</span>
                            <span class="star" data-value="2">★</span>
                            <span class="star" data-value="3">★</span>
                            <span class="star" data-value="4">★</span>
                            <span class="star" data-value="5">★</span>
                        </div>

                        <label for="description">Mô tả:</label>
                        <textarea name="description" id="description" placeholder="Nhập mô tả đánh giá của bạn..."><?= $hasRated ? htmlspecialchars($currentRating['description']) : '' ?></textarea>

                        <button type="submit" name="submit_rating"><?= $hasRated ? 'Cập Nhật Đánh Giá' : 'Gửi Đánh Giá' ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="rating-section">
                <h2>Đánh Giá Món Ăn</h2>
                <p class="login-prompt">Vui lòng <a href="user_login.php">đăng nhập</a> để gửi đánh giá.</p>
            </div>
        <?php endif; ?>

        <!-- Phần xem đánh giá -->
        <div class="reviews-section">
            <h2>Đánh Giá Từ Người Dùng Khác</h2>

            <!-- Bộ lọc theo số sao -->
            <div class="filter-rating">
                <label for="filter-rating">Lọc theo số sao:</label>
                <select id="filter-rating" onchange="location.href='details.php?id=<?= $id ?>&filter_rating=' + this.value">
                    <option value="0" <?= $filterRating == 0 ? 'selected' : '' ?>>Tất cả</option>
                    <option value="5" <?= $filterRating == 5 ? 'selected' : '' ?>>5 sao</option>
                    <option value="4" <?= $filterRating == 4 ? 'selected' : '' ?>>4 sao</option>
                    <option value="3" <?= $filterRating == 3 ? 'selected' : '' ?>>3 sao</option>
                    <option value="2" <?= $filterRating == 2 ? 'selected' : '' ?>>2 sao</option>
                    <option value="1" <?= $filterRating == 1 ? 'selected' : '' ?>>1 sao</option>
                </select>
            </div>

            <!-- Danh sách đánh giá -->
            <?php if (!empty($ratings)): ?>
                <?php foreach ($ratings as $review): ?>
                    <div class="review-item">
                        <p class="username"><?= htmlspecialchars($review['username']); ?></p>
                        <div class="rating">
                            <?php
                            for ($i = 1; $i <= 5; $i++) {
                                if ($review['rating'] >= $i) {
                                    echo "<span style='color: gold;'>★</span>";
                                } else {
                                    echo "<span style='color: lightgray;'>★</span>";
                                }
                            }
                            ?>
                        </div>
                        <p class="description"><?= htmlspecialchars($review['description']); ?></p>
                        <p class="date">Đăng vào: <?= date('d/m/Y H:i', strtotime($review['created_at'])); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-reviews">Chưa có đánh giá nào cho món ăn này!</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
include 'footer.php';
?>