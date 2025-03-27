<?php
include 'header.php'; // Thêm header từ file riêng
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách món ăn</title>
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

        .search-wrapper {
            position: relative;
            display: inline-block;
        }

        #searchInput {
            width: 300px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .clear-button {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            padding: 0;
            font-size: 16px;
            color: transparent;
            cursor: pointer;
            outline: none;
            visibility: hidden;
        }

        .clear-button:hover {
            color: #000;
        }

        .search-wrapper input:not(:placeholder-shown) ~ .clear-button {
            visibility: visible;
            color: #aaa;
        }

        .menu-item {
            text-align: center;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            background-color: #fff;
        }

        .menu-item img {
            max-width: 200px;
            border-radius: 5px;
        }

        .menu-item button {
            margin: 10px 5px;
            padding: 10px 20px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .menu-item button:hover {
            background-color: #555;
        }

        .rating-stars {
            display: flex; /* Hiển thị dãy sao theo hàng ngang */
            justify-content: center; /* Căn giữa nội dung */
            align-items: center; /* Căn giữa theo trục dọc */
            font-size: 1.2em; /* Kích thước sao */
            margin: 10px 0; /* Khoảng cách trên/dưới */
        }

        .rating-stars span {
            margin-right: 5px; /* Khoảng cách giữa các sao */
        }
    </style>
    <script>
        function clearSearchInput() {
            const searchInput = document.getElementById('searchInput');
            searchInput.value = '';
            document.getElementById('searchForm').submit();
        }
    </script>
</head>
<body>
    <!-- Banner -->
    <div class="banner">
        <h1>Chào mừng đến với AFC</h1>
        <p>Một ngày tuyệt vời để ăn gà!</p>
    </div>

    <!-- Thanh tìm kiếm và lọc -->
    <div class="search-filter">
        <form id="searchForm" method="GET" action="">
            <div class="search-wrapper">
                <input type="text" name="search" id="searchInput" placeholder="Tìm theo tên hoặc mô tả..." value="<?= isset($_GET['search']) ? $_GET['search'] : '' ?>">
                <button type="button" class="clear-button" onclick="clearSearchInput()">×</button>
            </div>
            <select name="sort" id="sortSelect">
                <option value="rating_desc" <?= isset($_GET['sort']) && $_GET['sort'] == 'rating_desc' ? 'selected' : '' ?>>Sao: Giảm dần</option>
                <option value="rating_asc" <?= isset($_GET['sort']) && $_GET['sort'] == 'rating_asc' ? 'selected' : '' ?>>Sao: Tăng dần</option>
                <option value="asc" <?= isset($_GET['sort']) && $_GET['sort'] == 'asc' ? 'selected' : '' ?>>Giá: Thấp đến Cao</option>
                <option value="desc" <?= isset($_GET['sort']) && $_GET['sort'] == 'desc' ? 'selected' : '' ?>>Giá: Cao đến Thấp</option>
            </select>
            <button type="submit">Tìm kiếm</button>
        </form>
    </div>

    <!-- Danh sách món ăn -->
    <div class="menu-list">
        <?php
        include 'dbconnection.php';

        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $sort = isset($_GET['sort']) ? $_GET['sort'] : 'rating_desc'; // Mặc định là sao giảm dần

        // Logic sắp xếp
        $orderBy = "rating DESC"; // Mặc định là sao giảm dần
        if ($sort == 'rating_asc') {
            $orderBy = "rating ASC"; // Sắp xếp sao tăng dần
        } elseif ($sort == 'asc') {
            $orderBy = "price ASC"; // Giá thấp đến cao
        } elseif ($sort == 'desc') {
            $orderBy = "price DESC"; // Giá cao đến thấp
        }

        // Truy vấn dữ liệu từ bảng `menu`
        $sql = "SELECT * FROM menu WHERE combo_name LIKE '%$search%' OR description LIKE '%$search%' ORDER BY $orderBy";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<div class='menu-item'>";
                echo "<h2>" . htmlspecialchars($row['combo_name']) . "</h2>";
                echo "<p>" . htmlspecialchars($row['description']) . "</p>";
                echo "<p>Giá: " . number_format($row['price'], 0, ',', '.') . " VND</p>";
                if (!empty($row['image'])) {
                    echo "<img src='../images/" . htmlspecialchars($row['image']) . "' alt='" . htmlspecialchars($row['combo_name']) . "'>";
                }

                // Hiển thị đánh giá dưới dạng sao
                $rating = $row['rating'];
                $rating_count = $row['rating_count'];
                echo "<div class='rating-stars'>";
                for ($i = 1; $i <= 5; $i++) {
                    if ($rating >= $i) {
                        echo "<span style='color: gold;'>&#9733;</span>";
                    } elseif ($rating >= $i - 0.5) {
                        echo "<span style='color: gold;'>&#9734;</span>";
                    } else {
                        echo "<span style='color: lightgray;'>&#9733;</span>";
                    }
                }
                echo " <span>(" . $rating_count . " đánh giá)</span>";
                echo "</div>";

                echo "<div>";
                echo "<button onclick=\"location.href='details.php?id=" . $row['id'] . "'\">Xem thêm</button>";
                echo "<button onclick=\"addToCart(" . $row['id'] . ")\">Thêm vào giỏ hàng</button>";
                echo "</div>";
                echo "</div>";
            }
        } else {
            echo "<p>Không tìm thấy món ăn nào!</p>";
        }

        $conn->close();
        ?>
    </div>

    <script>
        function addToCart(itemId) {
            alert("Món ăn với ID " + itemId + " đã được thêm vào giỏ hàng!");
        }
    </script>
</body>
</html>

<?php include 'footer.php'; ?>