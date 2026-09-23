<?php
require_once __DIR__ . '/../includes/authorization.php';
require_role('seller');
require_once __DIR__ . '/../includes/Repositories/OrderRepository.php';

$repo = new OrderRepository();
$sellerId = (int) $_SESSION['user_id'];
$orders = $repo->sellerOrders($sellerId);

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

$summary = [
    'requested' => 0,
    'accepted' => 0,
    'completed' => 0,
    'paid' => 0,
];

foreach ($orders as $order) {
    if (isset($summary[$order['status']])) {
        $summary[$order['status']]++;
    }
    if (($order['payment_status'] ?? '') === 'paid') {
        $summary['paid']++;
    }
}

$pageTitle = 'Pesanan Seller';
require __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/order-review-polish.css">
<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/seller-orders-v2.css">

<section class="section-padding page-section seller-orders-page seller-orders-v2">
    <div class="container">
        <div class="page-toolbar">
            <?= back_link('seller-dashboard', 'Kembali ke dashboard') ?>
        </div>

        <div class="seller-orders-heading">
            <div>
                <span class="eyebrow">Seller center</span>
                <h2>Kelola <em>pesanan.</em></h2>
                <p class="text-muted mb-0">Pilih pesanan untuk melihat detail, pembayaran, produk, ulasan, dan mengubah status.</p>
            </div>
            <a class="btn btn-soft" href="<?= e(page_url('chat', ['new' => 'support'])) ?>">
                <i class="bi bi-headset me-2"></i>Hubungi admin
            </a>
        </div>

        <div class="seller-order-stats">
            <div class="glass-card seller-stat-card"><span><i class="bi bi-hourglass-split"></i></span><div><small>Pesanan baru</small><strong><?= (int)$summary['requested'] ?></strong></div></div>
            <div class="glass-card seller-stat-card"><span><i class="bi bi-box-seam"></i></span><div><small>Diproses</small><strong><?= (int)$summary['accepted'] ?></strong></div></div>
            <div class="glass-card seller-stat-card"><span><i class="bi bi-credit-card"></i></span><div><small>Sudah dibayar</small><strong><?= (int)$summary['paid'] ?></strong></div></div>
            <div class="glass-card seller-stat-card"><span><i class="bi bi-check2-circle"></i></span><div><small>Selesai</small><strong><?= (int)$summary['completed'] ?></strong></div></div>
        </div>

        <?php if ($message = flash('success')): ?>
            <div class="alert alert-success glass-alert mb-4"><?= e($message) ?></div>
        <?php endif; ?>

        <?php if ($message = flash('error')): ?>
            <div class="alert alert-danger glass-alert mb-4"><?= e($message) ?></div>
        <?php endif; ?>

        <?php if (!$orders): ?>
            <div class="glass-card seller-order-empty">
                <span class="seller-order-empty-icon"><i class="bi bi-bag-check"></i></span>
                <h3>Belum ada pesanan</h3>
                <p>Pesanan dari customer akan muncul di sini.</p>
            </div>
        <?php endif; ?>

        <div class="seller-orders-list">
            <?php foreach ($orders as $o): ?>
                <?php $paymentStatus = (string)($o['payment_status'] ?? 'unpaid'); ?>
                <article class="glass-card seller-order-card seller-order-card-v2">
                    <div class="seller-order-card-top">
                        <div>
                            <span class="seller-order-kicker">Pesanan #<?= (int)$o['id'] ?></span>
                            <h3><?= e((string)$o['buyer_name']) ?></h3>
                            <div class="seller-order-contact">
                                <span><i class="bi bi-whatsapp"></i><?= e((string)$o['buyer_whatsapp']) ?></span>
                                <span><i class="bi bi-calendar3"></i><?= e((string)$o['created_at']) ?></span>
                            </div>
                        </div>
                        <div class="seller-order-card-badges">
                            <span class="order-status-badge status-<?= e((string)$o['status']) ?>"><?= e($statusLabels[$o['status']] ?? $o['status']) ?></span>
                            <span class="seller-payment-badge payment-<?= e($paymentStatus) ?>"><i class="bi bi-credit-card"></i><?= e($paymentLabels[$paymentStatus] ?? $paymentStatus) ?></span>
                        </div>
                    </div>

                    <?php if (!empty($o['note'])): ?>
                        <div class="seller-order-note-v2">
                            <i class="bi bi-sticky"></i>
                            <div><small>Catatan customer</small><p><?= nl2br(e((string)$o['note'])) ?></p></div>
                        </div>
                    <?php endif; ?>

                    <div class="seller-order-bottom">
                        <div class="seller-order-total-v2">
                            <small>Total pesanan</small>
                            <strong><?= format_price((int)$o['total']) ?></strong>
                            <?php if (!empty($o['payment_type'])): ?><span>Metode: <?= e((string)$o['payment_type']) ?></span><?php endif; ?>
                        </div>
                        <a class="btn btn-primary" href="<?= e(page_url('seller-order-detail', ['id' => $o['id']])) ?>">
                            Lihat detail <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
