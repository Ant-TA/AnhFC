<?php
include 'check_admin.php';

// Xử lý các hành động CRUD
$errors = [];
$success = '';

// Xử lý tạo voucher mới (Create)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $code = trim($_POST['code']);
    $discountPercent = !empty($_POST['discount_percent']) ? floatval($_POST['discount_percent']) : null;
    $maxDiscountValue = !empty($_POST['max_discount_value']) ? floatval($_POST['max_discount_value']) : null;
    $fixedDiscount = !empty($_POST['fixed_discount']) ? floatval($_POST['fixed_discount']) : null;
    $minOrderValue = !empty($_POST['min_order_value']) ? floatval($_POST['min_order_value']) : null;
    $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $quantity = isset($_POST['quantity']) && $_POST['quantity'] !== '' ? intval($_POST['quantity']) : null;
    $isPublic = isset($_POST['is_public']) ? 1 : 0; // Thêm trạng thái công khai

    // Kiểm tra dữ liệu đầu vào
    if (empty($code)) {
        $errors[] = "Mã voucher không được để trống.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM vouchers WHERE code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Mã voucher đã tồn tại.";
        }
        $stmt->close();
    }

    if (empty($discountPercent) && empty($fixedDiscount)) {
        $errors[] = "Phải chọn ít nhất một loại giảm giá: phần trăm hoặc cố định.";
    }
    if (!empty($discountPercent) && !empty($fixedDiscount)) {
        $errors[] = "Chỉ được chọn một loại giảm giá: phần trăm hoặc cố định.";
    }

    if (!empty($discountPercent)) {
        if ($discountPercent <= 0 || $discountPercent > 100) {
            $errors[] = "Giảm giá phần trăm phải nằm trong khoảng 0 đến 100.";
        }
        if (empty($maxDiscountValue) || $maxDiscountValue <= 0) {
            $errors[] = "Giá trị giảm giá tối đa phải lớn hơn 0 khi chọn giảm giá phần trăm.";
        }
    }

    if (!empty($fixedDiscount)) {
        if ($fixedDiscount <= 0) {
            $errors[] = "Giá trị giảm giá cố định phải lớn hơn 0.";
        }
        if (empty($minOrderValue) || $minOrderValue <= 0) {
            $errors[] = "Giá trị đơn hàng tối thiểu phải lớn hơn 0 khi chọn giảm giá cố định.";
        }
        if ($minOrderValue <= $fixedDiscount) {
            $errors[] = "Giá trị đơn hàng tối thiểu phải lớn hơn giá trị giảm giá cố định.";
        }
    }

    if (!empty($expiryDate)) {
        $expiryDateTime = DateTime::createFromFormat('Y-m-d\TH:i', $expiryDate);
        $currentDateTime = new DateTime();
        if ($expiryDateTime === false || $expiryDateTime < $currentDateTime) {
            $errors[] = "Ngày đến hạn phải là một ngày trong tương lai.";
        } else {
            $expiryDate = $expiryDateTime->format('Y-m-d H:i:s');
        }
    } else {
        $expiryDate = null;
    }

    if (isset($quantity) && $quantity <= 0) {
        $errors[] = "Số lượng phải lớn hơn 0.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO vouchers (code, discount_percent, max_discount_value, fixed_discount, min_order_value, expiry_date, quantity, is_public) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sddddsii", $code, $discountPercent, $maxDiscountValue, $fixedDiscount, $minOrderValue, $expiryDate, $quantity, $isPublic);
        if ($stmt->execute()) {
            $success = "Tạo voucher thành công!";
        } else {
            $errors[] = "Có lỗi xảy ra khi tạo voucher. Vui lòng thử lại.";
        }
        $stmt->close();
    }
}

// Xử lý cập nhật voucher (Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = intval($_POST['id']);
    $code = trim($_POST['code']);
    $discountPercent = !empty($_POST['discount_percent']) ? floatval($_POST['discount_percent']) : null;
    $maxDiscountValue = !empty($_POST['max_discount_value']) ? floatval($_POST['max_discount_value']) : null;
    $fixedDiscount = !empty($_POST['fixed_discount']) ? floatval($_POST['fixed_discount']) : null;
    $minOrderValue = !empty($_POST['min_order_value']) ? floatval($_POST['min_order_value']) : null;
    $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $quantity = isset($_POST['quantity']) && $_POST['quantity'] !== '' ? intval($_POST['quantity']) : null;
    $isPublic = isset($_POST['is_public']) ? 1 : 0; // Thêm trạng thái công khai

    if (empty($code)) {
        $errors[] = "Mã voucher không được để trống.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM vouchers WHERE code = ? AND id != ?");
        $stmt->bind_param("si", $code, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Mã voucher đã tồn tại.";
        }
        $stmt->close();
    }

    if (empty($discountPercent) && empty($fixedDiscount)) {
        $errors[] = "Phải chọn ít nhất một loại giảm giá: phần trăm hoặc cố định.";
    }
    if (!empty($discountPercent) && !empty($fixedDiscount)) {
        $errors[] = "Chỉ được chọn một loại giảm giá: phần trăm hoặc cố định.";
    }

    if (!empty($discountPercent)) {
        if ($discountPercent <= 0 || $discountPercent > 100) {
            $errors[] = "Giảm giá phần trăm phải nằm trong khoảng 0 đến 100.";
        }
        if (empty($maxDiscountValue) || $maxDiscountValue <= 0) {
            $errors[] = "Giá trị giảm giá tối đa phải lớn hơn 0 khi chọn giảm giá phần trăm.";
        }
    }

    if (!empty($fixedDiscount)) {
        if ($fixedDiscount <= 0) {
            $errors[] = "Giá trị giảm giá cố định phải lớn hơn 0.";
        }
        if (empty($minOrderValue) || $minOrderValue <= 0) {
            $errors[] = "Giá trị đơn hàng tối thiểu phải lớn hơn 0 khi chọn giảm giá cố định.";
        }
        if ($minOrderValue <= $fixedDiscount) {
            $errors[] = "Giá trị đơn hàng tối thiểu phải lớn hơn giá trị giảm giá cố định.";
        }
    }

    if (!empty($expiryDate)) {
        $expiryDateTime = DateTime::createFromFormat('Y-m-d\TH:i', $expiryDate);
        $currentDateTime = new DateTime();
        if ($expiryDateTime === false || $expiryDateTime < $currentDateTime) {
            $errors[] = "Ngày đến hạn phải là một ngày trong tương lai.";
        } else {
            $expiryDate = $expiryDateTime->format('Y-m-d H:i:s');
        }
    } else {
        $expiryDate = null;
    }

    if (isset($quantity) && $quantity < 0) {
        $errors[] = "Số lượng không được nhỏ hơn 0.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE vouchers SET code = ?, discount_percent = ?, max_discount_value = ?, fixed_discount = ?, min_order_value = ?, expiry_date = ?, quantity = ?, is_public = ? WHERE id = ?");
        $stmt->bind_param("sddddsiii", $code, $discountPercent, $maxDiscountValue, $fixedDiscount, $minOrderValue, $expiryDate, $quantity, $isPublic, $id);
        if ($stmt->execute()) {
            $success = "Cập nhật voucher thành công!";
        } else {
            $errors[] = "Có lỗi xảy ra khi cập nhật voucher. Vui lòng thử lại.";
        }
        $stmt->close();
    }
}

// Xử lý xóa voucher (Delete)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM vouchers WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success = "Xóa voucher thành công!";
    } else {
        $errors[] = "Có lỗi xảy ra khi xóa voucher. Vui lòng thử lại.";
    }
    $stmt->close();
}

// Lấy danh sách voucher hiện tại (Read)
$result = $conn->query("SELECT * FROM vouchers ORDER BY created_at DESC");
$vouchers = $result->fetch_all(MYSQLI_ASSOC);

// Lấy thông tin voucher để chỉnh sửa (nếu có)
$editVoucher = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM vouchers WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $editVoucher = $result->fetch_assoc();
    $stmt->close();
}
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
    <title>Quản Lý Voucher</title>
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

        h1, h2 {
            text-align: center;
            color: #333;
        }

        .form-section, .list-section {
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }

        .form-group input[type="number"],
        .form-group input[type="datetime-local"],
        .form-group input[type="checkbox"] {
            width: auto;
        }

        .error, .success {
            text-align: center;
            margin-bottom: 15px;
        }

        .error {
            color: #e74c3c;
        }

        .success {
            color: #2ecc71;
        }

        .form-group button {
            padding: 10px 20px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .form-group button:hover {
            background-color: #555;
        }

        .voucher-list {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .voucher-list th, .voucher-list td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }

        .voucher-list th {
            background-color: #f5f5f5;
            color: #333;
        }

        .voucher-list tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .voucher-list tr:hover {
            background-color: #f1f1f1;
        }

        .voucher-list .action a {
            color: #333;
            text-decoration: none;
            margin: 0 5px;
        }

        .voucher-list .action a.edit {
            color: #3498db;
        }

        .voucher-list .action a.delete {
            color: #e74c3c;
        }

        .voucher-list .action a:hover {
            text-decoration: underline;
        }

        .no-vouchers {
            text-align: center;
            color: #666;
            padding: 20px;
        }
    </style>
    <script>
        function confirmDelete(voucherId) {
            if (confirm('Bạn có chắc chắn muốn xóa voucher này?')) {
                window.location.href = 'voucher_manager.php?action=delete&id=' + voucherId;
            }
        }
    </script>
</head>
<body>
    <div class="admin-container">
        <h1>Quản Lý Voucher</h1>

        <!-- Thông báo lỗi hoặc thành công -->
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success">
                <p><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>

        <!-- Form tạo hoặc chỉnh sửa voucher -->
        <div class="form-section">
            <h2><?php echo $editVoucher ? 'Chỉnh Sửa Voucher' : 'Tạo Voucher Mới'; ?></h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?php echo $editVoucher ? 'update' : 'create'; ?>">
                <?php if ($editVoucher): ?>
                    <input type="hidden" name="id" value="<?php echo $editVoucher['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="code">Mã Voucher</label>
                    <input type="text" id="code" name="code" value="<?php echo $editVoucher ? htmlspecialchars($editVoucher['code']) : (isset($code) ? htmlspecialchars($code) : ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="discount_percent">Giảm Giá Phần Trăm (%)</label>
                    <input type="number" id="discount_percent" name="discount_percent" step="0.01" min="0" max="100" value="<?php echo $editVoucher ? htmlspecialchars($editVoucher['discount_percent']) : (isset($discountPercent) ? htmlspecialchars($discountPercent) : ''); ?>">
                </div>

                <div class="form-group">
                    <label for="max_discount_value">Giá Trị Giảm Giá Tối Đa (VND)</label>
                    <input type="number" id="max_discount_value" name="max_discount_value" step="0.01" min="0" value="<?php echo $editVoucher ? htmlspecialchars($editVoucher['max_discount_value']) : (isset($maxDiscountValue) ? htmlspecialchars($maxDiscountValue) : ''); ?>">
                </div>

                <div class="form-group">
                    <label for="fixed_discount">Giảm Giá Cố Định (VND)</label>
                    <input type="number" id="fixed_discount" name="fixed_discount" step="0.01" min="0" value="<?php echo $editVoucher ? htmlspecialchars($editVoucher['fixed_discount']) : (isset($fixedDiscount) ? htmlspecialchars($fixedDiscount) : ''); ?>">
                </div>

                <div class="form-group">
                    <label for="min_order_value">Giá Trị Đơn Hàng Tối Thiểu (VND)</label>
                    <input type="number" id="min_order_value" name="min_order_value" step="0.01" min="0" value="<?php echo $editVoucher ? htmlspecialchars($editVoucher['min_order_value']) : (isset($minOrderValue) ? htmlspecialchars($minOrderValue) : ''); ?>">
                </div>

                <div class="form-group">
                    <label for="expiry_date">Ngày Đến Hạn (Bỏ trống nếu không giới hạn thời gian)</label>
                    <input type="datetime-local" id="expiry_date" name="expiry_date" value="<?php echo $editVoucher && $editVoucher['expiry_date'] ? date('Y-m-d\TH:i', strtotime($editVoucher['expiry_date'])) : (isset($expiryDate) ? htmlspecialchars($expiryDate) : ''); ?>">
                </div>

                <div class="form-group">
                    <label for="quantity">Số Lượng Còn Lại (Bỏ trống nếu không giới hạn số lượng)</label>
                    <input type="number" id="quantity" name="quantity" min="0" value="<?php echo $editVoucher && $editVoucher['quantity'] !== null ? htmlspecialchars($editVoucher['quantity']) : (isset($quantity) ? htmlspecialchars($quantity) : ''); ?>">
                </div>

                <div class="form-group">
                    <label for="is_public">Công khai</label>
                    <input type="checkbox" id="is_public" name="is_public" <?php echo ($editVoucher && $editVoucher['is_public']) || (!isset($editVoucher) && !isset($_POST['is_public'])) ? 'checked' : ''; ?>>
                </div>

                <div class="form-group">
                    <button type="submit"><?php echo $editVoucher ? 'Cập Nhật Voucher' : 'Tạo Voucher'; ?></button>
                </div>
            </form>
        </div>

        <!-- Danh sách voucher -->
        <div class="list-section">
            <h2>Danh Sách Voucher</h2>
            <?php if (empty($vouchers)): ?>
                <p class="no-vouchers">Không có voucher nào!</p>
            <?php else: ?>
                <table class="voucher-list">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mã Voucher</th>
                            <th>Giảm Giá Phần Trăm</th>
                            <th>Giảm Giá Tối Đa</th>
                            <th>Giảm Giá Cố Định</th>
                            <th>Đơn Hàng Tối Thiểu</th>
                            <th>Ngày Đến Hạn</th>
                            <th>Số Lượng Còn Lại</th>
                            <th>Công Khai</th>
                            <th>Hành Động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vouchers as $voucher): ?>
                            <tr>
                                <td><?php echo $voucher['id']; ?></td>
                                <td><?php echo htmlspecialchars($voucher['code']); ?></td>
                                <td><?php echo $voucher['discount_percent'] ? number_format($voucher['discount_percent'], 2) . '%' : '-'; ?></td>
                                <td><?php echo $voucher['max_discount_value'] ? number_format($voucher['max_discount_value'], 0, ',', '.') . ' VND' : '-'; ?></td>
                                <td><?php echo $voucher['fixed_discount'] ? number_format($voucher['fixed_discount'], 0, ',', '.') . ' VND' : '-'; ?></td>
                                <td><?php echo $voucher['min_order_value'] ? number_format($voucher['min_order_value'], 0, ',', '.') . ' VND' : '-'; ?></td>
                                <td><?php echo $voucher['expiry_date'] ? date('d/m/Y H:i', strtotime($voucher['expiry_date'])) : 'Không giới hạn'; ?></td>
                                <td><?php echo $voucher['quantity'] !== null ? $voucher['quantity'] : 'Không giới hạn'; ?></td>
                                <td><?php echo $voucher['is_public'] ? 'Có' : 'Không'; ?></td>
                                <td class="action">
                                    <a href="voucher_manager.php?action=edit&id=<?php echo $voucher['id']; ?>" class="edit">Sửa</a>
                                    <a href="#" onclick="confirmDelete(<?php echo $voucher['id']; ?>)" class="delete">Xóa</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
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