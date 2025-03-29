<?php
session_start();
include 'dbconnection.php';

// Ngăn cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Kiểm tra trạng thái đăng nhập
$isLoggedIn = isset($_SESSION['user_id']);

// Khởi tạo giỏ hàng nếu người dùng đã đăng nhập
if ($isLoggedIn) {
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
        $redirectParams = [];
        if (isset($_GET['search'])) {
            $redirectParams[] = "search=" . urlencode($_GET['search']);
        }
        if (isset($_GET['sort'])) {
            $redirectParams[] = "sort=" . $_GET['sort'];
        }
        if (isset($_GET['categories'])) {
            $redirectParams[] = "categories=" . urlencode($_GET['categories']);
        }
        $redirectUrl = "menu.php?added=1" . (!empty($redirectParams) ? "&" . implode("&", $redirectParams) : "");
        header("Location: $redirectUrl");
        exit;
    }
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

// Lấy danh mục được chọn từ URL
$selectedCategories = [];
if (isset($_GET['categories'])) {
    $selectedCategories = array_map('intval', explode(',', $_GET['categories']));
}

// Lấy danh sách món ăn
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'rating_desc';

$orderBy = "m.rating DESC";
if ($sort == 'rating_asc') {
    $orderBy = "m.rating ASC";
} elseif ($sort == 'asc') {
    $orderBy = "m.price ASC";
} elseif ($sort == 'desc') {
    $orderBy = "m.price DESC";
}

$sql = "SELECT DISTINCT m.* 
        FROM menu m 
        LEFT JOIN menu_categories mc ON m.id = mc.menu_id 
        LEFT JOIN categories c ON mc.category_id = c.id 
        WHERE (m.combo_name LIKE ? OR m.description LIKE ?)";
$params = ["%$search%", "%$search%"];
$types = "ss";

if (!empty($selectedCategories)) {
    $placeholders = implode(',', array_fill(0, count($selectedCategories), '?'));
    $sql .= " AND mc.category_id IN ($placeholders)";
    $params = array_merge($params, $selectedCategories);
    $types .= str_repeat('i', count($selectedCategories));
}

$sql .= " ORDER BY $orderBy";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$menuItems = [];
while ($row = $result->fetch_assoc()) {
    // Lấy danh mục của món
    $stmt_cat = $conn->prepare("SELECT c.name FROM categories c JOIN menu_categories mc ON c.id = mc.category_id WHERE mc.menu_id = ?");
    $stmt_cat->bind_param("i", $row['id']);
    $stmt_cat->execute();
    $cat_result = $stmt_cat->get_result();
    $row['categories'] = [];
    while ($cat = $cat_result->fetch_assoc()) {
        $row['categories'][] = $cat['name'];
    }
    $stmt_cat->close();
    $menuItems[] = $row;
}
$stmt->close();
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

        .category-buttons {
            display: flex;
            gap: 10px;
            margin: 1.5rem 0;
            flex-wrap: wrap;
            justify-content: center;
        }

        .category-buttons label {
            display: inline-block;
        }

        .category-buttons input[type="checkbox"] {
            display: none;
        }

        .category-buttons .category-btn {
            padding: 10px 20px;
            background-color: #ffffff;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .category-buttons input[type="checkbox"]:checked + .category-btn {
            background-color: #d4edda;
        }

        .category-buttons .category-btn:hover {
            background-color: #e0e0e0;
        }

        .menu-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, 18rem);
            gap: 1.5rem;
            padding: 2rem;
            max-width: 100vw;
            margin: 0 auto;
            justify-content: center;
        }

        .menu-item {
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 1rem;
            background-color: #fff;
            width: 18rem;
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

        .menu-item .category {
            font-size: 0.9rem;
            color: #666;
            margin: 0.3rem 0;
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
            width: 18rem;
            box-sizing: border-box;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
            font-size: 1.2rem;
            color: #666;
        }

        .login-prompt {
            text-align: center;
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .login-prompt a {
            color: #333;
            text-decoration: underline;
        }

        .login-prompt a:hover {
            color: #555;
        }

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

        function updateCategoryFilter() {
            const checkboxes = document.querySelectorAll('input[name="categories[]"]:checked');
            const selectedCategories = Array.from(checkboxes).map(cb => cb.value).join(',');
            const form = document.getElementById('categoryForm');
            const hiddenInput = document.getElementById('selectedCategories');
            hiddenInput.value = selectedCategories;
            form.submit();
        }

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const sortSelect = document.getElementById('sortSelect');
            const searchForm = document.getElementById('searchForm');

            const debouncedSubmit = debounce(() => searchForm.submit(), 500);
            searchInput.addEventListener('input', debouncedSubmit);

            sortSelect.addEventListener('change', function() {
                searchForm.submit();
            });

            <?php if (isset($_GET['search'])): ?>
                searchInput.focus();
                searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
            <?php endif; ?>
        });
    </script>
</head>
<body>

    <?php include 'banner_slider.php'; ?>

    <?php
    if (isset($_GET['added']) && $_GET['added'] == 1) {
        echo "<p class='success-message'>Món ăn đã được thêm vào giỏ hàng!</p>";
    }
    ?>

    <!-- Button danh mục -->
    <div class="category-buttons">
        <form id="categoryForm" method="GET" action="">
            <?php foreach ($categories as $category): ?>
                <label>
                    <input type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" 
                        <?php echo in_array($category['id'], $selectedCategories) ? 'checked' : ''; ?>
                        onchange="updateCategoryFilter()">
                    <span class="category-btn"><?php echo htmlspecialchars($category['name']); ?></span>
                </label>
            <?php endforeach; ?>
            <input type="hidden" name="categories" id="selectedCategories" value="<?php echo htmlspecialchars(implode(',', $selectedCategories)); ?>">
            <?php if (isset($_GET['search'])): ?>
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search']); ?>">
            <?php endif; ?>
            <?php if (isset($_GET['sort'])): ?>
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($_GET['sort']); ?>">
            <?php endif; ?>
        </form>
    </div>

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
            <?php if (!empty($selectedCategories)): ?>
                <input type="hidden" name="categories" value="<?php echo htmlspecialchars(implode(',', $selectedCategories)); ?>">
            <?php endif; ?>
        </form>
    </div>

    <!-- Danh sách món ăn -->
    <div class="menu-list">
        <?php
        if (!empty($menuItems)) {
            foreach ($menuItems as $row) {
                echo "<div class='menu-item'>";
                echo "<h2>" . htmlspecialchars($row['combo_name']) . "</h2>";
                echo "<p class='category'>" . htmlspecialchars(implode(", ", $row['categories'])) . "</p>";
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
                if ($isLoggedIn) {
                    echo "<form method='POST' action='' style='display:inline;'>";
                    echo "<input type='hidden' name='item_id' value='" . $row['id'] . "'>";
                    echo "<button type='submit' name='add_to_cart'>Thêm vào giỏ hàng</button>";
                    echo "</form>";
                } else {
                    echo "<p class='login-prompt'><a href='user_login.php'>Đăng nhập</a> để thêm vào giỏ hàng</p>";
                }
                echo "</div>";
                echo "</div>";
            }
        } else {
            echo "<p class='no-items'>Không tìm thấy món ăn nào!</p>";
        }
        ?>
    </div>
</body>
</html>

<?php
$conn->close();
include 'footer.php';
?>