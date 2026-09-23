<?php
require_once __DIR__ . '/../includes/authorization.php';
require_role('customer');
require_once __DIR__ . '/../includes/Repositories/OrderRepository.php';

$repo = new OrderRepository();
$userId = (int) $_SESSION['user_id'];
$orders = $repo->byBuyer($userId);
$selected = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$details = $selected ? $repo->items((int) $selected, $userId) : [];

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

$pageTitle = 'Riwayat Pesanan';
require __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/order-review-polish.css">

<section class="section-padding page-section orders-page">
    <div class="container">

        <div class="page-toolbar">
            <?= back_link(
                'customer-dashboard',
                'Kembali ke dashboard customer'
            ) ?>
        </div>

        <div class="section-heading mb-4">
            <span class="eyebrow">Pesanan</span>
            <h2>Riwayat <em>transaksi.</em></h2>
            <p class="text-muted mb-0">
                Lihat status pesanan dan pembayaran.
            </p>
        </div>

        <div class="row g-4">

            <div class="col-lg-7">

                <?php if (!$orders): ?>

                    <div class="glass-card order-empty-card">
                        <span class="order-empty-icon">
                            <i class="bi bi-bag"></i>
                        </span>

                        <h3>Belum ada pesanan</h3>

                        <p>
                            Pesananmu akan muncul di sini
                            setelah checkout.
                        </p>

                        <a
                            class="btn btn-primary"
                            href="<?= e(
                                page_url('marketplace')
                            ) ?>"
                        >
                            Lihat katalog
                        </a>
                    </div>

                <?php endif; ?>

                <?php foreach ($orders as $o): ?>

                    <a
                        class="order-list-link"
                        href="<?= e(
                            page_url(
                                'orders',
                                ['id' => $o['id']]
                            )
                        ) ?>"
                    >
                        <div
                            class="glass-card order-list-card
                                <?= $selected
                                && (int)$selected === (int)$o['id']
                                ? 'is-selected'
                                : '' ?>"
                        >
                            <div>
                                <small>
                                    Pesanan #<?= (int)$o['id'] ?>
                                </small>

                                <strong>
                                    <?= format_price(
                                        (int)$o['total']
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e(
                                        (string)$o['created_at']
                                    ) ?>
                                    ·
                                    <?= (int)$o['item_count'] ?>
                                    item
                                </span>

                                <?php if (!empty($o['payment_status'])): ?>
                                    <span class="small mt-1">
                                        <i class="bi bi-credit-card me-1"></i>
                                        <?= e(
                                            $paymentLabels[
                                                $o['payment_status']
                                            ] ?? $o['payment_status']
                                        ) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <span
                                class="order-status-badge
                                    status-<?= e(
                                        (string)$o['status']
                                    ) ?>"
                            >
                                <?= e(
                                    $statusLabels[
                                        $o['status']
                                    ] ?? $o['status']
                                ) ?>
                            </span>
                        </div>
                    </a>

                <?php endforeach; ?>

            </div>

            <div class="col-lg-5">

                <?php if ($details): ?>

                    <?php
                    $paymentStatus =
                        (string)($details[0]['payment_status'] ?? '');

                    $paymentType =
                        (string)($details[0]['payment_type'] ?? '');

                    $orderTotal = (int) array_sum(
                        array_map(
                            static fn(array $row): int =>
                                (int)$row['line_total'],
                            $details
                        )
                    );
                    ?>

                    <div class="glass-card order-detail-card">

                        <div class="order-detail-head">
                            <div>
                                <span class="eyebrow">
                                    Detail #<?= (int)$selected ?>
                                </span>

                                <h3>
                                    <?= e(
                                        $statusLabels[
                                            $details[0]['status']
                                        ] ?? $details[0]['status']
                                    ) ?>
                                </h3>
                            </div>

                            <span class="order-detail-total">
                                <?= format_price($orderTotal) ?>
                            </span>
                        </div>

                        <div class="order-note mb-3">
                            <i class="bi bi-credit-card me-2"></i>

                            <span>
                                <strong>Status pembayaran</strong>

                                <?php if ($paymentStatus): ?>

                                    <?= e(
                                        $paymentLabels[
                                            $paymentStatus
                                        ] ?? $paymentStatus
                                    ) ?>

                                    <?php if ($paymentType): ?>
                                        <small class="d-block text-muted">
                                            Metode:
                                            <?= e($paymentType) ?>
                                        </small>
                                    <?php endif; ?>

                                <?php else: ?>

                                    Belum membuat pembayaran

                                <?php endif; ?>
                            </span>
                        </div>

                        <?php if ($details[0]['note']): ?>

                            <div class="order-note">
                                <i class="bi bi-sticky me-2"></i>

                                <span>
                                    <strong>Catatan</strong>

                                    <?= nl2br(
                                        e(
                                            (string)$details[0]['note']
                                        )
                                    ) ?>
                                </span>
                            </div>

                        <?php endif; ?>

                        <?php if (
                            $details[0]['status'] === 'accepted'
                            && $paymentStatus !== 'paid'
                        ): ?>

                            <a
                                class="btn btn-primary w-100 mt-3"
                                href="<?= e(
                                    page_url(
                                        'payment',
                                        ['id' => $selected]
                                    )
                                ) ?>"
                            >
                                <i class="bi bi-credit-card me-2"></i>
                                Bayar dengan Midtrans
                            </a>

                        <?php elseif (
                            $paymentStatus === 'paid'
                        ): ?>

                            <div class="alert alert-success mt-3 mb-0">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                Pembayaran berhasil dikonfirmasi.
                            </div>

                        <?php elseif (
                            $details[0]['status'] === 'requested'
                        ): ?>

                            <div class="alert alert-info mt-3 mb-0">
                                <i class="bi bi-info-circle me-2"></i>
                                Tombol pembayaran muncul setelah seller
                                menerima pesanan.
                            </div>

                        <?php endif; ?>

                        <div class="order-detail-items mt-4">

                            <?php foreach ($details as $d): ?>

                                <div class="order-detail-item">
                                    <div>
                                        <strong>
                                            <?= e(
                                                (string)
                                                $d['product_name']
                                            ) ?>
                                        </strong>

                                        <span>
                                            <?= (int)$d['quantity'] ?>
                                            ×
                                            <?= format_price(
                                                (int)$d['unit_price']
                                            ) ?>
                                        </span>

                                        <small>
                                            Seller:
                                            <?= e(
                                                (string)
                                                $d['seller_name']
                                            ) ?>
                                        </small>
                                    </div>

                                    <strong>
                                        <?= format_price(
                                            (int)$d['line_total']
                                        ) ?>
                                    </strong>
                                </div>

                                <?php if (
                                    $d['status'] === 'completed'
                                ): ?>

                                    <?php if ($d['review_id']): ?>

                                        <div
                                            class="order-reviewed-state"
                                        >
                                            <div>
                                                <span>
                                                    <i
                                                        class="bi bi-check-circle-fill me-1"
                                                    ></i>
                                                    Sudah diulas
                                                </span>

                                                <small>
                                                    Ulasanmu tersimpan
                                                </small>
                                            </div>

                                            <div
                                                class="order-stars"
                                                aria-label="Rating <?= (int)$d['review_rating'] ?> dari 5"
                                            >
                                                <?php for (
                                                    $i = 1;
                                                    $i <= 5;
                                                    $i++
                                                ): ?>

                                                    <i
                                                        class="bi
                                                            <?= $i <= (int)$d['review_rating']
                                                                ? 'bi-star-fill'
                                                                : 'bi-star' ?>"
                                                    ></i>

                                                <?php endfor; ?>

                                                <strong>
                                                    <?= (int)
                                                        $d['review_rating']
                                                    ?>/5
                                                </strong>
                                            </div>
                                        </div>

                                    <?php else: ?>

                                        <div
                                            class="order-review-action"
                                        >
                                            <div>
                                                <strong>
                                                    Pesanan selesai
                                                </strong>

                                                <small>
                                                    Bagikan pengalamanmu
                                                    untuk produk ini.
                                                </small>
                                            </div>

                                            <a
                                                class="btn btn-soft btn-sm"
                                                href="<?= e(
                                                    page_url(
                                                        'review',
                                                        [
                                                            'product_id' =>
                                                                $d['product_id']
                                                        ]
                                                    )
                                                ) ?>"
                                            >
                                                <i
                                                    class="bi bi-star me-1"
                                                ></i>
                                                Ulas
                                            </a>
                                        </div>

                                    <?php endif; ?>

                                <?php endif; ?>

                            <?php endforeach; ?>

                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
