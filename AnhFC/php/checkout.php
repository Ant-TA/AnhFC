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

// Kiểm tra quyền admin và lấy thông tin người dùng
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT is_admin, address, restrict_cod, restrict_order FROM users WHERE id = ?");
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

// Lấy trạng thái restrict_cod và restrict_order
$restrictCod = $user['restrict_cod'];
$restrictOrder = $user['restrict_order'];

// Kiểm tra trạng thái hạn chế đặt hàng
if ($restrictOrder == 1) {
    $error = "Tài khoản bị hạn chế đặt hàng.";
} else {
    // Khởi tạo giỏ hàng nếu chưa có
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Khởi tạo danh sách voucher đã áp dụng nếu chưa có
    if (!isset($_SESSION['applied_vouchers'])) {
        $_SESSION['applied_vouchers'] = [];
    }

    // Biến để lưu thông báo lỗi
    $error = '';

    // Tính tổng tiền gốc và lấy thông tin giảm giá
    $total = 0;
    foreach ($_SESSION['cart'] as $itemId => $quantity) {
        $stmt = $conn->prepare("SELECT price FROM menu WHERE id = ?");
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();
        $total += $item['price'] * $quantity;
    }

    // Lấy tổng tiền sau giảm giá từ session
    $finalTotal = isset($_SESSION['final_total']) ? $_SESSION['final_total'] : $total;
    $totalDiscount = $total - $finalTotal;

    // Lấy thông tin các voucher đã áp dụng
    $appliedVouchers = [];
    if (!empty($_SESSION['applied_vouchers'])) {
        $voucherIds = implode(',', array_map('intval', $_SESSION['applied_vouchers']));
        $stmt = $conn->prepare("SELECT id, code, discount_percent, max_discount_value, fixed_discount, min_order_value FROM vouchers WHERE id IN ($voucherIds)");
        $stmt->execute();
        $result = $stmt->get_result();
        $appliedVouchers = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // Xử lý đặt hàng
    if (isset($_POST['place_order'])) {
        $shippingAddress = $conn->real_escape_string($_POST['shipping_address']);
        $receiverName = $conn->real_escape_string($_POST['receiver_name']);
        $receiverPhone = $conn->real_escape_string($_POST['receiver_phone']);
        $paymentMethod = $conn->real_escape_string($_POST['payment_method']);

        // Kiểm tra nếu người dùng bị hạn chế COD và chọn COD
        if ($restrictCod && $paymentMethod === 'COD') {
            $error = "Bạn không thể chọn phương thức thanh toán COD do tài khoản của bạn đã bị hạn chế.";
        } elseif (empty(trim($shippingAddress))) {
            $error = "Địa chỉ giao hàng không được để trống!";
        } elseif (empty(trim($receiverName))) {
            $error = "Tên người nhận không được để trống!";
        } elseif (empty(trim($receiverPhone))) {
            $error = "Số điện thoại giao hàng không được để trống!";
        } elseif (!preg_match('/^[0-9]{10,15}$/', $receiverPhone)) {
            $error = "Số điện thoại không hợp lệ! Vui lòng nhập số điện thoại từ 10 đến 15 chữ số.";
        } else {
            // Lưu đơn hàng vào bảng orders
            $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, final_amount, shipping_address, receiver_name, receiver_phone, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iddssss", $userId, $total, $finalTotal, $shippingAddress, $receiverName, $receiverPhone, $paymentMethod);
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

            // Lưu danh sách voucher đã áp dụng vào bảng order_vouchers
            if (!empty($_SESSION['applied_vouchers'])) {
                foreach ($_SESSION['applied_vouchers'] as $voucherId) {
                    $stmt = $conn->prepare("INSERT INTO order_vouchers (order_id, voucher_id) VALUES (?, ?)");
                    $stmt->bind_param("ii", $orderId, $voucherId);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            // Giảm số lượng của các voucher đã sử dụng
            foreach ($_SESSION['applied_vouchers'] as $voucherId) {
                $stmt = $conn->prepare("UPDATE vouchers SET quantity = quantity - 1 WHERE id = ? AND quantity IS NOT NULL");
                $stmt->bind_param("i", $voucherId);
                $stmt->execute();
                $stmt->close();
            }

            // Xóa giỏ hàng và danh sách voucher đã áp dụng sau khi đặt hàng
            unset($_SESSION['cart']);
            unset($_SESSION['applied_vouchers']);
            unset($_SESSION['final_total']);
            header("Location: user_cart.php?ordered=1");
            exit;
        }
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

        .total-section {
            text-align: right;
            font-size: 1.2em;
            margin-top: 20px;
        }

        .total-section p {
            margin: 5px 0;
        }

        .voucher-section {
            margin: 20px 0;
        }

        .voucher-section h3 {
            margin-bottom: 10px;
            color: #333;
        }

        .voucher-list {
            margin-bottom: 15px;
        }

        .voucher-item {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
            background-color: #f9f9f9;
        }

        .payment-method, .shipping-info {
            margin: 20px 0;
        }

        .payment-method label, .shipping-info label {
            display: block;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .payment-method select, .shipping-info input, .shipping-info textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            margin-bottom: 10px;
        }

        .shipping-info textarea {
            height: 100px;
            resize: none;
            overflow: hidden;
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
        function autoResizeTextarea(element) {
            element.style.height = '100px';
            element.style.height = `${element.scrollHeight}px`;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('shipping_address');
            if (textarea) {
                textarea.addEventListener('input', function() {
                    autoResizeTextarea(this);
                });
                autoResizeTextarea(textarea);
            }
        });
    </script>
</head>
<body>
    <div class="checkout-container">
        <h1>Thanh Toán</h1>

        <?php if (!empty($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if ($restrictOrder == 1): ?>
            <p class="empty-cart">Bạn không thể đặt hàng do tài khoản bị hạn chế. Vui lòng liên hệ admin để biết thêm chi tiết.</p>
        <?php elseif (empty($_SESSION['cart'])): ?>
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
                foreach ($_SESSION['cart'] as $itemId => $quantity) {
                    $stmt = $conn->prepare("SELECT combo_name, price, image FROM menu WHERE id = ?");
                    $stmt->bind_param("i", $itemId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $item = $result->fetch_assoc();
                    $stmt->close();

                    if ($item) {
                        $subtotal = $item['price'] * $quantity;
                        $imagePath = !empty($item['image']) && file_exists("../images/" . $item['image']) 
                            ? "/AnhFC/images/" . htmlspecialchars($item['image']) 
                            : "/AnhFC/images/default_image.jpg";

                        echo "<tr>";
                        echo "<td><img src='$imagePath' alt='" . htmlspecialchars($item['combo_name']) . "'></td>";
                        echo "<td>" . htmlspecialchars($item['combo_name']) . "</td>";
                        echo "<td>" . number_format($item['price'], 0, ',', '.') . " VND</td>";
                        echo "<td>" . $quantity . "</td>";
                        echo "<td>" . number_format($subtotal, 0, ',', '.') . " VND</td>";
                        echo "</tr>";
                    }
                }
                ?>
            </table>

            <!-- Hiển thị danh sách voucher đã áp dụng -->
            <?php if (!empty($appliedVouchers)): ?>
                <div class="voucher-section">
                    <h3>Voucher Đã Áp Dụng</h3>
                    <div class="voucher-list">
                        <?php foreach ($appliedVouchers as $voucher): ?>
                            <div class="voucher-item">
                                <?php
                                $discountText = $voucher['fixed_discount'] ? "Giảm " . number_format($voucher['fixed_discount'], 0, ',', '.') . " VND" : "Giảm " . number_format($voucher['discount_percent'], 0) . "% (Tối đa " . number_format($voucher['max_discount_value'], 0, ',', '.') . " VND)";
                                $conditionText = isset($voucher['min_order_value']) && $voucher['min_order_value'] > 0 ? " (Đơn tối thiểu " . number_format($voucher['min_order_value'], 0, ',', '.') . " VND)" : "";
                                echo htmlspecialchars($voucher['code']) . " - $discountText$conditionText";
                                ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Hiển thị tổng tiền -->
            <div class="total-section">
                <p>Tổng cộng: <?php echo number_format($total, 0, ',', '.'); ?> VND</p>
                <?php if ($totalDiscount > 0): ?>
                    <p>Giảm giá: <?php echo number_format($totalDiscount, 0, ',', '.'); ?> VND</p>
                    <p>Tổng sau giảm giá: <?php echo number_format($finalTotal, 0, ',', '.'); ?> VND</p>
                <?php endif; ?>
            </div>

            <form method="POST" action="">
                <div class="payment-method">
                    <label for="payment_method">Phương thức thanh toán:</label>
                    <select name="payment_method" id="payment_method" required>
                        <?php if (!$restrictCod): ?>
                            <option value="COD">Thanh toán khi nhận hàng (COD)</option>
                        <?php endif; ?>
                        <option value="PayPal">PayPal (Giả lập)</option>
                        <option value="BankCard">Thẻ ngân hàng (Giả lập)</option>
                    </select>
                </div>

                <div class="shipping-info">
                    <label for="receiver_name">Tên người nhận:</label>
                    <input type="text" name="receiver_name" id="receiver_name" placeholder="Nhập tên người nhận" required value="<?php echo isset($_POST['receiver_name']) ? htmlspecialchars($_POST['receiver_name']) : ''; ?>">

                    <label for="receiver_phone">Số điện thoại giao hàng:</label>
                    <input type="text" name="receiver_phone" id="receiver_phone" placeholder="Nhập số điện thoại giao hàng" required value="<?php echo isset($_POST['receiver_phone']) ? htmlspecialchars($_POST['receiver_phone']) : ''; ?>">

                    <label for="shipping_address">Địa chỉ giao hàng:</label>
                    <textarea name="shipping_address" id="shipping_address" placeholder="Nhập địa chỉ giao hàng" required><?php echo (isset($user['address']) && !empty(trim($user['address']))) ? htmlspecialchars(trim($user['address'])) : (isset($_POST['shipping_address']) ? htmlspecialchars($_POST['shipping_address']) : ''); ?></textarea>
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