<?php
// Kết nối database
include 'dbconnection.php';

// Lấy danh sách banner chưa hết hạn
$currentDate = date('Y-m-d H:i:s');
$query = "SELECT * FROM banners WHERE expiry_date IS NULL OR expiry_date > ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $currentDate);
$stmt->execute();
$result = $stmt->get_result();
$banners = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!-- Thêm Swiper CSS -->
<link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />

<!-- HTML và CSS cho slider banner -->
<div class="banner-slider">
    <div class="swiper-container">
        <div class="swiper-wrapper">
            <?php if (empty($banners)): ?>
                <div class="swiper-slide" style="background: #333;">
                    <div class="banner-content">
                        <div class="banner">
                            <h1>Chào mừng đến với AFC</h1>
                            <p>Một ngày tuyệt vời để ăn gà!</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($banners as $banner): ?>
                    <div class="swiper-slide">
                        <img src="../images/<?php echo htmlspecialchars($banner['background']); ?>" alt="<?php echo htmlspecialchars($banner['title']); ?>">
                        <!-- Thêm lớp phủ gradient -->
                        <div class="banner-overlay"></div>
                        <div class="banner-content">
                            <h2><?php echo htmlspecialchars($banner['title']); ?></h2>
                            <p><?php echo htmlspecialchars($banner['description']); ?></p>
                            <a href="banner_detail.php?id=<?php echo $banner['id']; ?>">Xem Chi Tiết</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <!-- Thêm pagination và navigation -->
        <div class="swiper-pagination"></div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
    </div>
</div>

<!-- CSS cho slider banner -->
<style>
    .banner-slider {
        width: 100%;
        height: 400px;
        margin: 0 auto;
        position: relative;
        overflow: hidden;
        z-index: 500; /* z-index thấp hơn navbar */
    }

    .swiper-container {
        width: 100%;
        height: 100%;
    }

    .swiper-slide {
        position: relative;
        text-align: center;
        color: white;
        height: 400px;
        display: flex;
        justify-content: flex-start;
        align-items: flex-end;
        background-size: cover;
        background-position: center;
    }

    .swiper-slide img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        position: absolute;
        top: 0;
        left: 0;
        z-index: -1;
    }

    .banner-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 50%;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
        z-index: 0;
    }

    .banner-content {
        padding: 20px;
        text-align: left;
        z-index: 1;
        max-width: 50%;
        margin-left: 20px;
        margin-bottom: 20px;
    }

    .banner-content h2 {
        margin: 0 0 10px;
        font-size: 2rem;
    }

    .banner-content p {
        margin: 0 0 15px;
        font-size: 1.2rem;
    }

    .banner-content a {
        display: inline-block;
        padding: 10px 20px;
        background-color: #333;
        color: white;
        text-decoration: none;
        border-radius: 5px;
    }

    .banner-content a:hover {
        background-color: #555;
    }

    .swiper-pagination-bullet {
        background: white;
    }

    .swiper-button-next,
    .swiper-button-prev {
        color: white;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(0, 0, 0, 0.5);
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.3s ease;
    }

    .swiper-button-next:hover,
    .swiper-button-prev:hover {
        background: rgba(0, 0, 0, 0.8);
    }

    .swiper-button-prev {
        left: -50px;
    }

    .swiper-button-next {
        right: -50px;
    }

    @media (max-width: 768px) {
        .swiper-button-prev {
            left: 10px;
        }

        .swiper-button-next {
            right: 10px;
        }
    }

    .banner {
        background-color: #f5c518;
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
</style>

<!-- Thêm Swiper JS và khởi tạo -->
<script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const swiper = new Swiper('.swiper-container', {
            loop: true,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
        });
    });
</script>