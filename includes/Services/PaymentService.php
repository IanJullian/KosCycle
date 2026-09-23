<?php
declare(strict_types=1);

class PaymentService
{
    public function createPaymentForOrder(
        int $orderId,
        int $buyerId,
        bool $allowLocalSandboxDemo = false
    ): array {
        $pdo = db();

        $stmt = $pdo->prepare(
            "SELECT
                o.id,
                o.buyer_id,
                o.status,
                COALESCE(SUM(oi.line_total), 0) AS total,
                u.full_name,
                u.email,
                u.whatsapp
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             JOIN users u ON u.id = o.buyer_id
             WHERE o.id = ?
               AND o.buyer_id = ?
             GROUP BY
                o.id,
                o.buyer_id,
                o.status,
                u.full_name,
                u.email,
                u.whatsapp
             LIMIT 1"
        );
        $stmt->execute([$orderId, $buyerId]);
        $order = $stmt->fetch();

        if (!$order) {
            throw new RuntimeException('Pesanan tidak ditemukan.');
        }

        // Pay-first: buyer boleh membayar segera setelah checkout.
        if (!in_array((string) $order['status'], ['requested', 'accepted'], true)) {
            throw new RuntimeException('Pesanan ini sudah tidak dapat dibayar.');
        }

        $grossAmount = (int) $order['total'];
        if ($grossAmount <= 0) {
            throw new RuntimeException('Total pembayaran tidak valid.');
        }

        $paymentStmt = $pdo->prepare('SELECT * FROM payments WHERE order_id = ? LIMIT 1');
        $paymentStmt->execute([$orderId]);
        $payment = $paymentStmt->fetch() ?: null;

        if ($payment && $payment['payment_status'] === 'paid') {
            return $payment;
        }

        // Jangan membuat transaksi Midtrans baru setiap kali halaman dibuka.
        if ($payment
            && in_array((string) $payment['payment_status'], ['unpaid', 'pending'], true)
            && !empty($payment['snap_token'])) {
            return $payment;
        }

        $midtransOrderId = 'KOSCYCLE-' . $orderId . '-' . date('YmdHis');
        $snapToken = null;
        $midtransError = null;
        $midtransConfigured = MIDTRANS_SERVER_KEY !== '' && MIDTRANS_CLIENT_KEY !== '';

        if ($midtransConfigured) {
            \Midtrans\Config::$serverKey = MIDTRANS_SERVER_KEY;
            \Midtrans\Config::$isProduction = MIDTRANS_IS_PRODUCTION;
            \Midtrans\Config::$isSanitized = true;
            \Midtrans\Config::$is3ds = true;
        } elseif (!$allowLocalSandboxDemo) {
            throw new RuntimeException('Midtrans Server Key atau Client Key belum diisi.');
        }

        $params = [
            'transaction_details' => [
                'order_id' => $midtransOrderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $order['full_name'],
                'email' => $order['email'],
                'phone' => $order['whatsapp'],
            ],
        ];

        if ($midtransConfigured) {
            try {
                $snapToken = \Midtrans\Snap::getSnapToken($params);
            } catch (Throwable $e) {
                $midtransError = $e;
                if (!$allowLocalSandboxDemo) {
                    throw new RuntimeException(
                        'Gagal membuat transaksi Midtrans. Periksa Server Key dan koneksi hosting.'
                    );
                }
            }
        } else {
            $midtransError = new RuntimeException('Midtrans Sandbox belum dikonfigurasi.');
        }

        if ($midtransError !== null) {
            // Sandbox demo fallback: tetap buat record pembayaran lokal supaya
            // tombol simulator dapat dipakai walau Snap Sandbox tidak terjangkau.
            $midtransOrderId = 'KOSCYCLE-DEMO-' . $orderId . '-' . date('YmdHis');
        }

        if ($payment) {
            $update = $pdo->prepare(
                "UPDATE payments
                 SET midtrans_order_id = ?,
                     transaction_id = NULL,
                     snap_token = ?,
                     payment_status = 'unpaid',
                     payment_type = NULL,
                     fraud_status = NULL,
                     transaction_time = NULL,
                     settlement_time = NULL,
                     paid_at = NULL,
                     raw_notification = NULL,
                     gross_amount = ?,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE order_id = ?"
            );
            $update->execute([$midtransOrderId, $snapToken, $grossAmount, $orderId]);
        } else {
            $insert = $pdo->prepare(
                "INSERT INTO payments (
                    order_id,
                    midtrans_order_id,
                    snap_token,
                    payment_status,
                    gross_amount
                 ) VALUES (?, ?, ?, 'unpaid', ?)"
            );
            $insert->execute([$orderId, $midtransOrderId, $snapToken, $grossAmount]);
        }

        $resultStmt = $pdo->prepare('SELECT * FROM payments WHERE order_id = ? LIMIT 1');
        $resultStmt->execute([$orderId]);
        $result = $resultStmt->fetch();

        if (!$result) {
            throw new RuntimeException('Data pembayaran gagal disimpan.');
        }

        // Marker non-persisten agar UI dapat menjelaskan kenapa hanya tombol
        // simulator yang tersedia. Tidak ada detail secret/error yang diekspos.
        $result['_midtrans_unavailable'] = $midtransError !== null;

        return $result;
    }
}
