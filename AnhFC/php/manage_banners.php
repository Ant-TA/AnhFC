<?php
include 'check_admin.php';

// Xử lý thêm banner
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_banner'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

    // Kiểm tra dữ liệu đầu vào
    if (empty(trim($title))) {
        $error = "Tiêu đề không được để trống!";
    } elseif (empty($_FILES['background']['name'])) {
        $error = "Vui lòng chọn hình ảnh nền!";
    } else {
        // Xử lý upload hình ảnh
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        $background = '';

        if (in_array($_FILES['background']['type'], $allowedTypes) && $_FILES['background']['size'] <= $maxSize) {
            $backgroundName = time() . '_' . basename($_FILES['background']['name']);
            $target = "../images/" . $backgroundName;

            if (move_uploaded_file($_FILES['background']['tmp_name'], $target)) {
                $background = $backgroundName;
            } else {
                $error = "Không thể upload hình ảnh!";
            }
        } else {
            $error = "Hình ảnh không hợp lệ! Chỉ chấp nhận JPEG, PNG, GIF và kích thước tối đa 5MB.";
        }

        if (empty($error)) {
            // Thêm banner vào bảng banners
            $stmt = $conn->prepare("INSERT INTO banners (background, title, description, expiry_date, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssss", $background, $title, $description, $expiry_date);
            if ($stmt->execute()) {
                $success = "Thêm banner thành công!";
            } else {
                $error = "Có lỗi xảy ra khi thêm banner!";
            }
            $stmt->close();
        }
    }
}

// Xử lý xóa banner
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];

    // Lấy thông tin banner để xóa hình ảnh
    $stmt = $conn->prepare("SELECT background FROM banners WHERE id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $result = $stmt->get_result();
    $banner = $result->fetch_assoc();
    $stmt->close();

    if ($banner) {
        // Xóa hình ảnh nếu tồn tại
        if ($banner['background'] && file_exists("../images/" . $banner['background'])) {
            unlink("../images/" . $banner['background']);
        }

        // Xóa banner khỏi bảng banners
        $stmt = $conn->prepare("DELETE FROM banners WHERE id = ?");
        $stmt->bind_param("i", $deleteId);
        if ($stmt->execute()) {
            header("Location: manage_banners.php?success=deleted");
            exit;
        } else {
            header("Location: manage_banners.php?error=delete_failed");
            exit;
        }
        $stmt->close();
    }
}

// Lấy danh sách banner từ bảng banners
$result = $conn->query("SELECT * FROM banners");
$banners = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
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
    <title>Quản Lý Banner Quảng Cáo</title>
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
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h1, h2 {
            font-family: 'Lexend', sans-serif;
            font-weight: 600;
            text-align: center;
            color: #333;
        }

        h2 {
            margin-top: 30px;
            margin-bottom: 20px;
        }

        .add-form {
            margin-bottom: 30px;
        }

        .add-form label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
            color: #333;
        }

        .add-form input,
        .add-form textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
            font-family: 'Lexend', sans-serif;
        }

        .add-form textarea {
            min-height: 100px;
            resize: vertical;
            overflow: hidden;
        }

        .add-form input[type="datetime-local"] {
            padding: 8px;
        }

        .add-form button {
            width: 100%;
            padding: 10px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Lexend', sans-serif;
        }

        .add-form button:hover {
            background-color: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
            font-family: 'Lexend', sans-serif;
        }

        th {
            background-color: #333;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        td img {
            max-width: 100px;
            border-radius: 5px;
        }

        .action-btn {
            padding: 5px 10px;
            margin: 0 5px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-family: 'Lexend', sans-serif;
        }

        .action-btn:hover {
            background-color: #555;
        }

        .delete-btn {
            background-color: #d9534f;
        }

        .delete-btn:hover {
            background-color: #c9302c;
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

        .empty-message {
            text-align: center;
            color: #666;
            font-size: 1.2rem;
            margin-top: 20px;
        }
    </style>
    <script>
        // Tự động điều chỉnh chiều cao textarea khi nhập
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.querySelector('.add-form textarea');
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });
    </script>
</head>
<body>
    <div class="admin-container">
        <h1>Quản Lý Banner Quảng Cáo</h1>

        <!-- Thông báo lỗi/thành công -->
        <?php if (!empty($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <p class="success"><?php echo $success; ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['error']) && $_GET['error'] == 'delete_failed'): ?>
            <p class="error">Có lỗi xảy ra khi xóa banner!</p>
        <?php endif; ?>
        <?php if (isset($_GET['success']) && $_GET['success'] == 'deleted'): ?>
            <p class="success">Xóa banner thành công!</p>
        <?php endif; ?>

        <!-- Form thêm banner -->
        <div class="add-form">
            <h2>Thêm Banner</h2>
            <form action="" method="POST" enctype="multipart/form-data">
                <label for="title">Tiêu Đề:</label>
                <input type="text" name="title" id="title" required>

                <label for="description">Mô Tả:</label>
                <textarea name="description" id="description"></textarea>

                <label for="background">Hình Ảnh Nền:</label>
                <input type="file" name="background" id="background" accept="image/*" required>

                <label for="expiry_date">Ngày Hết Hạn (Để trống nếu không có):</label>
                <input type="datetime-local" name="expiry_date" id="expiry_date">

                <button type="submit" name="add_banner">Thêm Banner</button>
            </form>
        </div>

        <!-- Danh sách banner -->
        <h2>Danh Sách Banner</h2>
        <?php if (empty($banners)): ?>
            <p class="empty-message">Hiện tại chưa có banner nào!</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Tiêu Đề</th>
                    <th>Mô Tả</th>
                    <th>Hình Ảnh Nền</th>
                    <th>Ngày Hết Hạn</th>
                    <th>Hành Động</th>
                </tr>
                <?php foreach ($banners as $banner): ?>
                    <tr>
                        <td><?php echo $banner['id']; ?></td>
                        <td><?php echo htmlspecialchars($banner['title']); ?></td>
                        <td><?php echo htmlspecialchars($banner['description']); ?></td>
                        <td><img src="../images/<?php echo htmlspecialchars($banner['background']); ?>" alt="<?php echo htmlspecialchars($banner['title']); ?>"></td>
                        <td><?php echo $banner['expiry_date'] ? date('d/m/Y H:i', strtotime($banner['expiry_date'])) : 'Không có'; ?></td>
                        <td>
                            <a href="edit_banner.php?id=<?php echo $banner['id']; ?>" class="action-btn">Sửa</a>
                            <a href="manage_banners.php?delete_id=<?php echo $banner['id']; ?>" class="action-btn delete-btn" onclick="return confirm('Bạn có chắc muốn xóa banner này?');">Xóa</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
    <?php
    $conn->close();
    include 'footer.php';
    ?>
</body>
</html>