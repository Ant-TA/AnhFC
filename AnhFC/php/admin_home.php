<?php
include 'check_admin.php';

// Lấy số lượng món ăn từ bảng menu
$stmt = $conn->prepare("SELECT COUNT(*) as total_items FROM menu");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$totalItems = $row['total_items'];
$stmt->close();

// Lấy số lượng voucher đang có hiệu lực
$currentDate = date('Y-m-d H:i:s');
$stmt = $conn->prepare("SELECT COUNT(*) as total_vouchers FROM vouchers WHERE (expiry_date IS NULL OR expiry_date > ?) AND (quantity IS NULL OR quantity > 0)");
$stmt->bind_param("s", $currentDate);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$totalVouchers = $row['total_vouchers'];
$stmt->close();

// Lấy số lượng banner đang chạy quảng cáo
$stmt = $conn->prepare("SELECT COUNT(*) as total_banners FROM banners WHERE expiry_date IS NULL OR expiry_date > ?");
$stmt->bind_param("s", $currentDate);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$totalBanners = $row['total_banners'];
$stmt->close();

// Lấy số lượng đơn hàng đang xử lý (status = 'Pending')
$stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM orders WHERE status = 'Pending'");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$pendingOrders = $row['pending_count'];
$stmt->close();

// Lấy số lượng người dùng (không tính admin)
$stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM users WHERE is_admin = 0");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$totalUsers = $row['total_users'];
$stmt->close();

// Lấy số lượng đơn hàng đã hoàn thành trong tháng hiện tại
$currentMonth = date('m');
$currentYear = date('Y');
$stmt = $conn->prepare("SELECT COUNT(*) as completed_orders 
                        FROM orders 
                        WHERE status = 'Completed' 
                        AND MONTH(order_date) = ? 
                        AND YEAR(order_date) = ?");
$stmt->bind_param("ii", $currentMonth, $currentYear);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$completedOrdersThisMonth = $row['completed_orders'];
$stmt->close();

// Lấy tổng số đánh giá
$stmt = $conn->prepare("SELECT COUNT(*) as total_ratings FROM ratings");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$totalRatings = $row['total_ratings'];
$stmt->close();
?>

<?php include 'admin_header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Trang Chủ Admin</title>
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

        .admin-container {
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #333;
        }

        .dashboard {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
        }

        .dashboard-item {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            width: 300px;
            text-align: center;
            transition: transform 0.2s;
            position: relative;
        }

        .dashboard-item:hover {
            transform: scale(1.05);
        }

        .dashboard-item .number {
            font-size: 2.5em;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        .dashboard-item .label {
            font-size: 1.2em;
            color: #666;
        }

        .dashboard-item .action {
            margin-top: 10px;
        }

        .dashboard-item .action a {
            color: #333;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
        }

        .dashboard-item .action a:hover {
            color: #555;
        }

        .dashboard-item .badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 5px 10px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1>Trang Chủ Admin</h1>
        <div class="dashboard">
            <!-- Số lượng món ăn -->
            <div class="dashboard-item">
                <div class="number"><?php echo $totalItems; ?></div>
                <div class="label">Số lượng món ăn hiện tại</div>
                <div class="action">
                    <a href="admin_menu.php">Quản Lí Thực Đơn</a>
                </div>
            </div>

            <!-- Số lượng voucher đang có hiệu lực -->
            <div class="dashboard-item">
                <div class="number"><?php echo $totalVouchers; ?></div>
                <div class="label">Số lượng voucher đang có hiệu lực</div>
                <div class="action">
                    <a href="voucher_manager.php">Quản Lí Voucher</a>
                </div>
            </div>

            <!-- Số lượng banner đang chạy quảng cáo -->
            <div class="dashboard-item">
                <div class="number"><?php echo $totalBanners; ?></div>
                <div class="label">Số lượng banner đang chạy quảng cáo</div>
                <div class="action">
                    <a href="manage_banners.php">Quản Lí Banner</a>
                </div>
            </div>

            <!-- Số lượng đơn hàng đang xử lý -->
            <div class="dashboard-item">
                <div class="number"><?php echo $pendingOrders; ?></div>
                <div class="label">Đơn hàng đang xử lý</div>
                <div class="action">
                    <a href="admin_order_manager.php">Quản Lý Đơn Hàng</a>
                </div>
                <?php if ($pendingOrders > 0): ?>
                    <span class="badge"><?php echo $pendingOrders; ?></span>
                <?php endif; ?>
            </div>

            <!-- Số lượng người dùng -->
            <div class="dashboard-item">
                <div class="number"><?php echo $totalUsers; ?></div>
                <div class="label">Số lượng người dùng</div>
                <div class="action">
                    <a href="admin_user_manager.php">Quản Lý Người Dùng</a>
                </div>
            </div>

            <!-- Số lượng đơn hàng đã hoàn thành trong tháng -->
            <div class="dashboard-item">
                <div class="number"><?php echo $completedOrdersThisMonth; ?></div>
                <div class="label">Số đơn hàng đã hoàn thành trong tháng <?php echo $currentMonth; ?></div>
                <div class="action">
                    <a href="admin_revenue_manager.php">Quản Lý Doanh Thu</a>
                </div>
            </div>

            <!-- Số lượng đánh giá -->
            <div class="dashboard-item">
                <div class="number"><?php echo $totalRatings; ?></div>
                <div class="label">Số lượng đánh giá</div>
                <div class="action">
                    <a href="admin_ratings.php">Quản Lý Đánh Giá</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
include 'footer.php';
?>