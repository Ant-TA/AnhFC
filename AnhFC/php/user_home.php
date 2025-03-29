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

// Lấy danh sách danh mục và số lượng món ăn trong mỗi danh mục
$categories = [];
$stmt = $conn->prepare("
    SELECT c.id, c.name, COUNT(mc.menu_id) as item_count 
    FROM categories c 
    LEFT JOIN menu_categories mc ON c.id = mc.category_id 
    GROUP BY c.id, c.name 
    ORDER BY c.name ASC
");
$stmt->execute();
$result = $stmt->get_result();
while ($category = $result->fetch_assoc()) {
    $categories[] = $category;
}
$stmt->close();

// Lấy món ăn HOT (dựa trên số lượng món trong các đơn hàng đã hoàn tất)
$hotItems = [];
$stmt = $conn->prepare("
    SELECT m.*, SUM(oi.quantity) as total_quantity 
    FROM menu m 
    JOIN order_items oi ON m.id = oi.item_id 
    JOIN orders o ON oi.order_id = o.id 
    WHERE o.status = 'Completed' 
    GROUP BY m.id 
    ORDER BY total_quantity DESC 
    LIMIT 4
");
$stmt->execute();
$result = $stmt->get_result();
while ($item = $result->fetch_assoc()) {
    // Lấy danh mục của món
    $stmt_cat = $conn->prepare("SELECT c.name FROM categories c JOIN menu_categories mc ON c.id = mc.category_id WHERE mc.menu_id = ?");
    $stmt_cat->bind_param("i", $item['id']);
    $stmt_cat->execute();
    $cat_result = $stmt_cat->get_result();
    $item['categories'] = [];
    while ($cat = $cat_result->fetch_assoc()) {
        $item['categories'][] = $cat['name'];
    }
    $stmt_cat->close();
    $hotItems[] = $item;
}
$stmt->close();

// Lấy món ăn khuyến mãi (thuộc danh mục "Khuyến mãi")
$promoItems = [];
$stmt = $conn->prepare("
    SELECT m.* 
    FROM menu m 
    JOIN menu_categories mc ON m.id = mc.menu_id 
    JOIN categories c ON mc.category_id = c.id 
    WHERE c.name = 'Khuyến mãi' 
    LIMIT 4
");
$stmt->execute();
$result = $stmt->get_result();
while ($item = $result->fetch_assoc()) {
    // Lấy danh mục của món
    $stmt_cat = $conn->prepare("SELECT c.name FROM categories c JOIN menu_categories mc ON c.id = mc.category_id WHERE mc.menu_id = ?");
    $stmt_cat->bind_param("i", $item['id']);
    $stmt_cat->execute();
    $cat_result = $stmt_cat->get_result();
    $item['categories'] = [];
    while ($cat = $cat_result->fetch_assoc()) {
        $item['categories'][] = $cat['name'];
    }
    $stmt_cat->close();
    $promoItems[] = $item;
}
$stmt->close();

// Lấy voucher công khai và đang khả dụng
$vouchers = [];
$currentDate = date('Y-m-d H:i:s');
$stmt = $conn->prepare("
    SELECT * 
    FROM vouchers 
    WHERE is_public = 1 
    AND (expiry_date IS NULL OR expiry_date >= ?) 
    AND (quantity IS NULL OR quantity > 0)
");
$stmt->bind_param("s", $currentDate);
$stmt->execute();
$result = $stmt->get_result();
while ($voucher = $result->fetch_assoc()) {
    $vouchers[] = $voucher;
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
    <title>Trang Chủ</title>
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
            color: #333;
            margin: 1.5rem 0;
        }

        .section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .content-block {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 1rem;
            margin-bottom: 2rem;
        }

        .content-block h2 {
            text-align: left;
            font-size: 2rem;
            padding: 0 1rem;
            margin: 0 0 1rem 0;
        }

        .category-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            padding: 1rem;
        }

        .category-item {
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 1rem;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: transform 0.3s;
        }

        .category-item:hover {
            transform: scale(1.05);
        }

        .category-item h3 {
            font-size: 1.2rem;
            margin: 0.5rem 0;
        }

        .category-item p {
            font-size: 0.9rem;
            color: #666;
            margin: 0;
        }

        .item-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, 18rem);
            gap: 1.5rem;
            padding: 1rem;
            justify-content: center;
        }

        .menu-item {
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 1rem;
            background-color: #fff;
            width: 18rem;
            box-sizing: border-box;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .menu-item img {
            max-width: 100%;
            height: 12rem;
            object-fit: cover;
            border-radius: 5px;
        }

        .menu-item h3 {
            font-size: 1.2rem;
            margin: 0.5rem 0;
        }

        .menu-item p {
            font-size: 1rem;
            margin: 0.5rem 0;
        }

        .menu-item .category {
            font-size: 0.9rem;
            color: #666;
            margin: 0.3rem 0;
        }

        .menu-item button {
            margin: 0.5rem 0.25rem;
            padding: 0.6rem 1.2rem;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .menu-item button:hover {
            background-color: #555;
        }

        .rating-stars {
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1rem;
            margin: 0.5rem 0;
        }

        .rating-stars span {
            margin-right: 0.3rem;
        }

        .voucher-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            padding: 1rem;
        }

        .voucher-item {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 1rem;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .voucher-item h3 {
            font-size: 1.2rem;
            margin: 0.5rem 0;
            color: #e74c3c;
        }

        .voucher-item p {
            font-size: 0.9rem;
            margin: 0.3rem 0;
            color: #666;
        }

        .no-items {
            text-align: center;
            font-size: 1.2rem;
            color: #666;
            padding: 1rem;
            background-color: #ffffff;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
            max-width: 300px;
        }

        .login-prompt {
            text-align: center;
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .login-prompt a {
            color: #333;
            text-decoration: underline;
        }

        .login-prompt a:hover {
            color: #555;
        }

        @media (max-width: 60rem) {
            .item-list {
                grid-template-columns: repeat(auto-fit, 15rem);
            }

            .menu-item {
                width: 15rem;
            }

            .content-block h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

    <?php include 'banner_slider.php'; ?>

    <div class="section">
        <!-- Danh mục -->
        <div class="content-block">
            <h2>Danh Mục</h2>
            <div class="category-list">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $category): ?>
                        <div class="category-item" onclick="location.href='menu.php?categories=<?php echo $category['id']; ?>'">
                            <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                            <p><?php echo $category['item_count']; ?> món</p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-items">Không có danh mục nào!</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Món ăn HOT -->
        <div class="content-block">
            <h2>Món Ăn HOT</h2>
            <div class="item-list">
                <?php if (!empty($hotItems)): ?>
                    <?php foreach ($hotItems as $item): ?>
                        <div class="menu-item">
                            <h3><?php echo htmlspecialchars($item['combo_name']); ?></h3>
                            <p class="category"><?php echo htmlspecialchars(implode(", ", $item['categories'])); ?></p>
                            <p><?php echo htmlspecialchars($item['description']); ?></p>
                            <p>Giá: <?php echo number_format($item['price'], 0, ',', '.'); ?> VND</p>
                            <?php if (!empty($item['image'])): ?>
                                <img src="../images/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['combo_name']); ?>">
                            <?php endif; ?>
                            <div class="rating-stars">
                                <?php
                                $rating = $item['rating'];
                                $rating_count = $item['rating_count'];
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($rating >= $i) {
                                        echo "<span style='color: gold;'>★</span>";
                                    } elseif ($rating >= $i - 0.5) {
                                        echo "<span style='color: gold;'>☆</span>";
                                    } else {
                                        echo "<span style='color: lightgray;'>★</span>";
                                    }
                                }
                                echo " <span>(" . $rating_count . " đánh giá)</span>";
                                ?>
                            </div>
                            <div>
                                <button onclick="location.href='details.php?id=<?php echo $item['id']; ?>'">Xem thêm</button>
                                <?php if ($isLoggedIn): ?>
                                    <form method="POST" action="menu.php" style="display:inline;">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="add_to_cart">Thêm vào giỏ hàng</button>
                                    </form>
                                <?php else: ?>
                                    <p class="login-prompt"><a href="user_login.php">Đăng nhập</a> để thêm vào giỏ hàng</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-items">Chưa có món ăn HOT nào!</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Món ăn khuyến mãi -->
        <div class="content-block">
            <h2>Món Ăn Khuyến Mãi</h2>
            <div class="item-list">
                <?php if (!empty($promoItems)): ?>
                    <?php foreach ($promoItems as $item): ?>
                        <div class="menu-item">
                            <h3><?php echo htmlspecialchars($item['combo_name']); ?></h3>
                            <p class="category"><?php echo htmlspecialchars(implode(", ", $item['categories'])); ?></p>
                            <p><?php echo htmlspecialchars($item['description']); ?></p>
                            <p>Giá: <?php echo number_format($item['price'], 0, ',', '.'); ?> VND</p>
                            <?php if (!empty($item['image'])): ?>
                                <img src="../images/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['combo_name']); ?>">
                            <?php endif; ?>
                            <div class="rating-stars">
                                <?php
                                $rating = $item['rating'];
                                $rating_count = $item['rating_count'];
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($rating >= $i) {
                                        echo "<span style='color: gold;'>★</span>";
                                    } elseif ($rating >= $i - 0.5) {
                                        echo "<span style='color: gold;'>☆</span>";
                                    } else {
                                        echo "<span style='color: lightgray;'>★</span>";
                                    }
                                }
                                echo " <span>(" . $rating_count . " đánh giá)</span>";
                                ?>
                            </div>
                            <div>
                                <button onclick="location.href='details.php?id=<?php echo $item['id']; ?>'">Xem thêm</button>
                                <?php if ($isLoggedIn): ?>
                                    <form method="POST" action="menu.php" style="display:inline;">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="add_to_cart">Thêm vào giỏ hàng</button>
                                    </form>
                                <?php else: ?>
                                    <p class="login-prompt"><a href="user_login.php">Đăng nhập</a> để thêm vào giỏ hàng</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-items">Chưa có món ăn khuyến mãi nào!</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Voucher khả dụng -->
        <div class="content-block">
            <h2>Voucher Khả Dụng</h2>
            <div class="voucher-list">
                <?php if (!empty($vouchers)): ?>
                    <?php foreach ($vouchers as $voucher): ?>
                        <div class="voucher-item">
                            <h3><?php echo htmlspecialchars($voucher['code']); ?></h3>
                            <?php if ($voucher['discount_percent']): ?>
                                <p>Giảm <?php echo $voucher['discount_percent']; ?>% (Tối đa <?php echo number_format($voucher['max_discount_value'], 0, ',', '.'); ?> VND)</p>
                            <?php else: ?>
                                <p>Giảm <?php echo number_format($voucher['fixed_discount'], 0, ',', '.'); ?> VND</p>
                            <?php endif; ?>
                            <?php if ($voucher['min_order_value']): ?>
                                <p>Đơn tối thiểu: <?php echo number_format($voucher['min_order_value'], 0, ',', '.'); ?> VND</p>
                            <?php endif; ?>
                            <?php if ($voucher['expiry_date']): ?>
                                <p>Hết hạn: <?php echo date('d/m/Y', strtotime($voucher['expiry_date'])); ?></p>
                            <?php endif; ?>
                            <?php if ($voucher['quantity']): ?>
                                <p>Còn lại: <?php echo $voucher['quantity']; ?> voucher</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-items">Không có voucher khả dụng!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>

<?php
$conn->close();
include 'footer.php';
?>