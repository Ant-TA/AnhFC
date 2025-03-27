<?php
include 'header.php'; // Thêm header từ file riêng
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký Người Dùng</title>
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
        /* CSS tương tự login.php */
        .register-form {
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .register-form h1 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 1.8em;
            color: #333;
        }

        .register-form label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
            color: #333;
        }

        .register-form input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .register-form button {
            display: block;
            width: 100%;
            padding: 10px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .register-form button:hover {
            background-color: #555;
        }

        .success-message, .error-message {
            text-align: center;
            margin-bottom: 15px;
        }

        .success-message {
            color: green;
        }

        .error-message {
            color: red;
        }
    </style>
</head>
<body>
    <div class="register-form">
        <h1>Đăng Ký Người Dùng</h1>
        <?php
        include 'dbconnection.php';

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $username = $_POST['username'];
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $address = $_POST['address'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Mã hóa mật khẩu
            $created_at = date('Y-m-d H:i:s');

            // Kiểm tra username hoặc email đã tồn tại chưa
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $checkStmt->bind_param("ss", $username, $email);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                echo "<p class='error-message'>Tên đăng nhập hoặc email đã tồn tại!</p>";
            } else {
                // Thêm người dùng mới vào database
                $stmt = $conn->prepare("INSERT INTO users (username, email, phone, address, password, created_at) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $username, $email, $phone, $address, $password, $created_at);

                if ($stmt->execute()) {
                    // Đăng ký thành công, tự động chuyển hướng sau 2 giây
                    echo "<p class='success-message'>Đăng ký thành công! Bạn sẽ được chuyển đến trang đăng nhập trong 2 giây...</p>";
                    echo "<script>setTimeout(() => { window.location.href = 'user_login.php'; }, 2000);</script>";
                } else {
                    echo "<p class='error-message'>Đăng ký thất bại. Vui lòng thử lại!</p>";
                }
                $stmt->close();
            }
            $checkStmt->close();
        }
        $conn->close();
        ?>
        <form method="POST" action="">
            <label for="username">Tên Đăng Nhập:</label>
            <input type="text" id="username" name="username" required>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>

            <label for="phone">Số Điện Thoại:</label>
            <input type="text" id="phone" name="phone" required>

            <label for="address">Địa Chỉ:</label>
            <input type="text" id="address" name="address" required>

            <label for="password">Mật Khẩu:</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Đăng Ký</button>
        </form>
    </div>
</body>
</html>

<?php
include 'footer.php'; // Thêm footer từ file riêng
?>