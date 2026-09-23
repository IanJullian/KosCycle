<?php
declare(strict_types=1);

require_once __DIR__ . '/MidtransConfig.php';

class PaymentService
{
    public function createPaymentForOrder(int $orderId, int $buyerId): array
    {
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

        if ($order['status'] !== 'accepted') {
            throw new RuntimeException(
                'Pembayaran tersedia setelah seller menerima pesanan.'
            );
        }

        $grossAmount = (int) $order['total'];

        if ($grossAmount <= 0) {
            throw new RuntimeException('Total pembayaran tidak valid.');
        }

        $paymentStmt = $pdo->prepare(
            "SELECT *
             FROM payments
             WHERE order_id = ?
             LIMIT 1"
        );

        $paymentStmt->execute([$orderId]);
        $payment = $paymentStmt->fetch();

        if (!$payment) {
            $payment = null;
        } elseif ($payment['payment_status'] === 'paid') {
            throw new RuntimeException(
                'Pesanan ini sudah dibayar.'
            );
        } elseif (
            $payment['payment_status'] === 'pending'
            && !empty($payment['snap_token'])
        ) {
            return $payment;
        }

        $midtransOrderId =
            'KOSCYCLE-' . $orderId . '-' . date('YmdHis');

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

        try {
            $snapToken = \Midtrans\Snap::getSnapToken($params);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Gagal membuat transaksi Midtrans. Periksa Server Key dan koneksi hosting.'
            );
        }

        if ($payment) {
            $update = $pdo->prepare(
                "UPDATE payments
                 SET
                    midtrans_order_id = ?,
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

            $update->execute([
                $midtransOrderId,
                $snapToken,
                $grossAmount,
                $orderId,
            ]);
        } else {
            $insert = $pdo->prepare(
                "INSERT INTO payments (
                    order_id,
                    midtrans_order_id,
                    snap_token,
                    payment_status,
                    gross_amount
                 )
                 VALUES (?, ?, ?, 'unpaid', ?)"
            );

            $insert->execute([
                $orderId,
                $midtransOrderId,
                $snapToken,
                $grossAmount,
            ]);
        }

        $resultStmt = $pdo->prepare(
            "SELECT *
             FROM payments
             WHERE order_id = ?
             LIMIT 1"
        );

        $resultStmt->execute([$orderId]);

        $result = $resultStmt->fetch();

        if (!$result) {
            throw new RuntimeException(
                'Data pembayaran gagal disimpan.'
            );
        }

        return $result;
    }
}
