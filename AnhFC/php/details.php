<?php
session_start();
include 'dbconnection.php';

// Ngăn cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit;
}

// Kiểm tra quyền admin
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user['is_admin'] == 1) {
    session_unset();
    session_destroy();
    header("Location: user_login.php?error=2");
    exit;
}

// Khởi tạo giỏ hàng nếu chưa có
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Xử lý thêm vào giỏ hàng
if (isset($_POST['add_to_cart'])) {
    $itemId = intval($_POST['item_id']);
    if (!isset($_SESSION['cart'][$itemId])) {
        $_SESSION['cart'][$itemId] = 1; // Số lượng mặc định là 1
    } else {
        $_SESSION['cart'][$itemId]++; // Tăng số lượng nếu món đã có
    }
    // Chuyển hướng để tránh gửi lại form khi làm mới trang
    header("Location: details.php?id=$itemId&added=1");
    exit;
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
    </style>
</head>
<body>
    <div class="details-container">
        <?php
        if (isset($_GET['added']) && $_GET['added'] == 1) {
            echo "<p class='success-message'>Món ăn đã được thêm vào giỏ hàng!</p>";
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

        <form method="POST" action="">
            <input type="hidden" name="item_id" value="<?= $row['id']; ?>">
            <button type="submit" name="add_to_cart">Thêm vào giỏ hàng</button>
        </form>
    </div>
</body>
</html>

<?php
$conn->close();
include 'footer.php';
?>