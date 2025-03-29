<?php
session_start();
include_once 'dbconnection.php';
include_once 'voucher_helper.php'; // Đảm bảo include file helper

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

// Xử lý xóa đơn hàng
if (isset($_GET['delete'])) {
    $orderId = intval($_GET['delete']);

    // Hoàn lại voucher trước khi xóa bản ghi trong order_vouchers
    refundVoucher($conn, $orderId);

    // Xóa các mục trong order_items liên quan đến đơn hàng
    $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $stmt->close();

    // Xóa các voucher liên quan trong order_vouchers
    $stmt = $conn->prepare("DELETE FROM order_vouchers WHERE order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $stmt->close();

    // Xóa đơn hàng
    $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $stmt->close();

    header("Location: admin_order_manager.php?deleted=1");
    exit;
}

// Xử lý cập nhật trạng thái qua AJAX
if (isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['status'])) {
    $orderId = intval($_POST['order_id']);
    $newStatus = $conn->real_escape_string($_POST['status']);
    $completedAt = ($newStatus === 'Completed') ? date('Y-m-d H:i:s') : null;

    // Lấy trạng thái hiện tại của đơn hàng
    $stmt = $conn->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentOrder = $result->fetch_assoc();
    $stmt->close();

    if ($currentOrder) {
        $currentStatus = $currentOrder['status'];

        // Log để kiểm tra trạng thái
        error_log("Order ID: $orderId, Current Status: $currentStatus, New Status: $newStatus");

        // Cập nhật trạng thái và thời gian hoàn tất (nếu có)
        if ($newStatus === 'Completed') {
            $stmt = $conn->prepare("UPDATE orders SET status = ?, completed_at = ? WHERE id = ?");
            $stmt->bind_param("ssi", $newStatus, $completedAt, $orderId);
        } else {
            $stmt = $conn->prepare("UPDATE orders SET status = ?, completed_at = NULL WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $orderId);
        }

        if ($stmt->execute()) {
            // Nếu trạng thái mới là "Cancelled", hoàn lại voucher
            if ($newStatus === 'Cancelled' && $currentStatus !== 'Cancelled') {
                error_log("Calling refundVoucher for Order ID: $orderId");
                refundVoucher($conn, $orderId);
            }
            echo json_encode(['success' => true, 'new_status' => $newStatus, 'completed_at' => $completedAt]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Không thể cập nhật trạng thái.']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Đơn hàng không tồn tại.']);
    }
    exit;
}

// Xử lý cập nhật ghi chú qua AJAX
if (isset($_POST['update_note']) && isset($_POST['order_id']) && isset($_POST['note'])) {
    $orderId = intval($_POST['order_id']);
    $note = $conn->real_escape_string($_POST['note']);

    $stmt = $conn->prepare("UPDATE orders SET note = ? WHERE id = ?");
    $stmt->bind_param("si", $note, $orderId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
    exit;
}

// Xử lý lọc và tìm kiếm qua AJAX
if (isset($_POST['filter'])) {
    $statusFilter = isset($_POST['status']) && $_POST['status'] !== 'All' ? $conn->real_escape_string($_POST['status']) : null;
    $dateFrom = isset($_POST['date_from']) && !empty($_POST['date_from']) ? $conn->real_escape_string($_POST['date_from']) : null;
    $dateTo = isset($_POST['date_to']) && !empty($_POST['date_to']) ? $conn->real_escape_string($_POST['date_to']) : null;
    $search = isset($_POST['search']) && !empty($_POST['search']) ? $conn->real_escape_string($_POST['search']) : null;

    // Xây dựng truy vấn
    $query = "SELECT o.id, o.user_id, o.total_amount, o.final_amount, o.shipping_address, o.receiver_name, o.receiver_phone, o.payment_method, o.order_date, o.completed_at, o.status, o.note, u.username 
              FROM orders o 
              JOIN users u ON o.user_id = u.id";
    $conditions = [];
    $params = [];
    $types = '';

    // Lọc theo trạng thái
    if ($statusFilter) {
        $conditions[] = "o.status = ?";
        $params[] = $statusFilter;
        $types .= 's';
    }

    // Lọc theo ngày
    if ($dateFrom && $dateTo) {
        $conditions[] = "o.order_date BETWEEN ? AND ?";
        $params[] = $dateFrom . ' 00:00:00';
        $params[] = $dateTo . ' 23:59:59';
        $types .= 'ss';
    } elseif ($dateFrom) {
        $conditions[] = "o.order_date >= ?";
        $params[] = $dateFrom . ' 00:00:00';
        $types .= 's';
    } elseif ($dateTo) {
        $conditions[] = "o.order_date <= ?";
        $params[] = $dateTo . ' 23:59:59';
        $types .= 's';
    }

    // Tìm kiếm theo món ăn hoặc tên người đặt hàng
    if ($search) {
        $conditions[] = "(u.username LIKE ? OR EXISTS (
            SELECT 1 FROM order_items oi 
            JOIN menu m ON oi.item_id = m.id 
            WHERE oi.order_id = o.id AND m.combo_name LIKE ?
        ))";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }

    // Thêm điều kiện vào truy vấn
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }

    // Sắp xếp theo trạng thái: Pending → Shipping → Completed → Cancelled
    $query .= " ORDER BY FIELD(o.status, 'Pending', 'Shipping', 'Completed', 'Cancelled'), o.order_date DESC";

    // Chuẩn bị và thực thi truy vấn
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];

    while ($order = $result->fetch_assoc()) {
        // Lấy danh sách món ăn đã đặt
        $stmt_items = $conn->prepare("SELECT oi.item_id, oi.quantity, oi.price, m.combo_name 
                                      FROM order_items oi 
                                      JOIN menu m ON oi.item_id = m.id 
                                      WHERE oi.order_id = ?");
        $stmt_items->bind_param("i", $order['id']);
        $stmt_items->execute();
        $items_result = $stmt_items->get_result();
        $items = $items_result->fetch_all(MYSQLI_ASSOC);
        $stmt_items->close();

        // Lấy danh sách voucher đã áp dụng
        $stmt_vouchers = $conn->prepare("SELECT v.code, v.discount_percent, v.fixed_discount, v.max_discount_value 
                                         FROM order_vouchers ov 
                                         JOIN vouchers v ON ov.voucher_id = v.id 
                                         WHERE ov.order_id = ?");
        $stmt_vouchers->bind_param("i", $order['id']);
        $stmt_vouchers->execute();
        $vouchers_result = $stmt_vouchers->get_result();
        $vouchers = $vouchers_result->fetch_all(MYSQLI_ASSOC);
        $stmt_vouchers->close();

        $order['items'] = $items;
        $order['vouchers'] = $vouchers;
        $orders[] = $order;
    }
    $stmt->close();

    // Trả về HTML của bảng
    ob_start();
    if (empty($orders)) {
        echo '<p class="message">Không có đơn hàng nào để hiển thị.</p>';
    } else {
        ?>
        <table class="order-table">
            <tr>
                <th>ID</th>
                <th>Tên Người Dùng</th>
                <th>Danh Sách Món Ăn</th>
                <th>Voucher Đã Áp Dụng</th>
                <th>Giá Gốc</th>
                <th>Giảm Giá</th>
                <th>Thành Tiền</th>
                <th>Tên Người Nhận</th>
                <th>Số Điện Thoại</th>
                <th>Địa Chỉ Giao Hàng</th>
                <th>Thời Gian Đặt Hàng</th>
                <th>Thời Gian Nhận Hàng</th>
                <th>Ghi Chú</th>
                <th>Trạng Thái</th>
                <th>Hành Động</th>
            </tr>
            <?php foreach ($orders as $order): ?>
                <tr class="status-<?php echo strtolower($order['status']); ?>">
                    <td><?php echo $order['id']; ?></td>
                    <td><?php echo htmlspecialchars($order['username']); ?></td>
                    <td>
                        <ul class="items-list">
                            <?php foreach ($order['items'] as $item): ?>
                                <li>
                                    <?php echo htmlspecialchars($item['combo_name']) . " x " . $item['quantity'] . " (" . number_format($item['price'], 0, ',', '.') . " VND)"; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                    <td>
                        <?php
                        $discount = $order['total_amount'] - $order['final_amount'];
                        if (!empty($order['vouchers'])): ?>
                            <ul class="vouchers-list">
                                <?php foreach ($order['vouchers'] as $voucher): ?>
                                    <li>
                                        <?php
                                        $discountText = $voucher['fixed_discount'] ? "Giảm " . number_format($voucher['fixed_discount'], 0, ',', '.') . " VND" : "Giảm " . number_format($voucher['discount_percent'], 0) . "% (Tối đa " . number_format($voucher['max_discount_value'], 0, ',', '.') . " VND)";
                                        echo htmlspecialchars($voucher['code']) . " - $discountText";
                                        ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php elseif ($discount > 0): ?>
                            <span class="voucher-missing">Voucher không được ghi nhận (Giảm <?php echo number_format($discount, 0, ',', '.'); ?> VND)</span>
                        <?php else: ?>
                            Không có voucher
                        <?php endif; ?>
                    </td>
                    <td><?php echo number_format($order['total_amount'], 0, ',', '.'); ?> VND</td>
                    <td>
                        <?php
                        echo number_format($discount, 0, ',', '.'); ?> VND
                    </td>
                    <td><?php echo number_format($order['final_amount'], 0, ',', '.'); ?> VND</td>
                    <td><?php echo htmlspecialchars($order['receiver_name']); ?></td>
                    <td><?php echo htmlspecialchars($order['receiver_phone']); ?></td>
                    <td><?php echo htmlspecialchars($order['shipping_address']); ?></td>
                    <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                    <td class="completed-at"><?php echo $order['completed_at'] ? htmlspecialchars($order['completed_at']) : '-'; ?></td>
                    <td>
                        <textarea class="note-input" onblur="updateNote(<?php echo $order['id']; ?>, this)"><?php echo htmlspecialchars($order['note'] ?? ''); ?></textarea>
                    </td>
                    <td>
                        <select onchange="updateStatus(<?php echo $order['id']; ?>, this)">
                            <option value="Pending" <?php echo $order['status'] == 'Pending' ? 'selected' : ''; ?>>Đã đặt</option>
                            <option value="Shipping" <?php echo $order['status'] == 'Shipping' ? 'selected' : ''; ?>>Đang giao hàng</option>
                            <option value="Completed" <?php echo $order['status'] == 'Completed' ? 'selected' : ''; ?>>Đã hoàn tất</option>
                            <option value="Cancelled" <?php echo $order['status'] == 'Cancelled' ? 'selected' : ''; ?>>Đã hủy</option>
                        </select>
                    </td>
                    <td>
                        <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $order['id']; ?>)" class="delete-btn">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }
    $html = ob_get_clean();
    echo json_encode(['html' => $html]);
    exit;
}

// Lấy danh sách đơn hàng ban đầu (mặc định)
$query = "SELECT o.id, o.user_id, o.total_amount, o.final_amount, o.shipping_address, o.receiver_name, o.receiver_phone, o.payment_method, o.order_date, o.completed_at, o.status, o.note, u.username 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          ORDER BY FIELD(o.status, 'Pending', 'Shipping', 'Completed', 'Cancelled'), o.order_date DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];

while ($order = $result->fetch_assoc()) {
    // Lấy danh sách món ăn đã đặt
    $stmt_items = $conn->prepare("SELECT oi.item_id, oi.quantity, oi.price, m.combo_name 
                                  FROM order_items oi 
                                  JOIN menu m ON oi.item_id = m.id 
                                  WHERE oi.order_id = ?");
    $stmt_items->bind_param("i", $order['id']);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();
    $items = $items_result->fetch_all(MYSQLI_ASSOC);
    $stmt_items->close();

    // Lấy danh sách voucher đã áp dụng
    $stmt_vouchers = $conn->prepare("SELECT v.code, v.discount_percent, v.fixed_discount, v.max_discount_value 
                                     FROM order_vouchers ov 
                                     JOIN vouchers v ON ov.voucher_id = v.id 
                                     WHERE ov.order_id = ?");
    $stmt_vouchers->bind_param("i", $order['id']);
    $stmt_vouchers->execute();
    $vouchers_result = $stmt_vouchers->get_result();
    $vouchers = $vouchers_result->fetch_all(MYSQLI_ASSOC);
    $stmt_vouchers->close();

    $order['items'] = $items;
    $order['vouchers'] = $vouchers;
    $orders[] = $order;
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
    <title>Quản Lý Đơn Hàng</title>
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

        .order-manager-container {
            max-width: 2000px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .filter-section {
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-section label {
            font-weight: bold;
            margin-right: 10px;
        }

        .filter-section select, .filter-section input {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }

        .filter-section .reset-icon {
            color: rgb(66, 65, 65);
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.3s;
        }

        .filter-section .reset-icon:hover {
            color: rgb(145, 143, 143);
        }

        .order-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .order-table th, .order-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .order-table th {
            background-color: #f5f5f5;
        }

        /* Thêm màu nền cho các trạng thái */
        .order-table tr.status-pending {
            background-color: #e6f9e6; /* Xanh nhạt cho trạng thái Đã đặt */
        }

        .order-table tr.status-completed,
        .order-table tr.status-cancelled {
            background-color: #f0f0f0; /* Xám nhạt cho trạng thái Đã hoàn tất hoặc Đã hủy */
        }

        .order-table .items-list, .order-table .vouchers-list {
            list-style: none;
            padding: 0;
        }

        .order-table .items-list li, .order-table .vouchers-list li {
            margin: 5px 0;
        }

        .order-table select {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .order-table .delete-btn {
            color: red;
            text-decoration: none;
            font-size: 1.2rem;
        }

        .order-table .delete-btn:hover {
            color: darkred;
        }

        .order-table .note-input {
            width: 100%;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
            resize: none;
            height: 50px;
        }

        .message {
            text-align: center;
            margin-bottom: 20px;
        }

        .success {
            color: green;
        }

        .voucher-missing {
            color: #e74c3c; /* Màu đỏ cho thông báo voucher không được ghi nhận */
            font-style: italic;
        }
    </style>
    <script>
        function updateStatus(orderId, selectElement) {
            const status = selectElement.value;
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "admin_order_manager.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert("Cập nhật trạng thái thành công!");
                        // Cập nhật màu nền của hàng
                        const row = selectElement.closest('tr');
                        row.className = ''; // Xóa tất cả class hiện tại
                        row.classList.add('status-' + response.new_status.toLowerCase());
                        // Cập nhật cột Thời Gian Nhận Hàng
                        const completedAtCell = row.querySelector('.completed-at');
                        completedAtCell.textContent = response.completed_at ? response.completed_at : '-';
                    } else {
                        alert("Lỗi: " + response.error);
                    }
                }
            };
            xhr.send(`update_status=1&order_id=${orderId}&status=${status}`);
        }

        function updateNote(orderId, textareaElement) {
            const note = textareaElement.value;
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "admin_order_manager.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert("Cập nhật ghi chú thành công!");
                    }
                }
            };
            xhr.send(`update_note=1&order_id=${orderId}&note=${encodeURIComponent(note)}`); // Sửa lỗi cú pháp
        }

        function confirmDelete(orderId) {
            if (confirm("Bạn có chắc chắn muốn xóa đơn hàng này?")) {
                window.location.href = `admin_order_manager.php?delete=${orderId}`;
            }
        }

        function filterOrders() {
            const status = document.getElementById('status-filter').value;
            const dateFrom = document.getElementById('date-from').value;
            const dateTo = document.getElementById('date-to').value;
            const search = document.getElementById('search-input').value;

            const xhr = new XMLHttpRequest();
            xhr.open("POST", "admin_order_manager.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    document.getElementById('order-table-container').innerHTML = response.html;
                }
            };
            xhr.send(`filter=1&status=${status}&date_from=${dateFrom}&date_to=${dateTo}&search=${encodeURIComponent(search)}`);
        }

        function resetFilters() {
            document.getElementById('status-filter').value = 'All';
            document.getElementById('date-from').value = '';
            document.getElementById('date-to').value = '';
            document.getElementById('search-input').value = '';
            filterOrders();
        }
    </script>
</head>
<body>
    <div class="order-manager-container">
        <h1>Quản Lý Đơn Hàng</h1>

        <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
            <p class="message success">Xóa đơn hàng thành công!</p>
        <?php endif; ?>

        <!-- Bộ lọc -->
        <div class="filter-section">
            <div>
                <label for="status-filter">Trạng thái:</label>
                <select id="status-filter" onchange="filterOrders()">
                    <option value="All">Tất cả</option>
                    <option value="Pending">Đã đặt</option>
                    <option value="Shipping">Đang giao hàng</option>
                    <option value="Completed">Đã hoàn tất</option>
                    <option value="Cancelled">Đã hủy</option>
                </select>
            </div>
            <div>
                <label for="date-from">Từ ngày:</label>
                <input type="date" id="date-from" onchange="filterOrders()">
            </div>
            <div>
                <label for="date-to">Đến ngày:</label>
                <input type="date" id="date-to" onchange="filterOrders()">
            </div>
            <div>
                <label for="search-input">Tìm kiếm (món ăn/tên người đặt):</label>
                <input type="text" id="search-input" oninput="filterOrders()" placeholder="Nhập món ăn hoặc tên người đặt">
            </div>
            <div>
                <i class="fas fa-times-circle reset-icon" onclick="resetFilters()" title="Xóa bộ lọc"></i>
            </div>
        </div>

        <!-- Bảng đơn hàng -->
        <div id="order-table-container">
            <?php if (empty($orders)): ?>
                <p class="message">Không có đơn hàng nào để hiển thị.</p>
            <?php else: ?>
                <table class="order-table">
                    <tr>
                        <th>ID</th>
                        <th>Tên Người Dùng</th>
                        <th>Danh Sách Món Ăn</th>
                        <th>Voucher Đã Áp Dụng</th>
                        <th>Giá Gốc</th>
                        <th>Giảm Giá</th>
                        <th>Thành Tiền</th>
                        <th>Tên Người Nhận</th>
                        <th>Số Điện Thoại</th>
                        <th>Địa Chỉ Giao Hàng</th>
                        <th>Thời Gian Đặt Hàng</th>
                        <th>Thời Gian Nhận Hàng</th>
                        <th>Ghi Chú</th>
                        <th>Trạng Thái</th>
                        <th>Hành Động</th>
                    </tr>
                    <?php foreach ($orders as $order): ?>
                        <tr class="status-<?php echo strtolower($order['status']); ?>">
                            <td><?php echo $order['id']; ?></td>
                            <td><?php echo htmlspecialchars($order['username']); ?></td>
                            <td>
                                <ul class="items-list">
                                    <?php foreach ($order['items'] as $item): ?>
                                        <li>
                                            <?php echo htmlspecialchars($item['combo_name']) . " x " . $item['quantity'] . " (" . number_format($item['price'], 0, ',', '.') . " VND)"; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td>
                                <?php
                                $discount = $order['total_amount'] - $order['final_amount'];
                                if (!empty($order['vouchers'])): ?>
                                    <ul class="vouchers-list">
                                        <?php foreach ($order['vouchers'] as $voucher): ?>
                                            <li>
                                                <?php
                                                $discountText = $voucher['fixed_discount'] ? "Giảm " . number_format($voucher['fixed_discount'], 0, ',', '.') . " VND" : "Giảm " . number_format($voucher['discount_percent'], 0) . "% (Tối đa " . number_format($voucher['max_discount_value'], 0, ',', '.') . " VND)";
                                                echo htmlspecialchars($voucher['code']) . " - $discountText";
                                                ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php elseif ($discount > 0): ?>
                                    <span class="voucher-missing">Voucher không được ghi nhận (Giảm <?php echo number_format($discount, 0, ',', '.'); ?> VND)</span>
                                <?php else: ?>
                                    Không có voucher
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($order['total_amount'], 0, ',', '.'); ?> VND</td>
                            <td>
                                <?php
                                echo number_format($discount, 0, ',', '.'); ?> VND
                            </td>
                            <td><?php echo number_format($order['final_amount'], 0, ',', '.'); ?> VND</td>
                            <td><?php echo htmlspecialchars($order['receiver_name']); ?></td>
                            <td><?php echo htmlspecialchars($order['receiver_phone']); ?></td>
                            <td><?php echo htmlspecialchars($order['shipping_address']); ?></td>
                            <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                            <td class="completed-at"><?php echo $order['completed_at'] ? htmlspecialchars($order['completed_at']) : '-'; ?></td>
                            <td>
                                <textarea class="note-input" onblur="updateNote(<?php echo $order['id']; ?>, this)"><?php echo htmlspecialchars($order['note'] ?? ''); ?></textarea>
                            </td>
                            <td>
                                <select onchange="updateStatus(<?php echo $order['id']; ?>, this)">
                                    <option value="Pending" <?php echo $order['status'] == 'Pending' ? 'selected' : ''; ?>>Đã đặt</option>
                                    <option value="Shipping" <?php echo $order['status'] == 'Shipping' ? 'selected' : ''; ?>>Đang giao hàng</option>
                                    <option value="Completed" <?php echo $order['status'] == 'Completed' ? 'selected' : ''; ?>>Đã hoàn tất</option>
                                    <option value="Cancelled" <?php echo $order['status'] == 'Cancelled' ? 'selected' : ''; ?>>Đã hủy</option>
                                </select>
                            </td>
                            <td>
                                <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $order['id']; ?>)" class="delete-btn">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
include 'footer.php';
?>