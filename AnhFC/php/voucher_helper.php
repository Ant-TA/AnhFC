<?php
// voucher_helper.php

// Hàm hoàn lại voucher khi đơn hàng bị hủy
function refundVoucher($conn, $orderId) {
    // Lấy danh sách voucher đã áp dụng cho đơn hàng
    $stmt = $conn->prepare("SELECT voucher_id FROM order_vouchers WHERE order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $vouchers = [];
    while ($row = $result->fetch_assoc()) {
        $vouchers[] = $row['voucher_id'];
    }
    $stmt->close();

    // Hoàn lại từng voucher
    foreach ($vouchers as $voucherId) {
        // Kiểm tra voucher có còn hợp lệ không
        $stmt = $conn->prepare("SELECT expiration_date, usage_limit, used_count FROM vouchers WHERE id = ?");
        $stmt->bind_param("i", $voucherId);
        $stmt->execute();
        $result = $stmt->get_result();
        $voucher = $result->fetch_assoc();
        $stmt->close();

        if ($voucher) {
            $currentDate = date('Y-m-d H:i:s');
            $isExpired = strtotime($voucher['expiration_date']) < strtotime($currentDate);
            if (!$isExpired && $voucher['used_count'] > 0) {
                // Giảm used_count
                $newUsedCount = $voucher['used_count'] - 1;
                $stmt = $conn->prepare("UPDATE vouchers SET used_count = ? WHERE id = ?");
                $stmt->bind_param("ii", $newUsedCount, $voucherId);
                $stmt->execute();
                $stmt->close();
            }
        }

        // Xóa bản ghi trong order_vouchers
        $stmt = $conn->prepare("DELETE FROM order_vouchers WHERE order_id = ? AND voucher_id = ?");
        $stmt->bind_param("ii", $orderId, $voucherId);
        $stmt->execute();
        $stmt->close();
    }
}
?>