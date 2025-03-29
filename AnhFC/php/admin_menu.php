<?php
session_start();
include_once 'dbconnection.php';

// Kiểm tra đăng nhập và quyền admin
if (!isset($_SESSION['user_id'])) {
    header("Location: admin_login.php");
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
    session_unset();
    session_destroy();
    header("Location: admin_login.php?error=1");
    exit;
}

// Xử lý thêm danh mục mới
if (isset($_POST['add_category'])) {
    $categoryName = $conn->real_escape_string($_POST['category_name']);
    if (!empty($categoryName)) {
        $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->bind_param("s", $categoryName);
        if ($stmt->execute()) {
            $successMessage = "Thêm danh mục thành công!";
        } else {
            $errorMessage = "Lỗi: Không thể thêm danh mục. Tên danh mục có thể đã tồn tại.";
        }
        $stmt->close();
    } else {
        $errorMessage = "Vui lòng nhập tên danh mục!";
    }
}

// Xử lý xóa danh mục
if (isset($_GET['delete_category'])) {
    $categoryId = intval($_GET['delete_category']);
    
    // Kiểm tra xem danh mục có đang được sử dụng trong menu_categories không
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM menu_categories WHERE category_id = ?");
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row['count'] > 0) {
        $errorMessage = "Không thể xóa danh mục này vì có món ăn đang sử dụng nó!";
    } else {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $categoryId);
        if ($stmt->execute()) {
            $successMessage = "Xóa danh mục thành công!";
        } else {
            $errorMessage = "Lỗi: Không thể xóa danh mục.";
        }
        $stmt->close();
    }
}

// Xử lý thêm món mới
if (isset($_POST['add_item'])) {
    $comboName = $conn->real_escape_string($_POST['combo_name']);
    $description = $conn->real_escape_string($_POST['description']);
    $price = floatval($_POST['price']);
    $categoriesSelected = isset($_POST['categories']) ? $_POST['categories'] : [];
    $image = '';

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $targetDir = "../assets/";
        $image = basename($_FILES['image']['name']);
        $targetFile = $targetDir . $image;
        move_uploaded_file($_FILES['image']['tmp_name'], $targetFile);
    }

    // Thêm món vào bảng menu
    $stmt = $conn->prepare("INSERT INTO menu (combo_name, description, price, image) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssds", $comboName, $description, $price, $image);
    if ($stmt->execute()) {
        $menuId = $stmt->insert_id;
        $stmt->close();

        // Thêm danh mục vào bảng menu_categories
        if (!empty($categoriesSelected)) {
            $stmt = $conn->prepare("INSERT INTO menu_categories (menu_id, category_id) VALUES (?, ?)");
            foreach ($categoriesSelected as $categoryId) {
                $categoryId = intval($categoryId);
                $stmt->bind_param("ii", $menuId, $categoryId);
                $stmt->execute();
            }
            $stmt->close();
        }
        $successMessage = "Thêm món thành công!";
    } else {
        $errorMessage = "Lỗi: Không thể thêm món.";
        $stmt->close();
    }
}

// Xử lý cập nhật món
if (isset($_POST['update_item'])) {
    $itemId = intval($_POST['item_id']);
    $comboName = $conn->real_escape_string($_POST['combo_name']);
    $description = $conn->real_escape_string($_POST['description']);
    $price = floatval($_POST['price']);
    $categoriesSelected = isset($_POST['categories']) ? $_POST['categories'] : [];
    $image = $_POST['existing_image'];

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $targetDir = "../assets/";
        $image = basename($_FILES['image']['name']);
        $targetFile = $targetDir . $image;
        move_uploaded_file($_FILES['image']['tmp_name'], $targetFile);
    }

    // Cập nhật thông tin món
    $stmt = $conn->prepare("UPDATE menu SET combo_name = ?, description = ?, price = ?, image = ? WHERE id = ?");
    $stmt->bind_param("ssdsi", $comboName, $description, $price, $image, $itemId);
    if ($stmt->execute()) {
        $stmt->close();

        // Xóa các danh mục cũ của món
        $stmt = $conn->prepare("DELETE FROM menu_categories WHERE menu_id = ?");
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        $stmt->close();

        // Thêm lại các danh mục mới
        if (!empty($categoriesSelected)) {
            $stmt = $conn->prepare("INSERT INTO menu_categories (menu_id, category_id) VALUES (?, ?)");
            foreach ($categoriesSelected as $categoryId) {
                $categoryId = intval($categoryId);
                $stmt->bind_param("ii", $itemId, $categoryId);
                $stmt->execute();
            }
            $stmt->close();
        }
        $successMessage = "Cập nhật món thành công!";
    } else {
        $errorMessage = "Lỗi: Không thể cập nhật món.";
        $stmt->close();
    }
}

// Xử lý xóa món
if (isset($_GET['delete'])) {
    $itemId = intval($_GET['delete']);
    // Các bản ghi trong menu_categories sẽ tự động bị xóa do ON DELETE CASCADE
    $stmt = $conn->prepare("DELETE FROM menu WHERE id = ?");
    $stmt->bind_param("i", $itemId);
    if ($stmt->execute()) {
        $successMessage = "Xóa món thành công!";
    } else {
        $errorMessage = "Lỗi: Không thể xóa món.";
    }
    $stmt->close();
}

// Lấy danh sách danh mục
$categories = [];
$stmt = $conn->prepare("SELECT * FROM categories ORDER BY name ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($category = $result->fetch_assoc()) {
    $categories[] = $category;
}
$stmt->close();

// Lấy danh sách món ăn và danh mục của chúng
$menuItems = [];
$stmt = $conn->prepare("SELECT m.* FROM menu m ORDER BY m.id DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($item = $result->fetch_assoc()) {
    // Lấy danh mục của món
    $stmt_cat = $conn->prepare("SELECT c.id, c.name FROM categories c JOIN menu_categories mc ON c.id = mc.category_id WHERE mc.menu_id = ?");
    $stmt_cat->bind_param("i", $item['id']);
    $stmt_cat->execute();
    $cat_result = $stmt_cat->get_result();
    $item['categories'] = [];
    while ($cat = $cat_result->fetch_assoc()) {
        $item['categories'][] = $cat;
    }
    $stmt_cat->close();
    $menuItems[] = $item;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Thực Đơn</title>
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

        .menu-manager-container {
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .category-section, .add-item-section {
            margin-bottom: 30px;
        }

        .category-section form, .add-item-section form {
            display: flex;
            gap: 10px;
            justify-content: center;
            align-items: center;
        }

        .category-section input, .add-item-section input, .add-item-section textarea, .add-item-section select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }

        .category-section button, .add-item-section button {
            padding: 8px 20px;
            background-color: #2ecc71;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .category-section button:hover, .add-item-section button:hover {
            background-color: #27ae60;
        }

        .category-table, .menu-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .category-table th, .category-table td, .menu-table th, .menu-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .category-table th, .menu-table th {
            background-color: #f5f5f5;
        }

        .menu-table img {
            width: 100px;
            height: auto;
            border-radius: 5px;
        }

        .menu-table .edit-btn, .menu-table .delete-btn, .category-table .delete-btn {
            color: #333;
            text-decoration: none;
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-right: 5px;
        }

        .menu-table .edit-btn:hover, .menu-table .delete-btn:hover, .category-table .delete-btn:hover {
            background-color: #f0f0f0;
        }

        .menu-table .delete-btn, .category-table .delete-btn {
            color: red;
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

        .edit-popup, .add-popup {
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

        .edit-popup-content, .add-popup-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            width: 400px;
            max-width: 90%;
            text-align: center;
        }

        .edit-popup-content h3, .add-popup-content h3 {
            margin-top: 0;
            color: #333;
        }

        .edit-popup-content form, .add-popup-content form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .edit-popup-content input, .edit-popup-content textarea, .edit-popup-content select,
        .add-popup-content input, .add-popup-content textarea, .add-popup-content select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .edit-popup-content button, .add-popup-content button {
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .edit-popup-content .btn-save, .add-popup-content .btn-save {
            background-color: #2ecc71;
            color: white;
        }

        .edit-popup-content .btn-save:hover, .add-popup-content .btn-save:hover {
            background-color: #27ae60;
        }

        .edit-popup-content .btn-cancel, .add-popup-content .btn-cancel {
            background-color: #ccc;
            color: #333;
        }

        .edit-popup-content .btn-cancel:hover, .add-popup-content .btn-cancel:hover {
            background-color: #bbb;
        }

        .category-checkboxes {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }

        .category-checkboxes label {
            display: flex;
            align-items: center;
            gap: 5px;
        }
    </style>
</head>
<body>
    <div class="menu-manager-container">
        <h1>Quản Lý Thực Đơn</h1>

        <?php if (isset($errorMessage)): ?>
            <p class="error"><?php echo htmlspecialchars($errorMessage); ?></p>
        <?php endif; ?>
        <?php if (isset($successMessage)): ?>
            <p class="success"><?php echo htmlspecialchars($successMessage); ?></p>
        <?php endif; ?>

        <!-- Quản lý danh mục -->
        <div class="category-section">
            <h2>Quản Lý Danh Mục</h2>
            <form method="POST" action="">
                <input type="text" name="category_name" placeholder="Tên danh mục" required>
                <button type="submit" name="add_category">Thêm Danh Mục</button>
            </form>
            <table class="category-table">
                <tr>
                    <th>ID</th>
                    <th>Tên Danh Mục</th>
                    <th>Hành Động</th>
                </tr>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?php echo $category['id']; ?></td>
                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                        <td>
                            <a href="admin_menu_manager.php?delete_category=<?php echo $category['id']; ?>" class="delete-btn" onclick="return confirm('Bạn có chắc chắn muốn xóa danh mục này?')">Xóa</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- Thêm món mới -->
        <div class="add-item-section">
            <h2>Thêm Món Mới</h2>
            <button onclick="showAddPopup()">Thêm Món</button>
        </div>

        <!-- Danh sách món -->
        <table class="menu-table">
            <tr>
                <th>ID</th>
                <th>Tên Combo</th>
                <th>Danh Mục</th>
                <th>Mô Tả</th>
                <th>Giá</th>
                <th>Hình Ảnh</th>
                <th>Hành Động</th>
            </tr>
            <?php foreach ($menuItems as $item): ?>
                <tr>
                    <td><?php echo $item['id']; ?></td>
                    <td><?php echo htmlspecialchars($item['combo_name']); ?></td>
                    <td>
                        <?php
                        if (!empty($item['categories'])) {
                            $categoryNames = array_map(function($cat) { return htmlspecialchars($cat['name']); }, $item['categories']);
                            echo implode(", ", $categoryNames);
                        } else {
                            echo "Không có danh mục";
                        }
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                    <td><?php echo number_format($item['price'], 0, ',', '.'); ?> VND</td>
                    <td><img src="../assets/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['combo_name']); ?>"></td>
                    <td>
                        <a href="javascript:void(0)" class="edit-btn" onclick='showEditPopup(<?php echo json_encode($item); ?>)'>Sửa</a>
                        <a href="admin_menu_manager.php?delete=<?php echo $item['id']; ?>" class="delete-btn" onclick="return confirm('Bạn có chắc chắn muốn xóa món này?')">Xóa</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Popup thêm món -->
    <div class="add-popup" id="add-popup">
        <div class="add-popup-content">
            <h3>Thêm Món Mới</h3>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="text" name="combo_name" placeholder="Tên combo" required>
                <textarea name="description" placeholder="Mô tả" required></textarea>
                <input type="number" name="price" placeholder="Giá" step="0.01" required>
                <div class="category-checkboxes">
                    <?php foreach ($categories as $category): ?>
                        <label>
                            <input type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <input type="file" name="image" accept="image/*" required>
                <button type="submit" name="add_item" class="btn-save">Thêm</button>
                <button type="button" class="btn-cancel" onclick="hideAddPopup()">Hủy</button>
            </form>
        </div>
    </div>

    <!-- Popup sửa món -->
    <div class="edit-popup" id="edit-popup">
        <div class="edit-popup-content">
            <h3>Sửa Món</h3>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="item_id" id="edit-item-id">
                <input type="text" name="combo_name" id="edit-combo-name" required>
                <textarea name="description" id="edit-description" required></textarea>
                <input type="number" name="price" id="edit-price" step="0.01" required>
                <div class="category-checkboxes">
                    <?php foreach ($categories as $category): ?>
                        <label>
                            <input type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" id="edit-category-<?php echo $category['id']; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="existing_image" id="edit-existing-image">
                <input type="file" name="image" accept="image/*">
                <button type="submit" name="update_item" class="btn-save">Lưu</button>
                <button type="button" class="btn-cancel" onclick="hideEditPopup()">Hủy</button>
            </form>
        </div>
    </div>

    <script>
        function showAddPopup() {
            document.getElementById('add-popup').style.display = 'flex';
        }

        function hideAddPopup() {
            document.getElementById('add-popup').style.display = 'none';
        }

        function showEditPopup(item) {
            document.getElementById('edit-item-id').value = item.id;
            document.getElementById('edit-combo-name').value = item.combo_name;
            document.getElementById('edit-description').value = item.description;
            document.getElementById('edit-price').value = item.price;
            document.getElementById('edit-existing-image').value = item.image;

            // Đánh dấu các checkbox danh mục
            document.querySelectorAll('input[name="categories[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            item.categories.forEach(category => {
                const checkbox = document.getElementById('edit-category-' + category.id);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });

            document.getElementById('edit-popup').style.display = 'flex';
        }

        function hideEditPopup() {
            document.getElementById('edit-popup').style.display = 'none';
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>