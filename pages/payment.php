<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/authorization.php';
require_role('customer');

require_once __DIR__ . '/../includes/Services/PaymentService.php';

$userId = (int) $_SESSION['user_id'];

$orderId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$orderId) {
    flash('error', 'ID pesanan tidak valid.');
    redirect(page_url('orders'));
}

try {
    $payment = (new PaymentService())->createPaymentForOrder(
        (int) $orderId,
        $userId
    );
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    redirect(page_url('orders', ['id' => $orderId]));
}

$pageTitle = 'Pembayaran';
require __DIR__ . '/../includes/header.php';
?>

<script
    type="text/javascript"
    src="<?= MIDTRANS_IS_PRODUCTION
        ? 'https://app.midtrans.com/snap/snap.js'
        : 'https://app.sandbox.midtrans.com/snap/snap.js' ?>"
    data-client-key="<?= e(MIDTRANS_CLIENT_KEY) ?>">
</script>

<section class="section-padding page-section">
    <div class="container">
        <div class="glass-card p-4 text-center">
            <span class="eyebrow">Pembayaran Sandbox</span>

            <h2 class="mt-2">
                Bayar Pesanan <em>#<?= (int) $orderId ?></em>
            </h2>

            <p class="text-muted">
                Ini adalah pembayaran simulasi Midtrans Sandbox.
            </p>

            <div class="my-4">
                <strong class="fs-3">
                    <?= format_price((int) $payment['gross_amount']) ?>
                </strong>
            </div>

            <button
                type="button"
                id="pay-button"
                class="btn btn-primary"
            >
                <i class="bi bi-credit-card me-2"></i>
                Bayar dengan Midtrans
            </button>
        </div>
    </div>
</section>

<script>
document
    .getElementById('pay-button')
    .addEventListener('click', function () {
        window.snap.pay(
            <?= json_encode($payment['snap_token']) ?>,
            {
                onSuccess: function () {
                    window.location.href =
                        <?= json_encode(
                            page_url('orders', ['id' => $orderId])
                        ) ?>;
                },

                onPending: function () {
                    window.location.href =
                        <?= json_encode(
                            page_url('orders', ['id' => $orderId])
                        ) ?>;
                },

                onError: function () {
                    alert(
                        'Pembayaran gagal. Silakan coba lagi.'
                    );
                },

                onClose: function () {
                    console.log(
                        'Popup pembayaran ditutup.'
                    );
                }
            }
        );
    });
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
