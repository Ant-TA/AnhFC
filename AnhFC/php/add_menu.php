<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Món Ăn</title>
    <link rel="stylesheet" href="css/style.css"> <!-- Gắn CSS nếu cần -->
</head>
<body>
    <h1>Thêm Món Ăn</h1>
    <form action="process_add_menu.php" method="POST" enctype="multipart/form-data">
        <label for="combo_name">Tên món ăn:</label>
        <input type="text" name="combo_name" id="combo_name" required><br>

        <label for="description">Mô tả:</label>
        <textarea name="description" id="description"></textarea><br>

        <label for="price">Giá (VND):</label>
        <input type="number" name="price" id="price" step="0.01" required><br>

        <label for="image">Hình ảnh:</label>
        <input type="file" name="image" id="image" required><br>

        <button type="submit">Thêm Món Ăn</button>
    </form>
</body>
</html>