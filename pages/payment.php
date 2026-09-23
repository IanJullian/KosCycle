<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/authorization.php';
require_role('customer');
require_once __DIR__ . '/../includes/Services/PaymentService.php';

$userId = (int) $_SESSION['user_id'];
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId) {
    flash('error', 'ID pesanan tidak valid.');
    redirect(page_url('orders'));
}

try {
    $payment = (new PaymentService())->createPaymentForOrder((int)$orderId, $userId);
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    redirect(page_url('orders', ['id' => $orderId]));
}

if (is_post()) {
    if (!verify_csrf()) {
        flash('error', 'Sesi formulir tidak valid.');
        redirect(page_url('payment', ['id' => $orderId]));
    }

    $action = (string)($_POST['sandbox_action'] ?? '');

    if ($action === 'mark_paid') {
        if (MIDTRANS_IS_PRODUCTION || APP_ENV === 'production') {
            flash('error', 'Simulasi pembayaran hanya tersedia di mode Sandbox non-production.');
            redirect(page_url('payment', ['id' => $orderId]));
        }

        $check = db()->prepare(
            'SELECT p.id
             FROM payments p
             JOIN orders o ON o.id = p.order_id
             WHERE p.order_id = ? AND o.buyer_id = ?
             LIMIT 1'
        );
        $check->execute([(int)$orderId, $userId]);
        $paymentId = $check->fetchColumn();

        if (!$paymentId) {
            flash('error', 'Data pembayaran Sandbox tidak ditemukan.');
            redirect(page_url('payment', ['id' => $orderId]));
        }

        $demoTransactionId = 'SANDBOX-DEMO-' . (int)$orderId . '-' . date('YmdHis');
        $update = db()->prepare(
            "UPDATE payments
             SET payment_status = 'paid',
                 payment_type = 'sandbox_demo',
                 transaction_id = COALESCE(transaction_id, ?),
                 transaction_time = COALESCE(transaction_time, NOW()),
                 settlement_time = COALESCE(settlement_time, NOW()),
                 paid_at = COALESCE(paid_at, NOW())
             WHERE id = ?"
        );
        $update->execute([$demoTransactionId, (int)$paymentId]);

        flash('success', 'Mode demo: pembayaran ditandai berhasil.');
        redirect(page_url('orders', ['id' => $orderId]));
    }
}

$pageTitle = 'Pembayaran';
require __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/payment-polish-v2.css">

<script
    type="text/javascript"
    src="<?= MIDTRANS_IS_PRODUCTION
        ? 'https://app.midtrans.com/snap/snap.js'
        : 'https://app.sandbox.midtrans.com/snap/snap.js' ?>"
    data-client-key="<?= e(MIDTRANS_CLIENT_KEY) ?>">
</script>

<section class="section-padding page-section payment-page-v2">
    <div class="container payment-shell">
        <div class="page-toolbar">
            <?= back_link('orders', 'Kembali ke riwayat pesanan') ?>
        </div>

        <div class="row g-4 align-items-stretch">
            <div class="col-lg-7">
                <div class="glass-card payment-main-card h-100">
                    <div class="payment-mode-badge">
                        <i class="bi bi-flask"></i>
                        <?= MIDTRANS_IS_PRODUCTION ? 'Midtrans Production' : 'Midtrans Sandbox' ?>
                    </div>

                    <span class="eyebrow">Pembayaran aman</span>
                    <h2>Bayar Pesanan <em>#<?= (int)$orderId ?></em></h2>
                    <p class="text-muted">Pilih metode pembayaran melalui popup resmi Midtrans Snap. Pada Sandbox, transaksi tidak menggunakan uang nyata.</p>

                    <div class="payment-amount-box">
                        <small>Total pembayaran</small>
                        <strong><?= format_price((int)$payment['gross_amount']) ?></strong>
                        <span><i class="bi bi-shield-check"></i>Diproses melalui Midtrans Snap</span>
                    </div>

                    <button type="button" id="pay-button" class="btn btn-primary btn-lg w-100 payment-primary-btn">
                        <i class="bi bi-credit-card me-2"></i>Lanjut ke pembayaran
                    </button>

                    <div class="payment-trust-row">
                        <span><i class="bi bi-lock"></i>Koneksi aman</span>
                        <span><i class="bi bi-receipt"></i>Status tercatat</span>
                        <span><i class="bi bi-phone"></i>Mobile friendly</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="glass-card payment-side-card h-100">
                    <h3>Informasi pembayaran</h3>
                    <div class="payment-info-row"><span>Nomor pesanan</span><strong>#<?= (int)$orderId ?></strong></div>
                    <div class="payment-info-row"><span>Lingkungan</span><strong><?= MIDTRANS_IS_PRODUCTION ? 'Production' : 'Sandbox' ?></strong></div>
                    <div class="payment-info-row"><span>Total</span><strong><?= format_price((int)$payment['gross_amount']) ?></strong></div>

                    <div class="payment-help-box">
                        <i class="bi bi-info-circle"></i>
                        <div>
                            <strong>Popup tidak muncul?</strong>
                            <small>Pastikan koneksi internet aktif lalu coba kembali. Jangan refresh saat proses Snap sedang berjalan.</small>
                        </div>
                    </div>

                    <a class="btn btn-outline-secondary w-100" href="<?= e(page_url('chat', ['new' => 'support'])) ?>">
                        <i class="bi bi-headset me-2"></i>Hubungi admin
                    </a>

                    <?php if (!MIDTRANS_IS_PRODUCTION && APP_ENV !== 'production'): ?>
                        <details class="sandbox-test-panel">
                            <summary><i class="bi bi-tools me-2"></i>Alat uji Sandbox</summary>
                            <div class="sandbox-test-body">
                                <p>Fitur ini hanya untuk demo lokal/Sandbox. Tombol di bawah tidak memanggil pembayaran nyata dan otomatis dinonaktifkan pada Production.</p>
                                <form method="post" onsubmit="return confirm('Tandai pembayaran ini berhasil untuk keperluan demo Sandbox?')">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="sandbox_action" value="mark_paid">
                                    <button class="btn btn-sm btn-outline-success w-100" type="submit">
                                        <i class="bi bi-check2-circle me-1"></i>Simulasikan pembayaran berhasil
                                    </button>
                                </form>
                            </div>
                        </details>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
const payButton = document.getElementById('pay-button');

payButton.addEventListener('click', function () {
    payButton.disabled = true;
    payButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Membuka Midtrans...';

    window.snap.pay(
        <?= json_encode($payment['snap_token']) ?>,
        {
            onSuccess: function () {
                window.location.href = <?= json_encode(page_url('orders', ['id' => $orderId])) ?>;
            },
            onPending: function () {
                window.location.href = <?= json_encode(page_url('orders', ['id' => $orderId])) ?>;
            },
            onError: function () {
                payButton.disabled = false;
                payButton.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Coba pembayaran lagi';
                alert('Pembayaran gagal. Silakan coba lagi.');
            },
            onClose: function () {
                payButton.disabled = false;
                payButton.innerHTML = '<i class="bi bi-credit-card me-2"></i>Lanjut ke pembayaran';
            }
        }
    );
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
