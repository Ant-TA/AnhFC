<?php
session_start();
include 'dbconnection.php';

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

// Lấy ID của banner từ URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: menu.php"); // Chuyển hướng nếu không có ID hợp lệ
    exit;
}

$bannerId = intval($_GET['id']);

// Lấy thông tin banner hiện tại
$query = "SELECT * FROM banners WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $bannerId);
$stmt->execute();
$result = $stmt->get_result();
$banner = $result->fetch_assoc();
$stmt->close();

if (!$banner) {
    header("Location: menu.php"); // Chuyển hướng nếu không tìm thấy banner
    exit;
}

// Lấy danh sách các banner khác (không bao gồm banner hiện tại) và chưa hết hạn
$currentDate = date('Y-m-d H:i:s');
$query = "SELECT * FROM banners WHERE id != ? AND (expiry_date IS NULL OR expiry_date > ?)";
$stmt = $conn->prepare($query);
$stmt->bind_param("is", $bannerId, $currentDate);
$stmt->execute();
$result = $stmt->get_result();
$otherBanners = $result->fetch_all(MYSQLI_ASSOC);
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
    <title>Chi Tiết Banner - <?php echo htmlspecialchars($banner['title']); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Lexend', sans-serif;
            background-image: url('../assets/BG.jpg');
            background-size: cover;
            background-attachment: fixed;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .banner-detail {
            background-color: #fff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .banner-detail h1 {
            font-size: 2.5rem;
            margin: 0 0 1rem;
            color: #333;
        }

        .banner-detail img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .banner-detail .description {
            font-size: 1.2rem;
            line-height: 1.6;
            color: #555;
            margin-bottom: 1rem;
        }

        .banner-detail .expiry-date {
            font-size: 1rem;
            color: #e74c3c; /* Màu đỏ để nổi bật */
            font-weight: 600;
        }

        .other-banners {
            margin-top: 2rem;
        }

        .other-banners h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #333;
        }

        .banner-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .banner-item {
            background-color: #fff;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .banner-item img {
            max-width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 0.5rem;
        }

        .banner-item h3 {
            font-size: 1.2rem;
            margin: 0.5rem 0;
            color: #333;
        }

        .banner-item p {
            font-size: 1rem;
            color: #555;
            margin: 0.5rem 0;
        }

        .banner-item a {
            display: inline-block;
            padding: 0.5rem 1rem;
            background-color: #333;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .banner-item a:hover {
            background-color: #555;
        }

        .no-banners {
            text-align: center;
            font-size: 1.2rem;
            color: #666;
            padding: 1rem;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Chi tiết banner -->
        <div class="banner-detail">
            <h1><?php echo htmlspecialchars($banner['title']); ?></h1>
            <img src="../images/<?php echo htmlspecialchars($banner['background']); ?>" alt="<?php echo htmlspecialchars($banner['title']); ?>">
            <div class="description"><?php echo nl2br(htmlspecialchars($banner['description'])); ?></div>
            <?php if (!empty($banner['expiry_date'])): ?>
                <div class="expiry-date">Khuyến mãi kết thúc: <?php echo date('d/m/Y H:i:s', strtotime($banner['expiry_date'])); ?></div>
            <?php endif; ?>
        </div>

        <!-- Danh sách các banner khác -->
        <div class="other-banners">
            <h2>Các Banner Khác</h2>
            <?php if (empty($otherBanners)): ?>
                <p class="no-banners">Không có banner nào khác!</p>
            <?php else: ?>
                <div class="banner-list">
                    <?php foreach ($otherBanners as $otherBanner): ?>
                        <div class="banner-item">
                            <img src="../images/<?php echo htmlspecialchars($otherBanner['background']); ?>" alt="<?php echo htmlspecialchars($otherBanner['title']); ?>">
                            <h3><?php echo htmlspecialchars($otherBanner['title']); ?></h3>
                            <p><?php echo htmlspecialchars(substr($otherBanner['description'], 0, 100)) . (strlen($otherBanner['description']) > 100 ? '...' : ''); ?></p>
                            <a href="banner_detail.php?id=<?php echo $otherBanner['id']; ?>">Xem Chi Tiết</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php include 'footer.php'; ?>