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
$stmt = $conn->prepare("SELECT is_admin, username, email, phone, address, created_at, restrict_cod, restrict_order, password FROM users WHERE id = ?");
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

// Xác định trạng thái tài khoản
$accountStatus = "Bình thường";
if ($user['restrict_cod'] && $user['restrict_order']) {
    $accountStatus = "Hạn chế COD và đặt hàng";
} elseif ($user['restrict_cod']) {
    $accountStatus = "Hạn chế COD";
} elseif ($user['restrict_order']) {
    $accountStatus = "Hạn chế đặt hàng";
}

// Thống kê đơn hàng
$totalOrders = 0;
$pendingOrders = 0;
$shippingOrders = 0;
$completedOrders = 0;
$cancelledOrders = 0;

// Tổng số đơn hàng
$stmt = $conn->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$totalOrders = $result->fetch_assoc()['total_orders'];
$stmt->close();

// Số đơn hàng đang chờ xử lý (Pending)
$stmt = $conn->prepare("SELECT COUNT(*) as pending_orders FROM orders WHERE user_id = ? AND status = 'Pending'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$pendingOrders = $result->fetch_assoc()['pending_orders'];
$stmt->close();

// Số đơn hàng đang giao (Shipping)
$stmt = $conn->prepare("SELECT COUNT(*) as shipping_orders FROM orders WHERE user_id = ? AND status = 'Shipping'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$shippingOrders = $result->fetch_assoc()['shipping_orders'];
$stmt->close();

// Số đơn hàng đã hoàn tất (Completed)
$stmt = $conn->prepare("SELECT COUNT(*) as completed_orders FROM orders WHERE user_id = ? AND status = 'Completed'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$completedOrders = $result->fetch_assoc()['completed_orders'];
$stmt->close();

// Số đơn hàng đã hủy (Cancelled)
$stmt = $conn->prepare("SELECT COUNT(*) as cancelled_orders FROM orders WHERE user_id = ? AND status = 'Cancelled'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$cancelledOrders = $result->fetch_assoc()['cancelled_orders'];
$stmt->close();

// Lấy danh sách đơn hàng
$orders = [];
$stmt = $conn->prepare("SELECT id, order_date, final_amount, status FROM orders WHERE user_id = ? ORDER BY order_date DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($order = $result->fetch_assoc()) {
    $orders[] = $order;
}
$stmt->close();

// Xử lý cập nhật thông tin người dùng
$updateError = '';
$updateSuccess = '';
if (isset($_POST['update_profile'])) {
    $currentPassword = $_POST['current_password'];
    $newEmail = $conn->real_escape_string($_POST['email']);
    $newPhone = $conn->real_escape_string($_POST['phone']);
    $newAddress = $conn->real_escape_string($_POST['address']);
    $newPassword = !empty($_POST['new_password']) ? password_hash($_POST['new_password'], PASSWORD_BCRYPT) : $user['password'];

    // Xác minh mật khẩu hiện tại
    if (!password_verify($currentPassword, $user['password'])) {
        $updateError = "Mật khẩu hiện tại không đúng!";
    } else {
        // Cập nhật thông tin
        $stmt = $conn->prepare("UPDATE users SET email = ?, phone = ?, address = ?, password = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $newEmail, $newPhone, $newAddress, $newPassword, $userId);
        if ($stmt->execute()) {
            $updateSuccess = "Cập nhật thông tin thành công!";
            // Cập nhật lại thông tin người dùng
            $user['email'] = $newEmail;
            $user['phone'] = $newPhone;
            $user['address'] = $newAddress;
            if ($newPassword !== $user['password']) {
                $user['password'] = $newPassword;
            }
        } else {
            $updateError = "Có lỗi xảy ra khi cập nhật thông tin. Vui lòng thử lại.";
        }
        $stmt->close();
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
    <title>Thông Tin Người Dùng</title>
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

        .profile-container {
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .profile-info, .order-stats, .order-history, .update-form {
            margin-bottom: 30px;
        }

        .profile-info p {
            font-size: 1.1rem;
            margin: 10px 0;
        }

        .profile-info .status {
            font-weight: bold;
        }

        .profile-info .status.restricted {
            color: #e74c3c;
        }

        .profile-info .status.normal {
            color: #2ecc71;
        }

        .edit-btn {
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

        .edit-btn:hover {
            background-color: #555;
        }

        .update-form {
            display: none;
        }

        .update-form.active {
            display: block;
        }

        .update-form label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .update-form input, .update-form textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            margin-bottom: 15px;
        }

        .update-form textarea {
            height: 100px;
            resize: none;
        }

        .update-form button {
            display: block;
            width: 200px;
            margin: 0 auto;
            padding: 10px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
        }

        .update-form button:hover {
            background-color: #555;
        }

        .order-stats-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .status-btn {
            position: relative;
            background: none;
            border: none;
            cursor: pointer;
            padding: 10px;
            text-align: center;
            transition: transform 0.1s;
        }

        .status-btn.active {
            background-color: #e0e0e0;
            border-radius: 5px;
        }

        .status-btn:hover {
            transform: scale(1.1);
        }

        .status-btn i {
            font-size: 2rem;
            color: #333;
        }

        .status-btn span {
            display: block;
            font-size: 0.9rem;
            color: #333;
        }

        .status-btn .badge {
            position: absolute;
            top: 0;
            right: 0;
            background-color: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.8rem;
        }

        .order-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .order-table th, .order-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .order-table th {
            background-color: #f5f5f5;
        }

        .order-table a, .order-table button {
            color: #333;
            text-decoration: none;
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }

        .order-table a:hover, .order-table button:hover {
            background-color: #e0e0e0;
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
    </style>
</head>
<body>
    <div class="profile-container">
        <h1>Thông Tin Người Dùng</h1>

        <!-- Thông tin người dùng -->
        <div class="profile-info">
            <h2>Thông Tin Cá Nhân</h2>
            <p><strong>Tên người dùng:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            <p><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($user['phone'] ?? '-'); ?></p>
            <p><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($user['address'] ?? '-'); ?></p>
            <p><strong>Ngày tạo tài khoản:</strong> <?php echo htmlspecialchars($user['created_at']); ?></p>
            <p><strong>Trạng thái tài khoản:</strong> 
                <span class="status <?php echo $accountStatus === 'Bình thường' ? 'normal' : 'restricted'; ?>">
                    <?php echo htmlspecialchars($accountStatus); ?>
                </span>
            </p>
            <button class="edit-btn" onclick="toggleEditForm()">Sửa Thông Tin</button>
        </div>

        <!-- Form cập nhật thông tin -->
        <div class="update-form" id="update-form">
            <h2>Cập Nhật Thông Tin</h2>
            <?php if (!empty($updateError)): ?>
                <p class="error"><?php echo htmlspecialchars($updateError); ?></p>
            <?php endif; ?>
            <?php if (!empty($updateSuccess)): ?>
                <p class="success"><?php echo htmlspecialchars($updateSuccess); ?></p>
            <?php endif; ?>
            <form method="POST" action="">
                <label for="email">Email:</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>

                <label for="phone">Số điện thoại:</label>
                <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="Nhập số điện thoại">

                <label for="address">Địa chỉ:</label>
                <textarea name="address" id="address" placeholder="Nhập địa chỉ"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>

                <label for="new_password">Mật khẩu mới (để trống nếu không muốn thay đổi):</label>
                <input type="password" name="new_password" id="new_password" placeholder="Nhập mật khẩu mới">

                <label for="current_password">Mật khẩu hiện tại (bắt buộc để xác nhận):</label>
                <input type="password" name="current_password" id="current_password" required placeholder="Nhập mật khẩu hiện tại">

                <button type="submit" name="update_profile">Cập Nhật</button>
            </form>
        </div>

        <!-- Thống kê đơn hàng -->
        <div class="order-stats">
            <h2>Thống Kê Đơn Hàng</h2>
            <div class="order-stats-buttons">
                <button class="status-btn" data-status="Pending" onclick="toggleFilter('Pending')">
                    <i class="fas fa-clock"></i>
                    <span>Đã đặt</span>
                    <?php if ($pendingOrders > 0): ?>
                        <span class="badge"><?php echo $pendingOrders; ?></span>
                    <?php endif; ?>
                </button>
                <button class="status-btn" data-status="Shipping" onclick="toggleFilter('Shipping')">
                    <i class="fas fa-truck"></i>
                    <span>Đang giao</span>
                    <?php if ($shippingOrders > 0): ?>
                        <span class="badge"><?php echo $shippingOrders; ?></span>
                    <?php endif; ?>
                </button>
                <button class="status-btn" data-status="Completed" onclick="toggleFilter('Completed')">
                    <i class="fas fa-check-circle"></i>
                    <span>Đã hoàn tất</span>
                </button>
                <button class="status-btn" data-status="Cancelled" onclick="toggleFilter('Cancelled')">
                    <i class="fas fa-times-circle"></i>
                    <span>Đã hủy</span>
                </button>
            </div>
        </div>

        <!-- Danh sách đơn hàng -->
        <div class="order-history">
            <h2>Lịch Sử Đơn Hàng</h2>
            <?php if (empty($orders)): ?>
                <p>Chưa có đơn hàng nào.</p>
            <?php else: ?>
                <table class="order-table" id="order-table">
                    <tr>
                        <th>Mã Đơn Hàng</th>
                        <th>Ngày Đặt</th>
                        <th>Tổng Tiền</th>
                        <th>Trạng Thái</th>
                        <th>Hành Động</th>
                    </tr>
                    <?php foreach ($orders as $order): ?>
                        <tr data-status="<?php echo htmlspecialchars($order['status']); ?>">
                            <td><?php echo htmlspecialchars($order['id']); ?></td>
                            <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                            <td><?php echo number_format($order['final_amount'], 0, ',', '.'); ?> VND</td>
                            <td><?php echo htmlspecialchars($order['status']); ?></td>
                            <td>
                                <a href="order_detail.php?order_id=<?php echo $order['id']; ?>">Xem Chi Tiết</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let activeFilters = [];

        function toggleEditForm() {
            const form = document.getElementById('update-form');
            form.classList.toggle('active');
        }

        function toggleFilter(status) {
            const btn = document.querySelector(`.status-btn[data-status="${status}"]`);
            const index = activeFilters.indexOf(status);

            if (index === -1) {
                activeFilters.push(status);
                btn.classList.add('active');
            } else {
                activeFilters.splice(index, 1);
                btn.classList.remove('active');
            }

            filterOrders();
        }

        function filterOrders() {
            const rows = document.querySelectorAll('#order-table tr[data-status]');
            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                if (activeFilters.length === 0 || activeFilters.includes(rowStatus)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>

<?php
$conn->close();
include 'footer.php';
?>