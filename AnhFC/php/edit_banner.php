<?php
include 'check_admin.php';

// Kiểm tra nếu không có id banner
if (!isset($_GET['id'])) {
    header("Location: manage_banners.php?error=missing_id");
    exit;
}

$bannerId = (int)$_GET['id'];

// Lấy thông tin banner từ bảng banners
$stmt = $conn->prepare("SELECT * FROM banners WHERE id = ?");
$stmt->bind_param("i", $bannerId);
$stmt->execute();
$result = $stmt->get_result();
$banner = $result->fetch_assoc();
$stmt->close();

if (!$banner) {
    header("Location: manage_banners.php?error=not_found");
    exit;
}

// Xử lý cập nhật banner
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

    // Kiểm tra dữ liệu đầu vào
    if (empty(trim($title))) {
        $error = "Tiêu đề không được để trống!";
    } else {
        // Xử lý upload hình ảnh (nếu có)
        $background = $banner['background']; // Giữ hình ảnh cũ nếu không upload hình mới
        if (!empty($_FILES['background']['name'])) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if (in_array($_FILES['background']['type'], $allowedTypes) && $_FILES['background']['size'] <= $maxSize) {
                $backgroundName = time() . '_' . basename($_FILES['background']['name']);
                $target = "../images/" . $backgroundName;

                if (move_uploaded_file($_FILES['background']['tmp_name'], $target)) {
                    // Xóa hình ảnh cũ nếu tồn tại
                    if ($banner['background'] && file_exists("../images/" . $banner['background'])) {
                        unlink("../images/" . $banner['background']);
                    }
                    $background = $backgroundName;
                } else {
                    $error = "Không thể upload hình ảnh!";
                }
            } else {
                $error = "Hình ảnh không hợp lệ! Chỉ chấp nhận JPEG, PNG, GIF và kích thước tối đa 5MB.";
            }
        }

        if (empty($error)) {
            // Cập nhật thông tin banner
            $stmt = $conn->prepare("UPDATE banners SET background = ?, title = ?, description = ?, expiry_date = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $background, $title, $description, $expiry_date, $bannerId);
            if ($stmt->execute()) {
                $success = "Cập nhật banner thành công!";
                // Cập nhật lại thông tin banner để hiển thị
                $stmt->close();
                $stmt = $conn->prepare("SELECT * FROM banners WHERE id = ?");
                $stmt->bind_param("i", $bannerId);
                $stmt->execute();
                $result = $stmt->get_result();
                $banner = $result->fetch_assoc();
            } else {
                $error = "Có lỗi xảy ra khi cập nhật banner!";
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
    <title>Chỉnh Sửa Banner</title>
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

        .form-group input[type="datetime-local"] {
            padding: 8px;
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
        <h1>Chỉnh Sửa Banner</h1>
        <?php if (!empty($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <p class="success"><?php echo $success; ?></p>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Tiêu Đề:</label>
                <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($banner['title']); ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Mô Tả:</label>
                <textarea name="description" id="description"><?php echo htmlspecialchars($banner['description']); ?></textarea>
            </div>
            <div class="form-group">
                <label for="background">Hình Ảnh Nền (để trống nếu không thay đổi):</label>
                <input type="file" name="background" id="background" accept="image/*">
                <p>Hình ảnh hiện tại: <img src="../images/<?php echo htmlspecialchars($banner['background']); ?>" alt="<?php echo htmlspecialchars($banner['title']); ?>"></p>
            </div>
            <div class="form-group">
                <label for="expiry_date">Ngày Hết Hạn (để trống nếu không có):</label>
                <input type="datetime-local" name="expiry_date" id="expiry_date" value="<?php echo $banner['expiry_date'] ? date('Y-m-d\TH:i', strtotime($banner['expiry_date'])) : ''; ?>">
            </div>
            <button type="submit">Cập Nhật</button>
        </form>
        <div class="back-link">
            <a href="manage_banners.php">Quay lại danh sách banner</a>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
include 'footer.php';
?>