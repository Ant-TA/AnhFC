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

// Xử lý xóa người dùng
if (isset($_GET['delete'])) {
    $deleteUserId = intval($_GET['delete']);

    // Lấy danh sách đơn hàng của người dùng
    $stmt = $conn->prepare("SELECT id FROM orders WHERE user_id = ?");
    $stmt->bind_param("i", $deleteUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $orderIds = [];
    while ($row = $result->fetch_assoc()) {
        $orderIds[] = $row['id'];
    }
    $stmt->close();

    // Xóa các mục trong order_items liên quan đến đơn hàng
    foreach ($orderIds as $orderId) {
        $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $stmt->close();

        // Xóa các voucher liên quan trong order_vouchers
        $stmt = $conn->prepare("DELETE FROM order_vouchers WHERE order_id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $stmt->close();
    }

    // Xóa các đơn hàng của người dùng
    $stmt = $conn->prepare("DELETE FROM orders WHERE user_id = ?");
    $stmt->bind_param("i", $deleteUserId);
    $stmt->execute();
    $stmt->close();

    // Xóa người dùng
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
    $stmt->bind_param("i", $deleteUserId);
    $stmt->execute();
    $stmt->close();

    header("Location: admin_user_manager.php?deleted=1");
    exit;
}

// Xử lý cập nhật trạng thái hạn chế COD qua AJAX
if (isset($_POST['update_restrict_cod']) && isset($_POST['user_id']) && isset($_POST['restrict_cod'])) {
    $userId = intval($_POST['user_id']);
    $restrictCod = intval($_POST['restrict_cod']);

    $stmt = $conn->prepare("UPDATE users SET restrict_cod = ? WHERE id = ? AND is_admin = 0");
    $stmt->bind_param("ii", $restrictCod, $userId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'restrict_cod' => $restrictCod]);
    exit;
}

// Xử lý cập nhật trạng thái hạn chế đặt hàng qua AJAX
if (isset($_POST['update_restrict_order']) && isset($_POST['user_id']) && isset($_POST['restrict_order'])) {
    $userId = intval($_POST['user_id']);
    $restrictOrder = intval($_POST['restrict_order']);

    $stmt = $conn->prepare("UPDATE users SET restrict_order = ? WHERE id = ? AND is_admin = 0");
    $stmt->bind_param("ii", $restrictOrder, $userId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'restrict_order' => $restrictOrder]);
    exit;
}

// Xử lý lọc, tìm kiếm và sắp xếp qua AJAX
if (isset($_POST['filter'])) {
    $statusFilter = isset($_POST['status']) && $_POST['status'] !== 'All' ? $conn->real_escape_string($_POST['status']) : null;
    $search = isset($_POST['search']) && !empty($_POST['search']) ? $conn->real_escape_string($_POST['search']) : null;
    $sortColumn = isset($_POST['sort_column']) ? $conn->real_escape_string($_POST['sort_column']) : 'created_at';
    $sortOrder = isset($_POST['sort_order']) && $_POST['sort_order'] === 'ASC' ? 'ASC' : 'DESC';

    // Xây dựng truy vấn
    $query = "SELECT id, username, phone, address, created_at, restrict_cod, restrict_order 
              FROM users 
              WHERE is_admin = 0";
    $conditions = [];
    $params = [];
    $types = '';

    // Lọc theo trạng thái
    if ($statusFilter === 'Normal') {
        $conditions[] = "restrict_cod = 0 AND restrict_order = 0";
    } elseif ($statusFilter === 'RestrictedCOD') {
        $conditions[] = "restrict_cod = 1";
    } elseif ($statusFilter === 'RestrictedOrder') {
        $conditions[] = "restrict_order = 1";
    }

    // Tìm kiếm theo tên người dùng
    if ($search) {
        $conditions[] = "username LIKE ?";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $types .= 's';
    }

    // Thêm điều kiện vào truy vấn
    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    // Sắp xếp
    $validColumns = ['username', 'order_count', 'total_spent', 'first_order_date', 'last_order_date', 'created_at'];
    if (!in_array($sortColumn, $validColumns)) {
        $sortColumn = 'created_at';
    }
    $query .= " ORDER BY $sortColumn $sortOrder";

    // Chuẩn bị và thực thi truy vấn
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];

    while ($user = $result->fetch_assoc()) {
        // Số đơn hàng đã đặt
        $stmt_orders = $conn->prepare("SELECT COUNT(*) as order_count 
                                       FROM orders 
                                       WHERE user_id = ?");
        $stmt_orders->bind_param("i", $user['id']);
        $stmt_orders->execute();
        $order_count_result = $stmt_orders->get_result();
        $user['order_count'] = $order_count_result->fetch_assoc()['order_count'];
        $stmt_orders->close();

        // Tổng chi tiêu (tính tất cả đơn hàng Completed, dựa trên final_amount)
        $stmt_total = $conn->prepare("SELECT SUM(final_amount) as total_spent 
                                      FROM orders 
                                      WHERE user_id = ? AND status = 'Completed'");
        $stmt_total->bind_param("i", $user['id']);
        $stmt_total->execute();
        $total_result = $stmt_total->get_result();
        $user['total_spent'] = $total_result->fetch_assoc()['total_spent'] ?? 0;
        $stmt_total->close();

        // Ngày mua lần đầu
        $stmt_first = $conn->prepare("SELECT MIN(order_date) as first_order_date 
                                      FROM orders 
                                      WHERE user_id = ?");
        $stmt_first->bind_param("i", $user['id']);
        $stmt_first->execute();
        $first_result = $stmt_first->get_result();
        $user['first_order_date'] = $first_result->fetch_assoc()['first_order_date'] ?? '-';
        $stmt_first->close();

        // Ngày mua gần nhất
        $stmt_last = $conn->prepare("SELECT MAX(order_date) as last_order_date 
                                     FROM orders 
                                     WHERE user_id = ?");
        $stmt_last->bind_param("i", $user['id']);
        $stmt_last->execute();
        $last_result = $stmt_last->get_result();
        $user['last_order_date'] = $last_result->fetch_assoc()['last_order_date'] ?? '-';
        $stmt_last->close();

        $users[] = $user;
    }
    $stmt->close();

    // Trả về HTML của bảng
    ob_start();
    if (empty($users)) {
        echo '<p class="message">Không có người dùng nào để hiển thị.</p>';
    } else {
        ?>
        <table class="user-table">
            <tr>
                <th class="sortable" data-column="username">Tên Người Dùng</th>
                <th>Số Điện Thoại</th>
                <th>Địa Chỉ</th>
                <th class="sortable" data-column="order_count">Số Đơn Hàng</th>
                <th class="sortable" data-column="total_spent">Tổng Chi Tiêu</th>
                <th class="sortable" data-column="first_order_date">Ngày Mua Lần Đầu</th>
                <th class="sortable" data-column="last_order_date">Ngày Mua Gần Nhất</th>
                <th class="sortable" data-column="created_at">Ngày Tạo Tài Khoản</th>
                <th>Hành Động</th>
            </tr>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($user['address'] ?? '-'); ?></td>
                    <td><?php echo $user['order_count']; ?></td>
                    <td><?php echo number_format($user['total_spent'], 0, ',', '.'); ?> VND</td>
                    <td><?php echo htmlspecialchars($user['first_order_date']); ?></td>
                    <td><?php echo htmlspecialchars($user['last_order_date']); ?></td>
                    <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                    <td>
                        <a href="javascript:void(0)" 
                           onclick="toggleRestrictCod(<?php echo $user['id']; ?>, this)" 
                           class="restrict-btn <?php echo $user['restrict_cod'] ? 'restricted' : ''; ?>" 
                           title="<?php echo $user['restrict_cod'] ? 'Bỏ hạn chế COD' : 'Hạn chế COD'; ?>">
                            <i class="fas fa-ban"></i>
                        </a>
                        <a href="javascript:void(0)" 
                           onclick="toggleRestrictOrder(<?php echo $user['id']; ?>, this)" 
                           class="restrict-order-btn <?php echo $user['restrict_order'] ? 'restricted' : ''; ?>" 
                           title="<?php echo $user['restrict_order'] ? 'Bỏ hạn chế đặt hàng' : 'Hạn chế đặt hàng'; ?>">
                            <i class="fas fa-shopping-cart"></i>
                        </a>
                        <a href="javascript:void(0)" 
                           onclick="confirmDelete(<?php echo $user['id']; ?>)" 
                           class="delete-btn" 
                           title="Xóa người dùng">
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

// Lấy danh sách người dùng ban đầu (mặc định)
$users = [];
$stmt = $conn->prepare("SELECT id, username, phone, address, created_at, restrict_cod, restrict_order 
                        FROM users 
                        WHERE is_admin = 0 
                        ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();

while ($user = $result->fetch_assoc()) {
    // Số đơn hàng đã đặt
    $stmt_orders = $conn->prepare("SELECT COUNT(*) as order_count 
                                   FROM orders 
                                   WHERE user_id = ?");
    $stmt_orders->bind_param("i", $user['id']);
    $stmt_orders->execute();
    $order_count_result = $stmt_orders->get_result();
    $user['order_count'] = $order_count_result->fetch_assoc()['order_count'];
    $stmt_orders->close();

    // Tổng chi tiêu (tính tất cả đơn hàng Completed, dựa trên final_amount)
    $stmt_total = $conn->prepare("SELECT SUM(final_amount) as total_spent 
                                  FROM orders 
                                  WHERE user_id = ? AND status = 'Completed'");
    $stmt_total->bind_param("i", $user['id']);
    $stmt_total->execute();
    $total_result = $stmt_total->get_result();
    $user['total_spent'] = $total_result->fetch_assoc()['total_spent'] ?? 0;
    $stmt_total->close();

    // Ngày mua lần đầu
    $stmt_first = $conn->prepare("SELECT MIN(order_date) as first_order_date 
                                  FROM orders 
                                  WHERE user_id = ?");
    $stmt_first->bind_param("i", $user['id']);
    $stmt_first->execute();
    $first_result = $stmt_first->get_result();
    $user['first_order_date'] = $first_result->fetch_assoc()['first_order_date'] ?? '-';
    $stmt_first->close();

    // Ngày mua gần nhất
    $stmt_last = $conn->prepare("SELECT MAX(order_date) as last_order_date 
                                 FROM orders 
                                 WHERE user_id = ?");
    $stmt_last->bind_param("i", $user['id']);
    $stmt_last->execute();
    $last_result = $stmt_last->get_result();
    $user['last_order_date'] = $last_result->fetch_assoc()['last_order_date'] ?? '-';
    $stmt_last->close();

    $users[] = $user;
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
    <title>Quản Lý Người Dùng</title>
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

        .user-manager-container {
            max-width: 1500px;
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
            color: #e74c3c;
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.3s;
        }

        .filter-section .reset-icon:hover {
            color: #c0392b;
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .user-table th, .user-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .user-table th {
            background-color: #f5f5f5;
            cursor: pointer;
        }

        .user-table th.sortable:hover {
            background-color: #e0e0e0;
        }

        .user-table th.sortable::after {
            content: '\f0dc'; /* Font Awesome sort icon */
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            margin-left: 5px;
            color: #666;
        }

        .user-table th.sort-asc::after {
            content: '\f0de'; /* Font Awesome sort-up icon */
        }

        .user-table th.sort-desc::after {
            content: '\f0dd'; /* Font Awesome sort-down icon */
        }

        .user-table .restrict-btn, .user-table .restrict-order-btn, .user-table .delete-btn {
            color: #e74c3c;
            text-decoration: none;
            font-size: 1.2rem;
            margin-right: 10px;
            cursor: pointer;
        }

        .user-table .restrict-btn.restricted, .user-table .restrict-order-btn.restricted {
            color: #2ecc71;
        }

        .user-table .restrict-btn:hover, .user-table .restrict-order-btn:hover {
            color: #c0392b;
        }

        .user-table .restrict-btn.restricted:hover, .user-table .restrict-order-btn.restricted:hover {
            color: #27ae60;
        }

        .user-table .delete-btn:hover {
            color: #c0392b;
        }

        .message {
            text-align: center;
            margin-bottom: 20px;
        }

        .success {
            color: green;
        }
    </style>
    <script>
        let currentSortColumn = 'created_at';
        let currentSortOrder = 'DESC';

        function toggleRestrictCod(userId, element) {
            const restrictCod = element.classList.contains('restricted') ? 0 : 1;
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "admin_user_manager.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        if (response.restrict_cod == 1) {
                            element.classList.add('restricted');
                            element.title = "Bỏ hạn chế COD";
                        } else {
                            element.classList.remove('restricted');
                            element.title = "Hạn chế COD";
                        }
                        alert("Cập nhật trạng thái hạn chế COD thành công!");
                    }
                }
            };
            xhr.send(`update_restrict_cod=1&user_id=${userId}&restrict_cod=${restrictCod}`);
        }

        function toggleRestrictOrder(userId, element) {
            const restrictOrder = element.classList.contains('restricted') ? 0 : 1;
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "admin_user_manager.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        if (response.restrict_order == 1) {
                            element.classList.add('restricted');
                            element.title = "Bỏ hạn chế đặt hàng";
                        } else {
                            element.classList.remove('restricted');
                            element.title = "Hạn chế đặt hàng";
                        }
                        alert("Cập nhật trạng thái hạn chế đặt hàng thành công!");
                    }
                }
            };
            xhr.send(`update_restrict_order=1&user_id=${userId}&restrict_order=${restrictOrder}`);
        }

        function confirmDelete(userId) {
            if (confirm("Bạn có chắc chắn muốn xóa người dùng này? Tất cả đơn hàng của họ cũng sẽ bị xóa.")) {
                window.location.href = `admin_user_manager.php?delete=${userId}`;
            }
        }

        function filterUsers() {
            const status = document.getElementById('status-filter').value;
            const search = document.getElementById('search-input').value;

            const xhr = new XMLHttpRequest();
            xhr.open("POST", "admin_user_manager.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    document.getElementById('user-table-container').innerHTML = response.html;
                    updateSortIcons();
                }
            };
            xhr.send(`filter=1&status=${status}&search=${encodeURIComponent(search)}&sort_column=${currentSortColumn}&sort_order=${currentSortOrder}`);
        }

        function sortTable(column) {
            if (currentSortColumn === column) {
                currentSortOrder = currentSortOrder === 'ASC' ? 'DESC' : 'ASC';
            } else {
                currentSortColumn = column;
                currentSortOrder = 'ASC';
            }
            filterUsers();
        }

        function updateSortIcons() {
            document.querySelectorAll('.sortable').forEach(th => {
                th.classList.remove('sort-asc', 'sort-desc');
                if (th.getAttribute('data-column') === currentSortColumn) {
                    th.classList.add(currentSortOrder === 'ASC' ? 'sort-asc' : 'sort-desc');
                }
            });
        }

        function resetFilters() {
            document.getElementById('status-filter').value = 'All';
            document.getElementById('search-input').value = '';
            currentSortColumn = 'created_at';
            currentSortOrder = 'DESC';
            filterUsers();
        }
    </script>
</head>
<body>
    <div class="user-manager-container">
        <h1>Quản Lý Người Dùng</h1>

        <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
            <p class="message success">Xóa người dùng thành công!</p>
        <?php endif; ?>

        <!-- Bộ lọc -->
        <div class="filter-section">
            <div>
                <label for="status-filter">Trạng thái:</label>
                <select id="status-filter" onchange="filterUsers()">
                    <option value="All">Tất cả</option>
                    <option value="Normal">Người dùng bình thường</option>
                    <option value="RestrictedCOD">Người dùng bị hạn chế COD</option>
                    <option value="RestrictedOrder">Người dùng bị hạn chế đặt hàng</option>
                </select>
            </div>
            <div>
                <label for="search-input">Tìm kiếm theo tên:</label>
                <input type="text" id="search-input" oninput="filterUsers()" placeholder="Nhập tên người dùng">
            </div>
            <div>
                <i class="fas fa-times-circle reset-icon" onclick="resetFilters()" title="Xóa bộ lọc"></i>
            </div>
        </div>

        <!-- Bảng người dùng -->
        <div id="user-table-container">
            <?php if (empty($users)): ?>
                <p class="message">Không có người dùng nào để hiển thị.</p>
            <?php else: ?>
                <table class="user-table">
                    <tr>
                        <th class="sortable" data-column="username" onclick="sortTable('username')">Tên Người Dùng</th>
                        <th>Số Điện Thoại</th>
                        <th>Địa Chỉ</th>
                        <th class="sortable" data-column="order_count" onclick="sortTable('order_count')">Số Đơn Hàng</th>
                        <th class="sortable" data-column="total_spent" onclick="sortTable('total_spent')">Tổng Chi Tiêu</th>
                        <th class="sortable" data-column="first_order_date" onclick="sortTable('first_order_date')">Ngày Mua Lần Đầu</th>
                        <th class="sortable" data-column="last_order_date" onclick="sortTable('last_order_date')">Ngày Mua Gần Nhất</th>
                        <th class="sortable" data-column="created_at" onclick="sortTable('created_at')">Ngày Tạo Tài Khoản</th>
                        <th>Hành Động</th>
                    </tr>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['address'] ?? '-'); ?></td>
                            <td><?php echo $user['order_count']; ?></td>
                            <td><?php echo number_format($user['total_spent'], 0, ',', '.'); ?> VND</td>
                            <td><?php echo htmlspecialchars($user['first_order_date']); ?></td>
                            <td><?php echo htmlspecialchars($user['last_order_date']); ?></td>
                            <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                            <td>
                                <a href="javascript:void(0)" 
                                   onclick="toggleRestrictCod(<?php echo $user['id']; ?>, this)" 
                                   class="restrict-btn <?php echo $user['restrict_cod'] ? 'restricted' : ''; ?>" 
                                   title="<?php echo $user['restrict_cod'] ? 'Bỏ hạn chế COD' : 'Hạn chế COD'; ?>">
                                    <i class="fas fa-ban"></i>
                                </a>
                                <a href="javascript:void(0)" 
                                   onclick="toggleRestrictOrder(<?php echo $user['id']; ?>, this)" 
                                   class="restrict-order-btn <?php echo $user['restrict_order'] ? 'restricted' : ''; ?>" 
                                   title="<?php echo $user['restrict_order'] ? 'Bỏ hạn chế đặt hàng' : 'Hạn chế đặt hàng'; ?>">
                                    <i class="fas fa-shopping-cart"></i>
                                </a>
                                <a href="javascript:void(0)" 
                                   onclick="confirmDelete(<?php echo $user['id']; ?>)" 
                                   class="delete-btn" 
                                   title="Xóa người dùng">
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