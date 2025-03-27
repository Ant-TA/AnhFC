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

// Xử lý cập nhật số lượng qua AJAX
if (isset($_POST['update_quantity']) && isset($_POST['item_id']) && isset($_POST['quantity'])) {
    $itemId = intval($_POST['item_id']);
    $quantity = max(1, intval($_POST['quantity'])); // Đảm bảo số lượng >= 1
    $_SESSION['cart'][$itemId] = $quantity;
    $stmt = $conn->prepare("SELECT price FROM menu WHERE id = ?");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
    $subtotal = $item['price'] * $quantity;
    $total = array_sum(array_map(function($id) use ($conn) {
        $stmt = $conn->prepare("SELECT price FROM menu WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();
        return $item['price'] * $_SESSION['cart'][$id];
    }, array_keys($_SESSION['cart'])));
    echo json_encode(['subtotal' => number_format($subtotal, 0, ',', '.'), 'total' => number_format($total, 0, ',', '.')]);
    exit;
}

// Xử lý xóa món khỏi giỏ hàng
if (isset($_GET['remove'])) {
    $itemId = intval($_GET['remove']);
    if (isset($_SESSION['cart'][$itemId])) {
        unset($_SESSION['cart'][$itemId]);
    }
    header("Location: user_cart.php?removed=1");
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

        .total {
            text-align: right;
            font-size: 1.5em;
            margin-top: 20px;
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
                }
            };
            xhr.send(`update_quantity=1&item_id=${itemId}&quantity=${quantity}`);
        }
    </script>
</head>
<div class="cart-container">
    <h1>Giỏ Hàng</h1>

    <?php
    if (isset($_GET['ordered']) && $_GET['ordered'] == 1) {
        echo "<p class='message success'>Đặt hàng thành công!</p>";
    }
    if (isset($_GET['removed']) && $_GET['removed'] == 1) {
        echo "<p class='message success'>Món ăn đã được xóa khỏi giỏ hàng!</p>";
    }

    if (empty($_SESSION['cart'])) {
        echo "<p class='empty-cart'>Giỏ hàng của bạn đang trống, thêm món ăn <a href='menu.php'>tại đây</a>!</p>";
    } else {
            echo "<table class='cart-table'>";
            echo "<tr><th>Hình ảnh</th><th>Tên món</th><th>Giá</th><th>Số lượng</th><th>Tổng</th><th>Hành động</th></tr>";

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
                    echo "<td><input type='number' value='$quantity' min='1' onchange='updateQuantity($itemId, this)'></td>";
                    echo "<td id='subtotal-$itemId'>" . number_format($subtotal, 0, ',', '.') . " VND</td>";
                    echo "<td><a href='user_cart.php?remove=$itemId' class='remove-btn'><i class='fas fa-trash-alt'></i></a></td>";
                    echo "</tr>";
                }
            }
            echo "</table>";
            echo "<div class='total' id='total'>Tổng cộng: " . number_format($total, 0, ',', '.') . " VND</div>";
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