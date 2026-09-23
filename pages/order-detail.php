<?php
require_once __DIR__ . '/../includes/authorization.php';
require_role('customer');
require_once __DIR__ . '/../includes/Repositories/OrderRepository.php';

$repo = new OrderRepository();
$userId = (int) $_SESSION['user_id'];
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId) {
    http_response_code(400);
    exit('Pesanan tidak valid.');
}

$details = $repo->items((int)$orderId, $userId);

if (!$details) {
    http_response_code(404);
    exit('Pesanan tidak ditemukan.');
}

$statusLabels = [
    'requested' => 'Menunggu seller',
    'accepted' => 'Sedang diproses',
    'completed' => 'Selesai',
    'cancelled' => 'Dibatalkan',
];

$paymentLabels = [
    'unpaid' => 'Belum dibayar',
    'pending' => 'Menunggu pembayaran',
    'paid' => 'Sudah dibayar',
    'failed' => 'Pembayaran gagal',
    'expired' => 'Pembayaran kedaluwarsa',
    'cancelled' => 'Pembayaran dibatalkan',
];

$paymentStatus = (string)($details[0]['payment_status'] ?? 'unpaid');
$paymentType = (string)($details[0]['payment_type'] ?? '');
$orderStatus = (string)$details[0]['status'];
$orderTotal = (int) array_sum(array_map(
    static fn(array $row): int => (int)$row['line_total'],
    $details
));
$accepted = in_array($orderStatus, ['accepted', 'completed'], true);
$paid = $paymentStatus === 'paid';
$completed = $orderStatus === 'completed';

$pageTitle = 'Detail Pesanan #' . (int)$orderId;
require __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/order-review-polish.css">
<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/orders-commerce-v2.css">

<section class="section-padding page-section orders-page orders-commerce-page">
    <div class="container">
        <div class="page-toolbar">
            <?= back_link('orders', 'Kembali ke pesanan saya') ?>
        </div>

        <section class="order-commerce-detail">
            <div class="order-detail-titlebar">
                <div>
                    <span class="eyebrow">Detail pesanan #<?= (int)$orderId ?></span>
                    <h3><?= e($statusLabels[$orderStatus] ?? $orderStatus) ?></h3>
                    <small class="text-muted"><?= e((string)$details[0]['created_at']) ?></small>
                </div>
                <div class="order-detail-title-total">
                    <small>Total transaksi</small>
                    <strong><?= format_price($orderTotal) ?></strong>
                </div>
            </div>

            <?php if ($orderStatus === 'cancelled'): ?>
                <div class="alert alert-danger order-commerce-alert">
                    <i class="bi bi-x-circle-fill"></i>
                    Pesanan ini telah dibatalkan.
                </div>
            <?php else: ?>
                <div class="order-progress" aria-label="Progres pesanan">
                    <div class="order-progress-step is-active">
                        <span><i class="bi bi-receipt"></i></span><small>Dibuat</small>
                    </div>
                    <div class="order-progress-line <?= $accepted ? 'is-active' : '' ?>"></div>
                    <div class="order-progress-step <?= $accepted ? 'is-active' : '' ?>">
                        <span><i class="bi bi-shop"></i></span><small>Diterima</small>
                    </div>
                    <div class="order-progress-line <?= $paid ? 'is-active' : '' ?>"></div>
                    <div class="order-progress-step <?= $paid ? 'is-active' : '' ?>">
                        <span><i class="bi bi-credit-card"></i></span><small>Dibayar</small>
                    </div>
                    <div class="order-progress-line <?= $completed ? 'is-active' : '' ?>"></div>
                    <div class="order-progress-step <?= $completed ? 'is-active' : '' ?>">
                        <span><i class="bi bi-check2"></i></span><small>Selesai</small>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row g-3 mt-1">
                <div class="col-lg-8">
                    <div class="glass-card order-detail-panel h-100">
                        <div class="order-panel-head">
                            <div>
                                <span>Produk dibeli</span>
                                <strong><?= count($details) ?> item</strong>
                            </div>
                        </div>

                        <div class="order-products-list">
                            <?php foreach ($details as $d): ?>
                                <div class="order-product-row">
                                    <div class="order-product-icon"><i class="bi bi-box-seam"></i></div>
                                    <div class="order-product-copy">
                                        <strong><?= e((string)$d['product_name']) ?></strong>
                                        <span><?= (int)$d['quantity'] ?> × <?= format_price((int)$d['unit_price']) ?></span>
                                        <small>Seller: <?= e((string)$d['seller_name']) ?></small>
                                    </div>
                                    <strong class="order-product-subtotal"><?= format_price((int)$d['line_total']) ?></strong>
                                </div>

                                <?php if ($d['status'] === 'completed'): ?>
                                    <?php if ($d['review_id']): ?>
                                        <div class="order-review-state-v2">
                                            <div>
                                                <i class="bi bi-check-circle-fill"></i>
                                                <span><strong>Sudah diulas</strong><small>Ulasanmu tersimpan.</small></span>
                                            </div>
                                            <div class="order-stars">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi <?= $i <= (int)$d['review_rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                                                <?php endfor; ?>
                                                <strong><?= (int)$d['review_rating'] ?>/5</strong>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="order-review-state-v2">
                                            <div>
                                                <i class="bi bi-star"></i>
                                                <span><strong>Bagikan pengalamanmu</strong><small>Beri ulasan untuk produk ini.</small></span>
                                            </div>
                                            <a class="btn btn-soft btn-sm" href="<?= e(page_url('review', ['product_id' => $d['product_id']])) ?>">Beri ulasan</a>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="glass-card order-summary-panel">
                        <h4>Ringkasan transaksi</h4>

                        <div class="order-summary-row">
                            <span>Status pesanan</span>
                            <strong><?= e($statusLabels[$orderStatus] ?? $orderStatus) ?></strong>
                        </div>
                        <div class="order-summary-row">
                            <span>Status pembayaran</span>
                            <strong><?= e($paymentLabels[$paymentStatus] ?? $paymentStatus) ?></strong>
                        </div>
                        <?php if ($paymentType): ?>
                            <div class="order-summary-row">
                                <span>Metode</span>
                                <strong><?= e($paymentType) ?></strong>
                            </div>
                        <?php endif; ?>
                        <div class="order-summary-row order-summary-total">
                            <span>Total</span>
                            <strong><?= format_price($orderTotal) ?></strong>
                        </div>

                        <?php if (!empty($details[0]['note'])): ?>
                            <div class="order-customer-note">
                                <small>Catatan pesanan</small>
                                <p><?= nl2br(e((string)$details[0]['note'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($orderStatus === 'accepted' && !$paid): ?>
                            <a class="btn btn-primary w-100" href="<?= e(page_url('payment', ['id' => $orderId])) ?>">
                                <i class="bi bi-shield-lock me-2"></i>Bayar sekarang
                            </a>
                        <?php elseif ($paid): ?>
                            <div class="order-paid-box">
                                <i class="bi bi-check-circle-fill"></i>
                                <span><strong>Pembayaran terkonfirmasi</strong><small>Transaksi tercatat sebagai sudah dibayar.</small></span>
                            </div>
                        <?php elseif ($orderStatus === 'requested'): ?>
                            <div class="order-wait-box">
                                <i class="bi bi-clock-history"></i>
                                <span><strong>Menunggu seller</strong><small>Pembayaran tersedia setelah seller menerima pesanan.</small></span>
                            </div>
                        <?php endif; ?>

                        <a class="btn btn-outline-secondary w-100 mt-2" href="<?= e(page_url('chat', ['new' => 'support'])) ?>">
                            <i class="bi bi-headset me-2"></i>Laporkan masalah
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
