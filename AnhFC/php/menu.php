<?php
session_start();
include 'dbconnection.php';

// Ngăn cache để tránh tự động đăng nhập lại
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit;
}

// Kiểm tra quyền admin
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user['is_admin'] == 1) {
    session_unset();
    session_destroy();
    header("Location: user_login.php?error=2");
    exit;
}

// Khởi tạo giỏ hàng nếu chưa có
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Xử lý thêm vào giỏ hàng
if (isset($_POST['add_to_cart'])) {
    $itemId = intval($_POST['item_id']);
    if (!isset($_SESSION['cart'][$itemId])) {
        $_SESSION['cart'][$itemId] = 1;
    } else {
        $_SESSION['cart'][$itemId]++;
    }
    header("Location: menu.php?added=1" . (isset($_GET['search']) ? "&search=" . urlencode($_GET['search']) : "") . (isset($_GET['sort']) ? "&sort=" . $_GET['sort'] : ""));
    exit;
}
?>

<?php include 'user_header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Danh sách món ăn</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Lexend', sans-serif;
            background-image: url('../assets/BG.jpg');
            background-size: cover;
            background-attachment: fixed;
        }

        h1, h2, h3 {
            font-family: 'Lexend', sans-serif;
            font-weight: 600;
        }

        .banner {
            background-color: #f5c518; /* Màu vàng giống trong ảnh */
            text-align: center;
            padding: 2rem;
            color: white;
        }

        .banner h1 {
            margin: 0;
            font-size: 2.5rem;
        }

        .banner p {
            margin: 0.5rem 0 0;
            font-size: 1.2rem;
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

        .menu-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, 18rem); /* Chiều rộng cố định 18rem, không giãn */
            gap: 1.5rem; /* Khoảng cách giữa các món */
            padding: 2rem;
            max-width: 100vw;
            margin: 0 auto;
            justify-content: center; /* Căn giữa các thẻ nếu ít hơn 5 */
        }

        .menu-item {
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 1rem;
            background-color: #fff;
            width: 18rem; /* Chiều rộng cố định */
            box-sizing: border-box;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .menu-item img {
            max-width: 100%;
            height: 12rem;
            object-fit: cover;
            border-radius: 5px;
        }

        .menu-item h2 {
            font-size: 1.2rem;
            margin: 0.5rem 0;
        }

        .menu-item p {
            font-size: 1rem;
            margin: 0.5rem 0;
        }

        .menu-item button {
            margin: 0.5rem 0.25rem;
            padding: 0.6rem 1.2rem;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .menu-item button:hover {
            background-color: #555;
        }

        .rating-stars {
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1rem;
            margin: 0.5rem 0;
        }

        .rating-stars span {
            margin-right: 0.3rem;
        }

        .success-message {
            color: green;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .search-filter {
            text-align: center;
            margin: 1.5rem 0;
        }

        .search-filter select {
            padding: 0.6rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-left: 0.6rem;
        }

        .no-items {
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 1rem;
            background-color: #fff;
            width: 18rem; /* Cùng chiều rộng với menu-item */
            box-sizing: border-box;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
            font-size: 1.2rem;
            color: #666;
        }

        /* Điều chỉnh cho màn hình nhỏ */
        @media (max-width: 60rem) {
            .menu-list {
                grid-template-columns: repeat(auto-fit, 15rem);
            }

            .menu-item, .no-items {
                width: 15rem;
            }
        }
    </style>
    <script>
        function clearSearchInput() {
            const searchInput = document.getElementById('searchInput');
            searchInput.value = '';
            document.getElementById('searchForm').submit();
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const sortSelect = document.getElementById('sortSelect');
            const form = document.getElementById('searchForm');

            const debouncedSubmit = debounce(() => form.submit(), 500);
            searchInput.addEventListener('input', debouncedSubmit);

            sortSelect.addEventListener('change', function() {
                form.submit();
            });

            <?php if (isset($_GET['search'])): ?>
                searchInput.focus();
                searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
            <?php endif; ?>
        });
    </script>
</head>
<body>
    <!-- Banner -->
    <div class="banner">
        <h1>Chào mừng đến với AFC</h1>
        <p>Một ngày tuyệt vời để ăn gà!</p>
    </div>

    <?php
    if (isset($_GET['added']) && $_GET['added'] == 1) {
        echo "<p class='success-message'>Món ăn đã được thêm vào giỏ hàng!</p>";
    }
    ?>

    <!-- Thanh tìm kiếm và lọc -->
    <div class="search-filter">
        <form id="searchForm" method="GET" action="">
            <div class="search-wrapper">
                <input type="text" name="search" id="searchInput" placeholder="Tìm theo tên hoặc mô tả..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                <button type="button" class="clear-button" onclick="clearSearchInput()">×</button>
            </div>
            <select name="sort" id="sortSelect">
                <option value="rating_desc" <?= isset($_GET['sort']) && $_GET['sort'] == 'rating_desc' ? 'selected' : '' ?>>Sao: Tăng dần</option>
                <option value="rating_asc" <?= isset($_GET['sort']) && $_GET['sort'] == 'rating_asc' ? 'selected' : '' ?>>Sao: Giảm dần</option>
                <option value="asc" <?= isset($_GET['sort']) && $_GET['sort'] == 'asc' ? 'selected' : '' ?>>Giá: Thấp đến Cao</option>
                <option value="desc" <?= isset($_GET['sort']) && $_GET['sort'] == 'desc' ? 'selected' : '' ?>>Giá: Cao đến Thấp</option>
            </select>
        </form>
    </div>

    <!-- Danh sách món ăn -->
    <div class="menu-list">
        <?php
        $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
        $sort = isset($_GET['sort']) ? $_GET['sort'] : 'rating_desc';

        $orderBy = "rating DESC";
        if ($sort == 'rating_asc') {
            $orderBy = "rating ASC";
        } elseif ($sort == 'asc') {
            $orderBy = "price ASC";
        } elseif ($sort == 'desc') {
            $orderBy = "price DESC";
        }

        $sql = "SELECT * FROM menu WHERE combo_name LIKE '%$search%' OR description LIKE '%$search%' ORDER BY $orderBy";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<div class='menu-item'>";
                echo "<h2>" . htmlspecialchars($row['combo_name']) . "</h2>";
                echo "<p>" . htmlspecialchars($row['description']) . "</p>";
                echo "<p>Giá: " . number_format($row['price'], 0, ',', '.') . " VND</p>";
                if (!empty($row['image'])) {
                    echo "<img src='../images/" . htmlspecialchars($row['image']) . "' alt='" . htmlspecialchars($row['combo_name']) . "'>";
                }

                $rating = $row['rating'];
                $rating_count = $row['rating_count'];
                echo "<div class='rating-stars'>";
                for ($i = 1; $i <= 5; $i++) {
                    if ($rating >= $i) {
                        echo "<span style='color: gold;'>★</span>";
                    } elseif ($rating >= $i - 0.5) {
                        echo "<span style='color: gold;'>☆</span>";
                    } else {
                        echo "<span style='color: lightgray;'>★</span>";
                    }
                }
                echo " <span>(" . $rating_count . " đánh giá)</span>";
                echo "</div>";

                echo "<div>";
                echo "<button onclick=\"location.href='details.php?id=" . $row['id'] . "'\">Xem thêm</button>";
                echo "<form method='POST' action='' style='display:inline;'>";
                echo "<input type='hidden' name='item_id' value='" . $row['id'] . "'>";
                echo "<button type='submit' name='add_to_cart'>Thêm vào giỏ hàng</button>";
                echo "</form>";
                echo "</div>";
                echo "</div>";
            }
        } else {
            echo "<p class='no-items'>Không tìm thấy món ăn nào!</p>";
        }

        $conn->close();
        ?>
    </div>
</body>
</html>

<?php include 'footer.php'; ?>