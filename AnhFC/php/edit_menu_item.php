<?php
include 'check_admin.php';

// Kiểm tra nếu không có id món ăn
if (!isset($_GET['id'])) {
    header("Location: admin_menu.php?error=missing_id");
    exit;
}

$menuId = (int)$_GET['id'];

// Lấy thông tin món ăn từ bảng menu
$stmt = $conn->prepare("SELECT * FROM menu WHERE id = ?");
$stmt->bind_param("i", $menuId);
$stmt->execute();
$result = $stmt->get_result();
$menu = $result->fetch_assoc();
$stmt->close();

if (!$menu) {
    header("Location: admin_menu.php?error=not_found");
    exit;
}

// Xử lý cập nhật món ăn
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $combo_name = $conn->real_escape_string($_POST['combo_name']);
    $description = $conn->real_escape_string($_POST['description']);
    $price = (float)$_POST['price']; // Sửa lỗi: loại bỏ dấu ) dư thừa

    // Kiểm tra dữ liệu đầu vào
    if (empty(trim($combo_name))) {
        $error = "Tên món ăn không được để trống!";
    } elseif ($price <= 0) {
        $error = "Giá phải lớn hơn 0!";
    } else {
        // Xử lý upload hình ảnh (nếu có)
        $image = $menu['image']; // Giữ hình ảnh cũ nếu không upload hình mới
        if (!empty($_FILES['image']['name'])) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if (in_array($_FILES['image']['type'], $allowedTypes) && $_FILES['image']['size'] <= $maxSize) {
                $imageName = time() . '_' . basename($_FILES['image']['name']);
                $target = "../images/" . $imageName;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                    // Xóa hình ảnh cũ nếu tồn tại
                    if ($menu['image'] && file_exists("../images/" . $menu['image'])) {
                        unlink("../images/" . $menu['image']);
                    }
                    $image = $imageName;
                } else {
                    $error = "Không thể upload hình ảnh!";
                }
            } else {
                $error = "Hình ảnh không hợp lệ! Chỉ chấp nhận JPEG, PNG, GIF và kích thước tối đa 5MB.";
            }
        }

        if (empty($error)) {
            // Cập nhật thông tin món ăn
            $stmt = $conn->prepare("UPDATE menu SET combo_name = ?, description = ?, price = ?, image = ? WHERE id = ?");
            $stmt->bind_param("ssdsi", $combo_name, $description, $price, $image, $menuId);
            if ($stmt->execute()) {
                $success = "Cập nhật món ăn thành công!";
                // Cập nhật lại thông tin món ăn để hiển thị
                $stmt->close();
                $stmt = $conn->prepare("SELECT * FROM menu WHERE id = ?");
                $stmt->bind_param("i", $menuId);
                $stmt->execute();
                $result = $stmt->get_result();
                $menu = $result->fetch_assoc();
            } else {
                $error = "Có lỗi xảy ra khi cập nhật món ăn!";
            }
            $stmt->close();
        }
    }
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
    <title>Chỉnh Sửa Món Ăn</title>
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

        .edit-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
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
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
            font-family: 'Lexend', sans-serif;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
            overflow: hidden;
        }

        .form-group input[type="number"] {
            -webkit-appearance: none;
            -moz-appearance: textfield;
            appearance: none;
        }

        .form-group input[type="number"]::-webkit-inner-spin-button,
        .form-group input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .form-group img {
            max-width: 100px;
            margin-top: 10px;
            border-radius: 5px;
        }

        .error {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }

        .success {
            color: green;
            text-align: center;
            margin-bottom: 15px;
        }

        button {
            display: block;
            width: 100%;
            padding: 10px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-family: 'Lexend', sans-serif;
        }

        button:hover {
            background-color: #555;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 15px;
        }

        .back-link a {
            color: #333;
            text-decoration: none;
        }

        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
    <script>
        // Tự động điều chỉnh chiều cao textarea khi nhập
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.querySelector('.form-group textarea');
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });
    </script>
</head>
<body>
    <div class="edit-container">
        <h1>Chỉnh Sửa Món Ăn</h1>
        <?php if (!empty($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <p class="success"><?php echo $success; ?></p>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="combo_name">Tên Combo:</label>
                <input type="text" name="combo_name" id="combo_name" value="<?php echo htmlspecialchars($menu['combo_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Mô Tả:</label>
                <textarea name="description" id="description"><?php echo htmlspecialchars($menu['description']); ?></textarea>
            </div>
            <div class="form-group">
                <label for="price">Giá (VND):</label>
                <input type="number" name="price" id="price" step="0.01" value="<?php echo htmlspecialchars($menu['price']); ?>" required>
            </div>
            <div class="form-group">
                <label for="image">Hình Ảnh (để trống nếu không thay đổi):</label>
                <input type="file" name="image" id="image" accept="image/*">
                <p>Hình ảnh hiện tại: <img src="../images/<?php echo htmlspecialchars($menu['image']); ?>" alt="<?php echo htmlspecialchars($menu['combo_name']); ?>"></p>
            </div>
            <button type="submit">Cập Nhật</button>
        </form>
        <div class="back-link">
            <a href="admin_menu.php">Quay lại danh sách món ăn</a>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
include 'footer.php';
?>