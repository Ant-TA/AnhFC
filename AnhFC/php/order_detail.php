<?php
session_start();
include_once 'dbconnection.php';
include_once 'voucher_helper.php'; // Thêm include file helper

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

// Kiểm tra order_id
if (!isset($_GET['order_id'])) {
    header("Location: user_profile.php");
    exit;
}

$orderId = intval($_GET['order_id']);

// Lấy thông tin đơn hàng
$stmt = $conn->prepare("SELECT id, user_id, order_date, total_amount, final_amount, shipping_address, receiver_name, receiver_phone, payment_method, status, note 
                        FROM orders 
                        WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $orderId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: user_profile.php");
    exit;
}

// Xử lý hủy đơn hàng
$cancelError = '';
$cancelSuccess = '';
if (isset($_POST['cancel_order'])) {
    if ($order['status'] !== 'Pending') {
        $cancelError = "Chỉ có thể hủy đơn hàng ở trạng thái Đã đặt (Pending)!";
    } else {
        $cancelReason = $conn->real_escape_string($_POST['cancel_reason']);
        if (empty(trim($cancelReason))) {
            $cancelError = "Vui lòng nhập lý do hủy đơn!";
        } else {
            $note = "Người dùng chủ động hủy: " . $cancelReason;
            $stmt = $conn->prepare("UPDATE orders SET status = 'Cancelled', note = ? WHERE id = ?");
            $stmt->bind_param("si", $note, $orderId);
            if ($stmt->execute()) {
                // Hoàn lại voucher
                refundVoucher($conn, $orderId);
                $cancelSuccess = "Hủy đơn hàng thành công! Voucher đã được hoàn lại nếu có.";
                $order['status'] = 'Cancelled';
                $order['note'] = $note;
            } else {
                $cancelError = "Có lỗi xảy ra khi hủy đơn hàng. Vui lòng thử lại.";
            }
            $stmt->close();
        }
    }
}

// Lấy danh sách món trong đơn hàng
$orderItems = [];
$stmt = $conn->prepare("SELECT oi.item_id, oi.quantity, oi.price, m.combo_name 
                        FROM order_items oi 
                        JOIN menu m ON oi.item_id = m.id 
                        WHERE oi.order_id = ?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$result = $stmt->get_result();
while ($item = $result->fetch_assoc()) {
    $orderItems[] = $item;
}
$stmt->close();

// Lấy danh sách voucher đã áp dụng
$vouchers = [];
$stmt = $conn->prepare("SELECT v.code, v.discount_percent, v.max_discount_value, v.fixed_discount 
                        FROM order_vouchers ov 
                        JOIN vouchers v ON ov.voucher_id = v.id 
                        WHERE ov.order_id = ?");
$stmt->bind_param("i", $orderId);
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
    <title>Chi Tiết Đơn Hàng</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .order-detail-container {
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .order-info, .order-items, .voucher-info {
            margin-bottom: 30px;
        }

        .order-info p {
            font-size: 1.1rem;
            margin: 10px 0;
        }

        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .item-table th, .item-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .item-table th {
            background-color: #f5f5f5;
        }

        .voucher-item {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
            background-color: #f9f9f9;
        }

        .cancel-btn {
            display: block;
            width: 200px;
            margin: 20px auto;
            padding: 10px;
            background-color: #e74c3c;
            color: white;
            text-align: center;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
        }

        .cancel-btn:hover {
            background-color: #c0392b;
        }

        .cancel-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .cancel-popup-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            width: 400px;
            max-width: 90%;
            text-align: center;
        }

        .cancel-popup-content h3 {
            margin-top: 0;
            color: #333;
        }

        .cancel-popup-content p {
            color: #e74c3c;
            font-weight: bold;
        }

        .cancel-popup-content textarea {
            width: 100%;
            height: 80px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin: 10px 0;
            resize: none;
        }

        .cancel-popup-content .btn {
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 0 10px;
        }

        .cancel-popup-content .btn-confirm {
            background-color: #e74c3c;
            color: white;
        }

        .cancel-popup-content .btn-confirm:hover {
            background-color: #c0392b;
        }

        .cancel-popup-content .btn-cancel {
            background-color: #ccc;
            color: #333;
        }

        .cancel-popup-content .btn-cancel:hover {
            background-color: #bbb;
        }

        .error {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 15px;
        }

        .success {
            color: #2ecc71;
            text-align: center;
            margin-bottom: 15px;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #333;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="order-detail-container">
        <h1>Chi Tiết Đơn Hàng</h1>

        <?php if (!empty($cancelError)): ?>
            <p class="error"><?php echo htmlspecialchars($cancelError); ?></p>
        <?php endif; ?>
        <?php if (!empty($cancelSuccess)): ?>
            <p class="success"><?php echo htmlspecialchars($cancelSuccess); ?></p>
        <?php endif; ?>

        <!-- Thông tin đơn hàng -->
        <div class="order-info">
            <h2>Thông Tin Đơn Hàng</h2>
            <p><strong>Mã đơn hàng:</strong> <?php echo htmlspecialchars($order['id']); ?></p>
            <p><strong>Ngày đặt:</strong> <?php echo htmlspecialchars($order['order_date']); ?></p>
            <p><strong>Địa chỉ giao hàng:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
            <p><strong>Tên người nhận:</strong> <?php echo htmlspecialchars($order['receiver_name']); ?></p>
            <p><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($order['receiver_phone']); ?></p>
            <p><strong>Phương thức thanh toán:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
            <p><strong>Trạng thái:</strong> <?php echo htmlspecialchars($order['status']); ?></p>
            <p><strong>Tổng tiền gốc:</strong> <?php echo number_format($order['total_amount'], 0, ',', '.'); ?> VND</p>
            <p><strong>Tổng tiền sau giảm giá:</strong> <?php echo number_format($order['final_amount'], 0, ',', '.'); ?> VND</p>
            <?php if (!empty($order['note'])): ?>
                <p><strong>Ghi chú:</strong> <?php echo htmlspecialchars($order['note']); ?></p>
            <?php endif; ?>
        </div>

        <!-- Nút hủy đơn hàng -->
        <?php if ($order['status'] === 'Pending'): ?>
            <button class="cancel-btn" onclick="showCancelPopup()">Hủy Đơn</button>
        <?php endif; ?>

        <!-- Danh sách món đã đặt -->
        <div class="order-items">
            <h2>Danh Sách Món Đã Đặt</h2>
            <table class="item-table">
                <tr>
                    <th>Tên Món</th>
                    <th>Số Lượng</th>
                    <th>Giá</th>
                    <th>Tổng</th>
                </tr>
                <?php foreach ($orderItems as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['combo_name']); ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td><?php echo number_format($item['price'], 0, ',', '.'); ?> VND</td>
                        <td><?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?> VND</td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- Danh sách voucher đã áp dụng -->
        <?php if (!empty($vouchers)): ?>
            <div class="voucher-info">
                <h2>Voucher Đã Áp Dụng</h2>
                <?php foreach ($vouchers as $voucher): ?>
                    <div class="voucher-item">
                        <?php
                        $discountText = $voucher['fixed_discount'] ? "Giảm " . number_format($voucher['fixed_discount'], 0, ',', '.') . " VND" : "Giảm " . number_format($voucher['discount_percent'], 0) . "% (Tối đa " . number_format($voucher['max_discount_value'], 0, ',', '.') . " VND)";
                        echo htmlspecialchars($voucher['code']) . " - $discountText";
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <a href="user_profile.php" class="back-link">Quay lại trang thông tin</a>
    </div>

    <!-- Popup hủy đơn hàng -->
    <div class="cancel-popup" id="cancel-popup">
        <div class="cancel-popup-content">
            <h3>Xác Nhận Hủy Đơn Hàng</h3>
            <p>Hủy đơn liên tục có thể dẫn đến hạn chế COD!</p>
            <form method="POST" action="">
                <label for="cancel_reason">Lý do hủy đơn:</label>
                <textarea name="cancel_reason" id="cancel_reason" placeholder="Nhập lý do hủy đơn" required></textarea>
                <button type="submit" name="cancel_order" class="btn btn-confirm">Xác Nhận Hủy</button>
                <button type="button" class="btn btn-cancel" onclick="hideCancelPopup()">Hủy Bỏ</button>
            </form>
        </div>
    </div>

    <script>
        function showCancelPopup() {
            document.getElementById('cancel-popup').style.display = 'flex';
        }

        function hideCancelPopup() {
            document.getElementById('cancel-popup').style.display = 'none';
        }
    </script>
</body>
</html>

<?php
$conn->close();
include 'footer.php';
?>