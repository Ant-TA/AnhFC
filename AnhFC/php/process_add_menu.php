<?php
include 'dbconnection.php'; // Gọi file kết nối cơ sở dữ liệu

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $combo_name = $_POST['combo_name'];
    $description = $_POST['description'];
    $price = $_POST['price'];

    // Xử lý upload hình ảnh
    $target_dir = "../images/";
    $target_file = $target_dir . basename($_FILES["image"]["name"]);
    $upload_ok = 1;

    // Kiểm tra định dạng file
    $image_file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    if ($image_file_type != "jpg" && $image_file_type != "png" && $image_file_type != "jpeg") {
        echo "Chỉ cho phép các định dạng JPG, PNG, JPEG.";
        $upload_ok = 0;
    }

    // Kiểm tra dung lượng tệp (giới hạn 2MB)
    if ($_FILES["image"]["size"] > 2000000) {
        echo "File hình ảnh quá lớn, giới hạn dưới 2MB.";
        $upload_ok = 0;
    }

    // Di chuyển file nếu hợp lệ
    if ($upload_ok && move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
        // Lưu thông tin vào database
        $sql = "INSERT INTO menu (combo_name, description, price, image)
                VALUES ('$combo_name', '$description', '$price', '$target_file')";

        if ($conn->query($sql) === TRUE) {
            echo "Thêm món ăn thành công!";
            header("Location: menu.php");
            exit();
        } else {
            echo "Lỗi khi thêm món ăn: " . $conn->error;
        }
    } else {
        echo "Không thể tải file lên.";
    }

    $conn->close();
}
?>