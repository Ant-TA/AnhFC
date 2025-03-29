<?php
session_start();
include 'dbconnection.php';

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
    header("Location: user_home.php");
    exit;
}

// Lấy tháng và năm hiện tại
$currentMonth = date('m');
$currentYear = date('Y');

// Lấy dữ liệu doanh thu từng ngày trong tháng hiện tại (dựa trên final_amount)
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$dailyRevenue = array_fill(1, $daysInMonth, 0);

$stmt = $conn->prepare("SELECT DAY(order_date) as day, SUM(final_amount) as daily_total 
                        FROM orders 
                        WHERE status = 'Completed' 
                        AND MONTH(order_date) = ? 
                        AND YEAR(order_date) = ? 
                        GROUP BY DAY(order_date)");
$stmt->bind_param("ii", $currentMonth, $currentYear);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $dailyRevenue[$row['day']] = $row['daily_total'];
}
$stmt->close();

// Xử lý khoảng thời gian tùy chỉnh
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Đảm bảo định dạng ngày hợp lệ
$startDate = date('Y-m-d 00:00:00', strtotime($startDate));
$endDate = date('Y-m-d 23:59:59', strtotime($endDate));

// Doanh thu tổng cộng (dựa trên final_amount)
$stmt = $conn->prepare("SELECT SUM(final_amount) as total_revenue 
                        FROM orders 
                        WHERE status = 'Completed' 
                        AND order_date BETWEEN ? AND ?");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
$totalRevenue = $result->fetch_assoc()['total_revenue'] ?? 0;
$stmt->close();

// Số lượng đơn hàng đã hoàn tất
$stmt = $conn->prepare("SELECT COUNT(*) as completed_orders 
                        FROM orders 
                        WHERE status = 'Completed' 
                        AND order_date BETWEEN ? AND ?");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
$completedOrders = $result->fetch_assoc()['completed_orders'] ?? 0;
$stmt->close();

// Món ăn được gọi nhiều nhất
$stmt = $conn->prepare("SELECT m.combo_name, SUM(oi.quantity) as total_quantity 
                        FROM order_items oi 
                        JOIN orders o ON oi.order_id = o.id 
                        JOIN menu m ON oi.item_id = m.id 
                        WHERE o.status = 'Completed' 
                        AND o.order_date BETWEEN ? AND ? 
                        GROUP BY m.id, m.combo_name 
                        ORDER BY total_quantity DESC 
                        LIMIT 1");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
$topItem = $result->fetch_assoc();
$stmt->close();

// Voucher được áp dụng nhiều nhất
$stmt = $conn->prepare("SELECT v.code, COUNT(*) as usage_count 
                        FROM order_vouchers ov 
                        JOIN orders o ON ov.order_id = o.id 
                        JOIN vouchers v ON ov.voucher_id = v.id 
                        WHERE o.status = 'Completed' 
                        AND o.order_date BETWEEN ? AND ? 
                        GROUP BY v.id, v.code 
                        ORDER BY usage_count DESC 
                        LIMIT 1");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
$topVoucher = $result->fetch_assoc();
$stmt->close();

// Số người mua hàng (số user_id duy nhất)
$stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) as total_customers 
                        FROM orders 
                        WHERE status = 'Completed' 
                        AND order_date BETWEEN ? AND ?");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
$totalCustomers = $result->fetch_assoc()['total_customers'] ?? 0;
$stmt->close();

// Số khách hàng mới (có đơn hàng đầu tiên trong khoảng thời gian)
$stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) as new_customers 
                        FROM orders o 
                        WHERE status = 'Completed' 
                        AND order_date BETWEEN ? AND ? 
                        AND order_date = (SELECT MIN(order_date) 
                                          FROM orders 
                                          WHERE user_id = o.user_id 
                                          AND status = 'Completed')");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
$newCustomers = $result->fetch_assoc()['new_customers'] ?? 0;
$stmt->close();

// Tỉ lệ quay lại của khách hàng
$stmt = $conn->prepare("SELECT user_id, COUNT(*) as order_count 
                        FROM orders 
                        WHERE status = 'Completed' 
                        AND order_date BETWEEN ? AND ? 
                        GROUP BY user_id 
                        HAVING order_count > 1");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
$returningCustomers = $result->num_rows;
$stmt->close();

$returnRate = $totalCustomers > 0 ? ($returningCustomers / $totalCustomers) * 100 : 0;

// Lấy danh sách món ăn và thông tin chi tiết
$items = [];
$stmt = $conn->prepare("SELECT m.id, m.combo_name, m.image, m.price, 
                               SUM(oi.quantity) as total_quantity, 
                               SUM(oi.quantity * oi.price) as item_revenue 
                        FROM order_items oi 
                        JOIN orders o ON oi.order_id = o.id 
                        JOIN menu m ON oi.item_id = m.id 
                        WHERE o.status = 'Completed' 
                        AND o.order_date BETWEEN ? AND ? 
                        GROUP BY m.id, m.combo_name, m.image, m.price");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
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
    <title>Quản Lý Doanh Thu</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Lexend', sans-serif;
            background-image: url('../assets/BG.jpg');
            background-size: cover;
            background-attachment: fixed;
        }

        h1, h2 {
            text-align: center;
            color: #333;
        }

        .revenue-manager-container {
            max-width: 1500px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .chart-container {
            margin-bottom: 40px;
        }

        .date-filter {
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
            justify-content: center;
            align-items: center;
        }

        .date-filter label {
            font-weight: bold;
        }

        .date-filter input {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }

        .date-filter button {
            padding: 5px 15px;
            background-color: #333;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .date-filter button:hover {
            background-color: #555;
        }

        .stats {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            margin-bottom: 40px;
        }

        .stat-item {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            width: 200px;
            text-align: center;
        }

        .stat-item .number {
            font-size: 1.5em;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-item .label {
            font-size: 1em;
            color: #666;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th, .items-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .items-table th {
            background-color: #f5f5f5;
        }

        .items-table img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="revenue-manager-container">
        <h1>Quản Lý Doanh Thu</h1>

        <!-- Biểu đồ doanh thu từng ngày -->
        <div class="chart-container">
            <h2>Doanh Thu Từng Ngày (Tháng <?php echo $currentMonth; ?> Năm <?php echo $currentYear; ?>)</h2>
            <canvas id="revenueChart"></canvas>
        </div>

        <!-- Bộ lọc ngày -->
        <div class="date-filter">
            <div>
                <label for="start-date">Từ ngày:</label>
                <input type="date" id="start-date" value="<?php echo date('Y-m-d', strtotime($startDate)); ?>">
            </div>
            <div>
                <label for="end-date">Đến ngày:</label>
                <input type="date" id="end-date" value="<?php echo date('Y-m-d', strtotime($endDate)); ?>">
            </div>
            <button onclick="filterRevenue()">Lọc</button>
        </div>

        <!-- Thống kê -->
        <div class="stats">
            <div class="stat-item">
                <div class="number"><?php echo number_format($totalRevenue, 0, ',', '.'); ?> VND</div>
                <div class="label">Doanh Thu Tổng Cộng</div>
            </div>
            <div class="stat-item">
                <div class="number"><?php echo $completedOrders; ?></div>
                <div class="label">Số Đơn Hàng Hoàn Tất</div>
            </div>
            <div class="stat-item">
                <div class="number"><?php echo $topItem ? htmlspecialchars($topItem['combo_name']) : '-'; ?></div>
                <div class="label">Món Ăn Được Gọi Nhiều Nhất</div>
            </div>
            <div class="stat-item">
                <div class="number"><?php echo $topVoucher ? htmlspecialchars($topVoucher['code']) : '-'; ?></div>
                <div class="label">Voucher Được Áp Dụng Nhiều Nhất</div>
            </div>
            <div class="stat-item">
                <div class="number"><?php echo $totalCustomers; ?></div>
                <div class="label">Số Người Mua Hàng</div>
            </div>
            <div class="stat-item">
                <div class="number"><?php echo $newCustomers; ?></div>
                <div class="label">Số Khách Hàng Mới</div>
            </div>
            <div class="stat-item">
                <div class="number"><?php echo number_format($returnRate, 2); ?>%</div>
                <div class="label">Tỉ Lệ Quay Lại</div>
            </div>
        </div>

        <!-- Bảng chi tiết món ăn -->
        <h2>Chi Tiết Món Ăn</h2>
        <?php if (empty($items)): ?>
            <p class="message">Không có dữ liệu món ăn trong khoảng thời gian này.</p>
        <?php else: ?>
            <table class="items-table">
                <tr>
                    <th>Tên</th>
                    <th>Hình Ảnh</th>
                    <th>Đơn Giá</th>
                    <th>Số Lượng Đã Mua</th>
                    <th>Số Tiền Doanh Thu Chưa Tính Giảm Giá</th>
                </tr>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['combo_name']); ?></td>
                        <td>
                            <?php
                            // Kiểm tra và hiển thị hình ảnh
                            $imagePath = !empty($item['image']) && file_exists("../images/" . $item['image']) 
                                ? "/AnhFC/images/" . htmlspecialchars($item['image']) 
                                : "/AnhFC/images/default_image.jpg";
                            ?>
                            <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($item['combo_name']); ?>">
                        </td>
                        <td><?php echo number_format($item['price'], 0, ',', '.'); ?> VND</td>
                        <td><?php echo $item['total_quantity']; ?></td>
                        <td><?php echo number_format($item['item_revenue'], 0, ',', '.'); ?> VND</td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <script>
        // Biểu đồ doanh thu từng ngày
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: Array.from({length: <?php echo $daysInMonth; ?>}, (_, i) => i + 1),
                datasets: [{
                    label: 'Doanh Thu (VND)',
                    data: <?php echo json_encode(array_values($dailyRevenue)); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Doanh Thu (VND)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Ngày'
                        }
                    }
                }
            }
        });

        // Hàm lọc doanh thu theo khoảng thời gian
        function filterRevenue() {
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            window.location.href = `admin_revenue_manager.php?start_date=${startDate}&end_date=${endDate}`;
        }
    </script>
</body>
</html>

<?php
$conn->close();
include 'footer.php';
?>