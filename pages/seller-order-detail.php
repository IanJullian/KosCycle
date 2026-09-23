<?php
require_once __DIR__ . '/../includes/authorization.php';
require_role('seller');
require_once __DIR__ . '/../includes/Repositories/OrderRepository.php';

$repo = new OrderRepository();
$sellerId = (int) $_SESSION['user_id'];
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId) {
    http_response_code(400);
    exit('Pesanan tidak valid.');
}

$transitions = [
    'requested' => ['accepted', 'cancelled'],
    'accepted' => ['completed', 'cancelled'],
    'completed' => [],
    'cancelled' => [],
];

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

$orders = $repo->sellerOrders($sellerId);
$order = null;
foreach ($orders as $candidate) {
    if ((int)$candidate['id'] === (int)$orderId) {
        $order = $candidate;
        break;
    }
}

$items = $order ? $repo->sellerOrderItems((int)$orderId, $sellerId) : [];

if (!$order || !$items) {
    http_response_code(404);
    exit('Pesanan tidak ditemukan atau bukan milik seller ini.');
}

$errors = [];

if (is_post()) {
    if (!verify_csrf()) {
        flash('error', 'Sesi formulir tidak valid.');
        redirect(page_url('seller-order-detail', ['id' => $orderId]));
    }

    $new = (string)($_POST['status'] ?? '');
    $current = (string)$order['status'];

    if (!isset($transitions[$current]) || !in_array($new, $transitions[$current], true)) {
        $errors[] = 'Perubahan status tidak valid.';
    }

    if (!$errors && $new === 'completed') {
        $paymentStatus = (string)($order['payment_status'] ?? 'unpaid');
        if ($paymentStatus !== 'paid') {
            $errors[] = 'Pesanan belum bisa diselesaikan karena pembayaran belum terkonfirmasi.';
        }
    }

    if (!$errors) {
        try {
            $repo->setStatusSeller((int)$orderId, $sellerId, $new);
            flash('success', 'Status pesanan diperbarui.');
            redirect(page_url('seller-order-detail', ['id' => $orderId]));
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$paymentStatus = (string)($order['payment_status'] ?? 'unpaid');
$availableTransitions = $transitions[$order['status']] ?? [];
if ($order['status'] === 'accepted' && $paymentStatus !== 'paid') {
    $availableTransitions = array_values(array_filter(
        $availableTransitions,
        static fn(string $status): bool => $status !== 'completed'
    ));
}

$pageTitle = 'Detail Pesanan #' . (int)$orderId;
require __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/order-review-polish.css">
<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/seller-orders-v2.css">

<section class="section-padding page-section seller-orders-page seller-orders-v2">
    <div class="container">
        <div class="page-toolbar">
            <?= back_link('seller-orders', 'Kembali ke daftar pesanan') ?>
        </div>

        <?php if ($message = flash('success')): ?>
            <div class="alert alert-success glass-alert mb-4"><?= e($message) ?></div>
        <?php endif; ?>

        <?php if ($message = flash('error')): ?>
            <div class="alert alert-danger glass-alert mb-4"><?= e($message) ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-danger glass-alert mb-4"><?= implode('<br>', array_map('e', $errors)) ?></div>
        <?php endif; ?>

        <article class="glass-card seller-order-card seller-order-card-v2">
            <div class="seller-order-card-top">
                <div>
                    <span class="seller-order-kicker">Detail pesanan #<?= (int)$orderId ?></span>
                    <h3><?= e((string)$order['buyer_name']) ?></h3>
                    <div class="seller-order-contact">
                        <span><i class="bi bi-whatsapp"></i><?= e((string)$order['buyer_whatsapp']) ?></span>
                        <span><i class="bi bi-calendar3"></i><?= e((string)$order['created_at']) ?></span>
                    </div>
                </div>
                <div class="seller-order-card-badges">
                    <span class="order-status-badge status-<?= e((string)$order['status']) ?>"><?= e($statusLabels[$order['status']] ?? $order['status']) ?></span>
                    <span class="seller-payment-badge payment-<?= e($paymentStatus) ?>"><i class="bi bi-credit-card"></i><?= e($paymentLabels[$paymentStatus] ?? $paymentStatus) ?></span>
                </div>
            </div>

            <?php if (!empty($order['note'])): ?>
                <div class="seller-order-note-v2">
                    <i class="bi bi-sticky"></i>
                    <div><small>Catatan customer</small><p><?= nl2br(e((string)$order['note'])) ?></p></div>
                </div>
            <?php endif; ?>

            <div class="seller-products-box">
                <div class="seller-products-box-head">
                    <span>Produk</span><strong><?= count($items) ?> item</strong>
                </div>

                <?php foreach ($items as $it): ?>
                    <div class="seller-product-line">
                        <div class="seller-product-line-icon"><i class="bi bi-box-seam"></i></div>
                        <div class="seller-product-line-copy">
                            <strong><?= e((string)$it['product_name']) ?></strong>
                            <span><?= (int)$it['quantity'] ?> × <?= format_price((int)$it['unit_price']) ?></span>
                        </div>
                        <strong class="seller-product-line-total"><?= format_price((int)$it['line_total']) ?></strong>
                    </div>

                    <?php if ($order['status'] === 'completed'): ?>
                        <div class="seller-review-v2 <?= $it['review_id'] ? 'has-review' : 'no-review' ?>">
                            <div class="seller-review-v2-head">
                                <div>
                                    <small><i class="bi bi-star me-1"></i>Ulasan customer</small>
                                    <strong><?= $it['review_id'] ? 'Ulasan sudah diterima' : 'Belum ada ulasan' ?></strong>
                                </div>
                                <?php if ($it['review_id']): ?>
                                    <div class="seller-review-score">
                                        <span class="seller-review-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi <?= $i <= (int)$it['review_rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                                            <?php endfor; ?>
                                        </span>
                                        <strong><?= (int)$it['review_rating'] ?>/5</strong>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($it['review_id'] && trim((string)$it['review_text']) !== ''): ?>
                                <p><?= nl2br(e((string)$it['review_text'])) ?></p>
                            <?php elseif ($it['review_id']): ?>
                                <p class="text-muted">Customer memberikan rating tanpa komentar.</p>
                            <?php else: ?>
                                <p class="text-muted">Ulasan akan tampil otomatis setelah customer mengirimkannya.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div class="seller-order-bottom">
                <div class="seller-order-total-v2">
                    <small>Total pesanan</small>
                    <strong><?= format_price((int)$order['total']) ?></strong>
                    <?php if (!empty($order['payment_type'])): ?><span>Metode: <?= e((string)$order['payment_type']) ?></span><?php endif; ?>
                </div>

                <?php if ($availableTransitions): ?>
                    <form class="seller-order-status-form" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <label class="seller-status-label" for="status-<?= (int)$orderId ?>">Ubah status</label>
                        <div class="seller-status-controls">
                            <select id="status-<?= (int)$orderId ?>" class="form-select" name="status" aria-label="Status baru">
                                <?php foreach ($availableTransitions as $s): ?>
                                    <option value="<?= e($s) ?>"><?= e($statusLabels[$s] ?? $s) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary" type="submit">Simpan</button>
                        </div>
                    </form>
                <?php elseif ($order['status'] === 'accepted' && $paymentStatus !== 'paid'): ?>
                    <div class="seller-wait-payment">
                        <i class="bi bi-hourglass-split"></i>
                        <span><strong>Menunggu pembayaran</strong><small>Pesanan dapat diselesaikan setelah pembayaran terkonfirmasi.</small></span>
                    </div>
                <?php elseif ($order['status'] === 'completed'): ?>
                    <span class="order-final-state"><i class="bi bi-check-circle-fill me-1"></i>Pesanan selesai</span>
                <?php else: ?>
                    <span class="order-final-state is-cancelled"><i class="bi bi-x-circle-fill me-1"></i>Pesanan dibatalkan</span>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2 flex-wrap mt-3">
                <a class="btn btn-soft" href="<?= e(page_url('chat')) ?>"><i class="bi bi-chat-dots me-1"></i>Buka chat</a>
                <a class="btn btn-outline-secondary" href="<?= e(page_url('chat', ['new' => 'support'])) ?>"><i class="bi bi-headset me-1"></i>Hubungi admin</a>
            </div>
        </article>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
