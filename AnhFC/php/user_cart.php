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

// Khởi tạo danh sách voucher đã áp dụng nếu chưa có
if (!isset($_SESSION['applied_vouchers'])) {
    $_SESSION['applied_vouchers'] = [];
}

// Xử lý áp dụng voucher
if (isset($_POST['apply_voucher'])) {
    $voucherCode = trim($_POST['voucher_code']);
    $currentDate = date('Y-m-d H:i:s');

    // Kiểm tra mã voucher
    $stmt = $conn->prepare("SELECT * FROM vouchers WHERE code = ? AND (expiry_date IS NULL OR expiry_date > ?) AND (quantity IS NULL OR quantity > 0)");
    $stmt->bind_param("ss", $voucherCode, $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $voucher = $result->fetch_assoc();
    $stmt->close();

    if ($voucher) {
        if (!in_array($voucher['id'], $_SESSION['applied_vouchers'])) {
            $_SESSION['applied_vouchers'][] = $voucher['id'];
            $_SESSION['voucher_message'] = "Áp dụng voucher thành công!";
        } else {
            $_SESSION['voucher_message'] = "Voucher đã được áp dụng.";
        }
    } else {
        $_SESSION['voucher_message'] = "Mã voucher không hợp lệ hoặc đã hết hạn.";
    }
    header("Location: user_cart.php");
    exit;
}

// Xử lý xóa voucher đã áp dụng
if (isset($_GET['remove_voucher'])) {
    $voucherId = intval($_GET['remove_voucher']);
    $_SESSION['applied_vouchers'] = array_filter($_SESSION['applied_vouchers'], function($id) use ($voucherId) {
        return $id != $voucherId;
    });
    $_SESSION['voucher_message'] = "Đã xóa voucher khỏi danh sách áp dụng.";
    header("Location: user_cart.php");
    exit;
}

// Xử lý cập nhật số lượng qua AJAX
if (isset($_POST['update_quantity']) && isset($_POST['item_id']) && isset($_POST['quantity'])) {
    $itemId = intval($_POST['item_id']);
    $quantity = max(1, intval($_POST['quantity']));
    $_SESSION['cart'][$itemId] = $quantity;

    $stmt = $conn->prepare("SELECT price FROM menu WHERE id = ?");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();

    $subtotal = $item['price'] * $quantity;

    // Tính lại tổng tiền gốc
    $total = array_sum(array_map(function($id) use ($conn) {
        $stmt = $conn->prepare("SELECT price FROM menu WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();
        return $item['price'] * $_SESSION['cart'][$id];
    }, array_keys($_SESSION['cart'])));

    // Tính lại giảm giá dựa trên tổng tiền mới
    $discountDetails = calculateDiscount($conn, $total, $_SESSION['applied_vouchers']);
    $finalTotal = $discountDetails['final_total'];
    $totalDiscount = $discountDetails['total_discount'];

    // Cập nhật lại $_SESSION['final_total']
    $_SESSION['final_total'] = $finalTotal;

    echo json_encode([
        'subtotal' => number_format($subtotal, 0, ',', '.'),
        'total' => number_format($total, 0, ',', '.'),
        'total_discount' => number_format($totalDiscount, 0, ',', '.'), // Trả về số tiền giảm giá
        'final_total' => number_format($finalTotal, 0, ',', '.')
    ]);
    exit;
}

// Xử lý xóa món khỏi giỏ hàng
if (isset($_GET['remove'])) {
    $itemId = intval($_GET['remove']);
    if (isset($_SESSION['cart'][$itemId])) {
        unset($_SESSION['cart'][$itemId]);
    }

    // Tính lại tổng tiền và giảm giá sau khi xóa món
    $total = array_sum(array_map(function($id) use ($conn) {
        $stmt = $conn->prepare("SELECT price FROM menu WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();
        return $item['price'] * $_SESSION['cart'][$id];
    }, array_keys($_SESSION['cart'])));

    $discountDetails = calculateDiscount($conn, $total, $_SESSION['applied_vouchers']);
    $_SESSION['final_total'] = $discountDetails['final_total'];

    header("Location: user_cart.php?removed=1");
    exit;
}

// Hàm tính toán giảm giá
function calculateDiscount($conn, $total, $appliedVouchers) {
    $fixedDiscount = 0;
    $percentDiscountAmount = 0; // Tổng số tiền giảm giá phần trăm

    foreach ($appliedVouchers as $voucherId) {
        $stmt = $conn->prepare("SELECT * FROM vouchers WHERE id = ?");
        $stmt->bind_param("i", $voucherId);
        $stmt->execute();
        $result = $stmt->get_result();
        $voucher = $result->fetch_assoc();
        $stmt->close();

        if ($voucher) {
            // Áp dụng giảm giá cố định trước
            if (!empty($voucher['fixed_discount'])) {
                if (empty($voucher['min_order_value']) || $total >= $voucher['min_order_value']) {
                    $fixedDiscount += $voucher['fixed_discount'];
                }
            }
            // Áp dụng giảm giá phần trăm
            if (!empty($voucher['discount_percent'])) {
                $discount = $total * ($voucher['discount_percent'] / 100);
                // Áp dụng giới hạn max_discount_value
                if (!empty($voucher['max_discount_value'])) {
                    $discount = min($discount, $voucher['max_discount_value']);
                }
                $percentDiscountAmount += $discount;
            }
        }
    }

    // Tính tổng tiền sau khi áp dụng giảm giá cố định
    $totalAfterFixed = max(0, $total - $fixedDiscount);

    // Tính tổng tiền sau khi áp dụng giảm giá phần trăm
    $totalAfterPercent = max(0, $totalAfterFixed - $percentDiscountAmount);

    // Tính tổng giảm giá
    $totalDiscount = $fixedDiscount + $percentDiscountAmount;

    return [
        'fixed_discount' => $fixedDiscount,
        'percent_discount_amount' => $percentDiscountAmount,
        'total_discount' => $totalDiscount,
        'final_total' => $totalAfterPercent
    ];
}

// Lấy danh sách voucher công khai còn hiệu lực
$currentDate = date('Y-m-d H:i:s');
$stmt = $conn->prepare("SELECT * FROM vouchers WHERE is_public = 1 AND (expiry_date IS NULL OR expiry_date > ?) AND (quantity IS NULL OR quantity > 0)");
$stmt->bind_param("s", $currentDate);
$stmt->execute();
$result = $stmt->get_result();
$publicVouchers = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Tính tổng tiền và giảm giá
$total = 0;
if (!empty($_SESSION['cart'])) {
    $total = array_sum(array_map(function($id) use ($conn) {
        $stmt = $conn->prepare("SELECT price FROM menu WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();
        return $item['price'] * $_SESSION['cart'][$id];
    }, array_keys($_SESSION['cart'])));
}

// Tính lại giảm giá mỗi khi tải trang
$discountDetails = calculateDiscount($conn, $total, $_SESSION['applied_vouchers']);
$finalTotal = $discountDetails['final_total'];
$_SESSION['final_total'] = $finalTotal;
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
    <title>Giỏ Hàng</title>
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

        h1 {
            text-align: center;
            color: #333;
        }

        .cart-container {
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

        .cart-table input[type="number"] {
            width: 60px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .cart-table .remove-btn {
            color: red;
            text-decoration: none;
            font-size: 1.2rem;
        }

        .cart-table .remove-btn:hover {
            color: darkred;
        }

        .voucher-section {
            margin-bottom: 20px;
        }

        .voucher-section h3 {
            margin-bottom: 10px;
            color: #333;
        }

        .voucher-list {
            margin-bottom: 15px;
        }

        .voucher-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
            background-color: #f9f9f9;
        }

        .voucher-item input[type="checkbox"] {
            margin-right: 10px;
        }

        .voucher-item label {
            flex-grow: 1;
        }

        .voucher-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .voucher-form input[type="text"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            flex-grow: 1;
        }

        .voucher-form button {
            padding: 8px 15px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .voucher-form button:hover {
            background-color: #555;
        }

        .applied-vouchers {
            margin-top: 15px;
        }

        .applied-voucher {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 10px;
            background-color: #e0f7fa;
            border-radius: 5px;
            margin-bottom: 5px;
        }

        .applied-voucher a {
            color: red;
            text-decoration: none;
        }

        .applied-voucher a:hover {
            color: darkred;
        }

        .total-section {
            text-align: right;
            font-size: 1.2em;
            margin-top: 20px;
        }

        .total-section p {
            margin: 5px 0;
        }

        .checkout-btn {
            display: block;
            width: 200px;
            margin: 20px auto;
            padding: 10px;
            background-color: #333;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;
        }

        .checkout-btn:hover {
            background-color: #555;
        }

        .message {
            text-align: center;
            margin-bottom: 20px;
        }

        .success {
            color: green;
        }

        .error {
            color: #e74c3c;
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
    </style>
    <script>
        function updateQuantity(itemId, quantityInput) {
            const quantity = quantityInput.value;
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "user_cart.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    document.getElementById(`subtotal-${itemId}`).innerText = response.subtotal + " VND";
                    document.getElementById("total").innerText = "Tổng cộng: " + response.total + " VND";
                    document.getElementById("discount").innerText = "Giảm giá: " + response.total_discount + " VND";
                    document.getElementById("final-total").innerText = "Tổng sau giảm giá: " + response.final_total + " VND";
                }
            };
            xhr.send(`update_quantity=1&item_id=${itemId}&quantity=${quantity}`);
        }

        function applyVoucher(voucherId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'user_cart.php';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'apply_voucher';
            input.value = '1';
            form.appendChild(input);
            const codeInput = document.createElement('input');
            codeInput.type = 'hidden';
            codeInput.name = 'voucher_code';
            codeInput.value = document.getElementById(`voucher-${voucherId}`).dataset.code;
            form.appendChild(codeInput);
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</head>
<body>
    <div class="cart-container">
        <h1>Giỏ Hàng</h1>

        <?php
        if (isset($_GET['ordered']) && $_GET['ordered'] == 1) {
            echo "<p class='message success'>Đặt hàng thành công!</p>";
        }
        if (isset($_GET['removed']) && $_GET['removed'] == 1) {
            echo "<p class='message success'>Món ăn đã được xóa khỏi giỏ hàng!</p>";
        }
        if (isset($_SESSION['voucher_message'])) {
            $messageClass = strpos($_SESSION['voucher_message'], 'thành công') !== false ? 'success' : 'error';
            echo "<p class='message $messageClass'>" . htmlspecialchars($_SESSION['voucher_message']) . "</p>";
            unset($_SESSION['voucher_message']);
        }

        if (empty($_SESSION['cart'])) {
            echo "<p class='empty-cart'>Giỏ hàng của bạn đang trống, thêm món ăn <a href='menu.php'>tại đây</a>!</p>";
        } else {
            // Hiển thị danh sách voucher công khai
            echo "<div class='voucher-section'>";
            echo "<h3>Áp dụng Voucher</h3>";

            if (!empty($publicVouchers)) {
                echo "<div class='voucher-list'>";
                foreach ($publicVouchers as $voucher) {
                    $discountText = $voucher['fixed_discount'] ? "Giảm " . number_format($voucher['fixed_discount'], 0, ',', '.') . " VND" : "Giảm " . number_format($voucher['discount_percent'], 0) . "% (Tối đa " . number_format($voucher['max_discount_value'], 0, ',', '.') . " VND)";
                    $conditionText = $voucher['min_order_value'] ? " (Đơn tối thiểu " . number_format($voucher['min_order_value'], 0, ',', '.') . " VND)" : "";
                    echo "<div class='voucher-item'>";
                    echo "<input type='checkbox' id='voucher-{$voucher['id']}' data-code='{$voucher['code']}' onchange='applyVoucher({$voucher['id']})' " . (in_array($voucher['id'], $_SESSION['applied_vouchers']) ? 'checked' : '') . ">";
                    echo "<label for='voucher-{$voucher['id']}'>{$voucher['code']} - $discountText$conditionText</label>";
                    echo "</div>";
                }
                echo "</div>";
            }

            // Form nhập mã voucher
            echo "<form method='POST' action='' class='voucher-form'>";
            echo "<input type='text' name='voucher_code' placeholder='Nhập mã voucher'>";
            echo "<button type='submit' name='apply_voucher'>Áp dụng</button>";
            echo "</form>";

            // Hiển thị danh sách voucher đã áp dụng
            if (!empty($_SESSION['applied_vouchers'])) {
                echo "<div class='applied-vouchers'>";
                echo "<h4>Voucher đã áp dụng:</h4>";
                foreach ($_SESSION['applied_vouchers'] as $voucherId) {
                    $stmt = $conn->prepare("SELECT code FROM vouchers WHERE id = ?");
                    $stmt->bind_param("i", $voucherId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $voucher = $result->fetch_assoc();
                    $stmt->close();
                    if ($voucher) {
                        echo "<div class='applied-voucher'>";
                        echo "<span>" . htmlspecialchars($voucher['code']) . "</span>";
                        echo "<a href='user_cart.php?remove_voucher=$voucherId'>Xóa</a>";
                        echo "</div>";
                    }
                }
                echo "</div>";
            }
            echo "</div>";

            // Hiển thị giỏ hàng
            echo "<table class='cart-table'>";
            echo "<tr><th>Hình ảnh</th><th>Tên món</th><th>Giá</th><th>Số lượng</th><th>Tổng</th><th>Hành động</th></tr>";

            foreach ($_SESSION['cart'] as $itemId => $quantity) {
                $stmt = $conn->prepare("SELECT combo_name, price, image FROM menu WHERE id = ?");
                $stmt->bind_param("i", $itemId);
                $stmt->execute();
                $result = $stmt->get_result();
                $item = $result->fetch_assoc();
                $stmt->close();

                if ($item) {
                    $subtotal = $item['price'] * $quantity;

                    echo "<tr>";
                    echo "<td><img src='../images/" . htmlspecialchars($item['image']) . "' alt='" . htmlspecialchars($item['combo_name']) . "'></td>";
                    echo "<td>" . htmlspecialchars($item['combo_name']) . "</td>";
                    echo "<td>" . number_format($item['price'], 0, ',', '.') . " VND</td>";
                    echo "<td><input type='number' value='$quantity' min='1' onchange='updateQuantity($itemId, this)'></td>";
                    echo "<td id='subtotal-$itemId'>" . number_format($subtotal, 0, ',', '.') . " VND</td>";
                    echo "<td><a href='user_cart.php?remove=$itemId' class='remove-btn'><i class='fas fa-trash-alt'></i></a></td>";
                    echo "</tr>";
                }
            }
            echo "</table>";

            // Hiển thị tổng tiền và giảm giá
            echo "<div class='total-section'>";
            echo "<p id='total'>Tổng cộng: " . number_format($total, 0, ',', '.') . " VND</p>";
            if ($discountDetails['total_discount'] > 0) {
                echo "<p id='discount'>Giảm giá: " . number_format($discountDetails['total_discount'], 0, ',', '.') . " VND</p>";
                echo "<p id='final-total'>Tổng sau giảm giá: " . number_format($finalTotal, 0, ',', '.') . " VND</p>";
            }
            echo "</div>";

            echo "<a href='checkout.php' class='checkout-btn'>Thanh toán</a>";
        }
        ?>
    </div>
</body>
</html>

<?php
$conn->close();
include 'footer.php';
?>