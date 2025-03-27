<?php
include 'header.php'; // Bao gồm header chung
include 'dbconnection.php'; // Kết nối cơ sở dữ liệu

// Kiểm tra và lấy ID món ăn từ URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']); // Đảm bảo ID là số nguyên

    // Truy vấn dữ liệu món ăn từ bảng `menu`
    $sql = "SELECT * FROM menu WHERE id = $id";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc(); // Lấy thông tin món ăn
    } else {
        echo "<p style='text-align:center;'>Món ăn không tồn tại!</p>";
        include 'footer.php';
        exit;
    }
} else {
    echo "<p style='text-align:center;'>Không tìm thấy ID món ăn!</p>";
    include 'footer.php';
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiết Món Ăn</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Lexend', sans-serif;
        }

        h1, h2, h3 {
            font-family: 'Lexend', sans-serif;
            font-weight: 600;
        }

        .details-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .details-container img {
            max-width: 100%;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .details-container h1 {
            font-size: 2em;
            color: #333;
            margin-bottom: 10px;
        }

        .details-container p {
            font-size: 1.2em;
            margin: 5px 0;
        }

        .details-container .price {
            font-size: 1.5em;
            color: #f39c12;
            font-weight: bold;
            margin-top: 15px;
        }

        .details-container .rating-stars {
            display: flex;
            align-items: center;
            font-size: 1.2em;
            margin: 10px 0;
        }

        .details-container .rating-stars span {
            margin-right: 2px;
        }

        .details-container button {
            padding: 10px 20px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 20px;
        }

        .details-container button:hover {
            background-color: #555;
        }
    </style>
</head>
<body>
    <div class="details-container">
        <!-- Hiển thị chi tiết món ăn -->
        <img src="../images/<?= htmlspecialchars($row['image']); ?>" alt="<?= htmlspecialchars($row['combo_name']); ?>">
        <h1><?= htmlspecialchars($row['combo_name']); ?></h1>
        <p><?= htmlspecialchars($row['description']); ?></p>
        <p class="price">Giá: <?= number_format($row['price'], 0, ',', '.') ?> VND</p>

        <!-- Hiển thị đánh giá bằng sao -->
        <div class="rating-stars">
            <?php
            $rating = $row['rating'];
            $rating_count = $row['rating_count'];

            // Hiển thị sao
            for ($i = 1; $i <= 5; $i++) {
                if ($rating >= $i) {
                    echo "<span style='color: gold;'>&#9733;</span>"; // Sao vàng
                } elseif ($rating >= $i - 0.5) {
                    echo "<span style='color: gold;'>&#9734;</span>"; // Nửa sao vàng
                } else {
                    echo "<span style='color: lightgray;'>&#9733;</span>"; // Sao xám
                }
            }
            ?>
            <span>(<?= $rating_count ?> đánh giá)</span>
        </div>

        <!-- Nút thêm vào giỏ hàng -->
        <button onclick="addToCart(<?= $row['id']; ?>)">Thêm vào giỏ hàng</button>
    </div>

    <script>
        function addToCart(itemId) {
            alert("Món ăn với ID " + itemId + " đã được thêm vào giỏ hàng!");
            // Xử lý thêm vào giỏ hàng tại backend
        }
    </script>
</body>
</html>

<?php include 'footer.php'; ?>