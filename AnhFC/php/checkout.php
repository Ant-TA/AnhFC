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

// Kiểm tra quyền admin và lấy địa chỉ
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT is_admin, address FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Kiểm tra nếu không tìm thấy user
if (!$user) {
    session_unset();
    session_destroy();
    header("Location: user_login.php?error=3"); // User không tồn tại
    exit;
}

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

// Biến để lưu thông báo lỗi
$error = '';

// Xử lý đặt hàng
if (isset($_POST['place_order'])) {
    $shippingAddress = $conn->real_escape_string($_POST['shipping_address']);
    $paymentMethod = $conn->real_escape_string($_POST['payment_method']);

    // Kiểm tra địa chỉ giao hàng không rỗng
    if (empty(trim($shippingAddress))) {
        $error = "Địa chỉ giao hàng không được để trống!";
    } else {
        $total = 0;

        // Tính tổng tiền
        foreach ($_SESSION['cart'] as $itemId => $quantity) {
            $stmt = $conn->prepare("SELECT price FROM menu WHERE id = ?");
            $stmt->bind_param("i", $itemId);
            $stmt->execute();
            $result = $stmt->get_result();
            $item = $result->fetch_assoc();
            $stmt->close();
            $total += $item['price'] * $quantity;
        }

        // Lưu đơn hàng vào bảng orders
        $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, shipping_address, payment_method) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("idss", $userId, $total, $shippingAddress, $paymentMethod);
        $stmt->execute();
        $orderId = $stmt->insert_id;
        $stmt->close();

        // Lưu chi tiết đơn hàng vào bảng order_items
        foreach ($_SESSION['cart'] as $itemId => $quantity) {
            $stmt = $conn->prepare("SELECT price FROM menu WHERE id = ?");
            $stmt->bind_param("i", $itemId);
            $stmt->execute();
            $result = $stmt->get_result();
            $item = $result->fetch_assoc();
            $stmt->close();

            $price = $item['price'];
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, item_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiid", $orderId, $itemId, $quantity, $price);
            $stmt->execute();
            $stmt->close();
        }

        // Xóa giỏ hàng sau khi đặt hàng
        unset($_SESSION['cart']);
        header("Location: user_cart.php?ordered=1");
        exit;
    }
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
    <title>Thanh Toán</title>
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
            text-align: center;
            color: #333;
        }

        .checkout-container {
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .cart-table th, .cart-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .cart-table th {
            background-color: #f5f5f5;
        }

        .cart-table img {
            max-width: 80px;
            border-radius: 5px;
        }

        .total {
            text-align: right;
            font-size: 1.5em;
            margin-top: 20px;
        }

        .payment-method, .shipping-address {
            margin: 20px 0;
        }

        .payment-method label, .shipping-address label {
            display: block;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .payment-method select, .shipping-address textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }

        .shipping-address textarea {
            height: 100px; /* Chiều cao ban đầu */
            resize: none; /* Tắt điều chỉnh thủ công */
            overflow: hidden; /* Ẩn thanh cuộn */
        }

        .confirm-btn {
            display: block;
            width: 200px;
            margin: 20px auto;
            padding: 10px;
            background-color: #333;
            color: white;
            text-align: center;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
        }

        .confirm-btn:hover {
            background-color: #555;
        }

        .empty-cart {
            text-align: center;
            color: #666;
            font-size: 1.2rem;
        }

        .empty-cart a {
            color: #333;
            text-decoration: underline;
        }

        .empty-cart a:hover {
            color: #555;
        }

        .error {
            color: red;
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
    <script>
        // Script để tự động điều chỉnh chiều cao textarea
        function autoResizeTextarea(element) {
            element.style.height = '100px'; // Chiều cao ban đầu
            element.style.height = `${element.scrollHeight}px`; // Điều chỉnh theo nội dung
        }

        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('shipping_address');
            textarea.addEventListener('input', function() {
                autoResizeTextarea(this);
            });
            // Gọi lần đầu để điều chỉnh nếu có nội dung ban đầu
            autoResizeTextarea(textarea);
        });
    </script>
</head>
<body>
    <div class="checkout-container">
        <h1>Thanh Toán</h1>

        <?php if (empty($_SESSION['cart'])): ?>
            <p class="empty-cart">Giỏ hàng của bạn đang trống, thêm món ăn <a href="menu.php">tại đây</a>!</p>
        <?php else: ?>
            <h2>Thông Tin Giỏ Hàng</h2>
            <table class="cart-table">
                <tr>
                    <th>Hình ảnh</th>
                    <th>Tên món</th>
                    <th>Giá</th>
                    <th>Số lượng</th>
                    <th>Tổng</th>
                </tr>
                <?php
                $total = 0;
                foreach ($_SESSION['cart'] as $itemId => $quantity) {
                    $stmt = $conn->prepare("SELECT combo_name, price, image FROM menu WHERE id = ?");
                    $stmt->bind_param("i", $itemId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $item = $result->fetch_assoc();
                    $stmt->close();

                    if ($item) {
                        $subtotal = $item['price'] * $quantity;
                        $total += $subtotal;

                        echo "<tr>";
                        echo "<td><img src='../images/" . htmlspecialchars($item['image']) . "' alt='" . htmlspecialchars($item['combo_name']) . "'></td>";
                        echo "<td>" . htmlspecialchars($item['combo_name']) . "</td>";
                        echo "<td>" . number_format($item['price'], 0, ',', '.') . " VND</td>";
                        echo "<td>" . $quantity . "</td>";
                        echo "<td>" . number_format($subtotal, 0, ',', '.') . " VND</td>";
                        echo "</tr>";
                    }
                }
                ?>
            </table>
            <div class="total">Tổng cộng: <?php echo number_format($total, 0, ',', '.'); ?> VND</div>

            <?php if (!empty($error)): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="payment-method">
                    <label for="payment_method">Phương thức thanh toán:</label>
                    <select name="payment_method" id="payment_method" required>
                        <option value="COD">Thanh toán khi nhận hàng (COD)</option>
                        <option value="PayPal">PayPal (Giả lập)</option>
                        <option value="BankCard">Thẻ ngân hàng (Giả lập)</option>
                    </select>
                </div>

                <div class="shipping-address">
                    <label for="shipping_address">Địa chỉ giao hàng:</label>
                    <textarea name="shipping_address" id="shipping_address" placeholder="Nhập địa chỉ giao hàng" required><?php echo (isset($user['address']) && !empty(trim($user['address']))) ? htmlspecialchars(trim($user['address'])) : ''; ?></textarea>
                </div>

                <button type="submit" name="place_order" class="confirm-btn">Xác nhận đặt hàng</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$conn->close();
include 'footer.php';
?>