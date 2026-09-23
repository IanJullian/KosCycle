<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/Services/MidtransConfig.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = null;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);

        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed',
        ]);

        exit;
    }

    $rawBody = file_get_contents('php://input');

    if (!$rawBody) {
        throw new RuntimeException(
            'Empty notification body.'
        );
    }

    $payload = json_decode(
        $rawBody,
        true
    );

    if (!is_array($payload)) {
        throw new RuntimeException(
            'Invalid JSON notification.'
        );
    }

    $orderId = (string)(
        $payload['order_id'] ?? ''
    );

    $statusCode = (string)(
        $payload['status_code'] ?? ''
    );

    $grossAmount = (string)(
        $payload['gross_amount'] ?? ''
    );

    $signatureKey = (string)(
        $payload['signature_key'] ?? ''
    );

    if (
        $orderId === ''
        || $statusCode === ''
        || $grossAmount === ''
        || $signatureKey === ''
    ) {
        throw new RuntimeException(
            'Notification tidak lengkap.'
        );
    }

    $expectedSignature = hash(
        'sha512',
        $orderId
        . $statusCode
        . $grossAmount
        . MIDTRANS_SERVER_KEY
    );

    if (!hash_equals(
        $expectedSignature,
        $signatureKey
    )) {
        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid signature.',
        ]);

        exit;
    }

    if (
        MIDTRANS_MERCHANT_ID !== ''
        && isset($payload['merchant_id'])
        && (string)$payload['merchant_id']
            !== MIDTRANS_MERCHANT_ID
    ) {
        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid merchant.',
        ]);

        exit;
    }

    $transactionStatus = (string)(
        $payload['transaction_status'] ?? ''
    );

    $fraudStatus = $payload['fraud_status'] ?? null;

    $paymentType = $payload['payment_type'] ?? null;

    $transactionId = $payload['transaction_id'] ?? null;

    $paymentStatus = match (
        $transactionStatus
    ) {
        'capture' => (
            $fraudStatus === null
            || strtolower((string)$fraudStatus) === 'accept'
        )
            ? 'paid'
            : 'pending',

        'settlement',
        'authorize' => 'paid',

        'pending' => 'pending',

        'deny',
        'failure' => 'failed',

        'expire' => 'expired',

        'cancel' => 'cancelled',

        default => 'pending',
    };

    $pdo = db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT *
         FROM payments
         WHERE midtrans_order_id = ?
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->execute([$orderId]);

    $payment = $stmt->fetch();

    if (!$payment) {
        throw new RuntimeException(
            'Payment tidak ditemukan.'
        );
    }

    if (
        $payment['payment_status'] === 'paid'
        && $paymentStatus !== 'paid'
    ) {
        $paymentStatus = 'paid';
    }

    $paidAt = $payment['paid_at'];

    if (
        $paymentStatus === 'paid'
        && empty($paidAt)
    ) {
        $paidAt = date('Y-m-d H:i:s');
    }

    $transactionTime =
        $payload['transaction_time']
        ?? null;

    $settlementTime =
        $payload['settlement_time']
        ?? null;

    $update = $pdo->prepare(
        "UPDATE payments
         SET
            transaction_id = ?,
            payment_status = ?,
            payment_type = ?,
            fraud_status = ?,
            transaction_time = ?,
            settlement_time = ?,
            paid_at = ?,
            raw_notification = ?
         WHERE id = ?"
    );

    $update->execute([
        $transactionId,
        $paymentStatus,
        $paymentType,
        $fraudStatus,
        $transactionTime,
        $settlementTime,
        $paidAt,
        json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE
        ),
        $payment['id'],
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Notification processed.',
    ]);

} catch (Throwable $e) {

    if (
        $pdo instanceof PDO
        && $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Notification processing failed.',
    ]);
}
