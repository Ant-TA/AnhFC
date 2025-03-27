<?php
session_start();
include 'dbconnection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: admin_login.php"); // Thay user_login.php
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
    header("Location: ../index.php");
    exit;
}

$menuId = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM menu WHERE id = ?");
$stmt->bind_param("i", $menuId);
$stmt->execute();
$result = $stmt->get_result();
$menu = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $combo_name = $_POST['combo_name'];
    $description = $_POST['description'];
    $price = $_POST['price'];

    $image = $menu['image'];
    if (!empty($_FILES['image']['name'])) {
        $image = $_FILES['image']['name'];
        $target = "../images/" . basename($image);
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
    }

    $stmt = $conn->prepare("UPDATE menu SET combo_name = ?, description = ?, price = ?, image = ? WHERE id = ?");
    $stmt->bind_param("ssdsi", $combo_name, $description, $price, $image, $menuId);
    if ($stmt->execute()) {
        header("Location: admin_menu.php");
        exit;
    } else {
        echo "<p>Lỗi khi cập nhật!</p>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh Sửa Món Ăn</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Giữ nguyên CSS từ trước */
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>
    <div class="edit-form">
        <h1>Chỉnh Sửa Món Ăn</h1>
        <form method="POST" enctype="multipart/form-data">
            <label for="combo_name">Tên Combo:</label>
            <input type="text" id="combo_name" name="combo_name" value="<?php echo htmlspecialchars($menu['combo_name']); ?>" required>

            <label for="description">Mô Tả:</label>
            <textarea id="description" name="description" required><?php echo htmlspecialchars($menu['description']); ?></textarea>

            <label for="price">Giá (VND):</label>
            <input type="number" id="price" name="price" value="<?php echo $menu['price']; ?>" required>

            <label for="image">Hình Ảnh:</label>
            <input type="file" id="image" name="image">
            <p>Hình ảnh hiện tại: <img src="../images/<?php echo htmlspecialchars($menu['image']); ?>" alt="<?php echo htmlspecialchars($menu['combo_name']); ?>" style="max-width: 100px;"></p>

            <button type="submit">Cập Nhật</button>
        </form>
    </div>
</body>
</html>
<?php $conn->close(); ?>