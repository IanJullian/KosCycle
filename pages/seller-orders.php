<?php
require_once __DIR__ . '/../includes/authorization.php';
require_role('seller');
require_once __DIR__ . '/../includes/Repositories/OrderRepository.php';

$repo = new OrderRepository();
$sellerId = (int) $_SESSION['user_id'];
$errors = [];

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

if (is_post()) {

    if (!verify_csrf()) {
        flash(
            'error',
            'Sesi formulir tidak valid.'
        );

        redirect(page_url('seller-orders'));
    }

    $orderId = filter_input(
        INPUT_POST,
        'order_id',
        FILTER_VALIDATE_INT
    );

    $new = (string) ($_POST['status'] ?? '');

    $items = $orderId
        ? $repo->sellerOrderItems(
            (int)$orderId,
            $sellerId
        )
        : [];

    $stmt = $orderId
        ? db()->prepare(
            'SELECT status
             FROM orders
             WHERE id=?
             LIMIT 1'
        )
        : null;

    if ($stmt) {
        $stmt->execute([$orderId]);
    }

    $current = $stmt
        ? $stmt->fetchColumn()
        : false;

    if (
        !$orderId
        || !$items
        || !isset($transitions[$current])
        || !in_array(
            $new,
            $transitions[$current],
            true
        )
    ) {
        $errors[] =
            'Perubahan status tidak valid atau pesanan bukan milik produkmu.';
    } else {
        try {
            $repo->setStatusSeller(
                (int)$orderId,
                $sellerId,
                $new
            );

            flash(
                'success',
                'Status pesanan diperbarui.'
            );

            redirect(page_url('seller-orders'));
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$orders = $repo->sellerOrders($sellerId);
$pageTitle = 'Pesanan Seller';

require __DIR__ . '/../includes/header.php';
?>

<link
    rel="stylesheet"
    href="<?= e(APP_URL) ?>/assets/css/order-review-polish.css"
>

<section class="section-padding page-section seller-orders-page">

    <div class="container">

        <div class="page-toolbar">
            <?= back_link(
                'seller-dashboard',
                'Kembali ke dashboard'
            ) ?>
        </div>

        <div class="section-heading mb-4">

            <span class="eyebrow">
                Seller
            </span>

            <h2>
                Kelola <em>pesanan.</em>
            </h2>

            <p class="text-muted">
                Perbarui status pesanan dan pantau pembayaran customer.
            </p>

        </div>

        <?php if ($message = flash('success')): ?>

            <div
                class="alert alert-success glass-alert mb-4"
            >
                <?= e($message) ?>
            </div>

        <?php endif; ?>

        <?php if ($message = flash('error')): ?>

            <div
                class="alert alert-danger glass-alert mb-4"
            >
                <?= e($message) ?>
            </div>

        <?php endif; ?>

        <?php if ($errors): ?>

            <div
                class="alert alert-danger glass-alert mb-4"
            >
                <?= implode(
                    '<br>',
                    array_map('e', $errors)
                ) ?>
            </div>

        <?php endif; ?>

        <?php if (!$orders): ?>

            <div class="glass-card seller-order-empty">

                <span class="seller-order-empty-icon">
                    <i class="bi bi-bag-check"></i>
                </span>

                <h3>
                    Belum ada pesanan
                </h3>

                <p>
                    Pesanan dari customer akan muncul di sini.
                </p>

            </div>

        <?php endif; ?>

        <?php foreach ($orders as $o): ?>

            <?php
            $items = $repo->sellerOrderItems(
                (int)$o['id'],
                $sellerId
            );
            ?>

            <article
                class="glass-card seller-order-card mb-4"
            >

                <div class="seller-order-head">

                    <div>

                        <div class="seller-order-kicker">
                            Pesanan #<?= (int)$o['id'] ?>
                        </div>

                        <h3>
                            <?= e(
                                (string)$o['buyer_name']
                            ) ?>
                        </h3>

                        <div class="seller-order-contact">

                            <i class="bi bi-whatsapp me-1"></i>

                            <?= e(
                                (string)
                                $o['buyer_whatsapp']
                            ) ?>

                            <span>·</span>

                            <?= e(
                                (string)
                                $o['created_at']
                            ) ?>

                        </div>

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

                <div class="order-note my-3">

                    <i class="bi bi-credit-card me-2"></i>

                    <span>

                        <strong>
                            Pembayaran
                        </strong>

                        <?= e(
                            $paymentLabels[
                                $o['payment_status']
                                ?? 'unpaid'
                            ]
                            ?? ($o['payment_status']
                                ?? 'Belum ada')
                        ) ?>

                        <?php if (!empty($o['payment_type'])): ?>

                            <small class="d-block text-muted">
                                Metode:
                                <?= e(
                                    (string)
                                    $o['payment_type']
                                ) ?>
                            </small>

                        <?php endif; ?>

                    </span>
                </div>

                <?php if (!empty($o['note'])): ?>

                    <div class="seller-order-note">

                        <i class="bi bi-sticky me-2"></i>

                        <span>

                            <strong>
                                Catatan customer
                            </strong>

                            <?= nl2br(
                                e(
                                    (string)
                                    $o['note']
                                )
                            ) ?>

                        </span>

                    </div>

                <?php endif; ?>

                <div class="seller-order-items">

                    <?php foreach ($items as $it): ?>

                        <div class="seller-order-item">

                            <div
                                class="seller-order-item-main"
                            >

                                <strong>
                                    <?= e(
                                        (string)
                                        $it['product_name']
                                    ) ?>
                                </strong>

                                <span>
                                    <?= (int)
                                        $it['quantity']
                                    ?>
                                    ×
                                    <?= format_price(
                                        (int)
                                        $it['unit_price']
                                    ) ?>
                                </span>

                            </div>

                            <strong>
                                <?= format_price(
                                    (int)
                                    $it['line_total']
                                ) ?>
                            </strong>

                        </div>

                        <?php if (
                            $o['status'] === 'completed'
                        ): ?>

                            <div
                                class="seller-item-review
                                    <?= $it['review_id']
                                        ? 'is-reviewed'
                                        : 'is-pending' ?>"
                            >

                                <div
                                    class="seller-item-review-head"
                                >

                                    <div>

                                        <small>
                                            <i
                                                class="bi bi-star me-1"
                                            ></i>
                                            Konfirmasi ulasan customer
                                        </small>

                                        <strong>
                                            <?= $it['review_id']
                                                ? 'Customer sudah memberikan ulasan'
                                                : 'Customer belum memberikan ulasan' ?>
                                        </strong>

                                    </div>

                                    <?php if ($it['review_id']): ?>

                                        <span
                                            class="seller-review-rating"
                                        >

                                            <span
                                                class="seller-review-stars"
                                            >

                                                <?php for (
                                                    $i=1;
                                                    $i<=5;
                                                    $i++
                                                ): ?>

                                                    <i
                                                        class="bi
                                                            <?= $i <= (int)$it['review_rating']
                                                                ? 'bi-star-fill'
                                                                : 'bi-star' ?>"
                                                    ></i>

                                                <?php endfor; ?>

                                            </span>

                                            <strong>
                                                <?= (int)
                                                    $it['review_rating']
                                                ?>/5
                                            </strong>

                                        </span>

                                    <?php endif; ?>

                                </div>

                                <?php if (
                                    $it['review_id']
                                    && trim(
                                        (string)
                                        $it['review_text']
                                    ) !== ''
                                ): ?>

                                    <p>
                                        <?= nl2br(
                                            e(
                                                (string)
                                                $it['review_text']
                                            )
                                        ) ?>
                                    </p>

                                <?php elseif (
                                    $it['review_id']
                                ): ?>

                                    <p class="text-muted">
                                        Customer memberikan
                                        rating tanpa komentar.
                                    </p>

                                <?php else: ?>

                                    <p class="text-muted">
                                        Rating akan tampil otomatis
                                        di sini setelah customer
                                        mengirim ulasan.
                                    </p>

                                <?php endif; ?>

                                <?php if (
                                    $it['review_id']
                                    && !empty(
                                        $it['review_created_at']
                                    )
                                ): ?>

                                    <small
                                        class="seller-review-date"
                                    >
                                        Diberikan
                                        <?= e(
                                            (string)
                                            $it['review_created_at']
                                        ) ?>
                                    </small>

                                <?php endif; ?>

                            </div>

                        <?php endif; ?>

                    <?php endforeach; ?>

                </div>

                <div class="seller-order-foot">

                    <div>

                        <small>
                            Total pesanan
                        </small>

                        <strong>
                            <?= format_price(
                                (int)$o['total']
                            ) ?>
                        </strong>

                    </div>

                    <?php if (
                        $transitions[$o['status']] ?? []
                    ): ?>

                        <form
                            class="seller-order-status-form"
                            method="post"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e(
                                    csrf_token()
                                ) ?>"
                            >

                            <input
                                type="hidden"
                                name="order_id"
                                value="<?= (int)$o['id'] ?>"
                            >

                            <select
                                class="form-select"
                                name="status"
                                aria-label="Status baru"
                            >

                                <?php foreach (
                                    $transitions[$o['status']]
                                    as $s
                                ): ?>

                                    <option
                                        value="<?= e($s) ?>"
                                    >
                                        <?= e(
                                            $statusLabels[$s]
                                            ?? $s
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <button
                                class="btn btn-primary"
                                type="submit"
                            >
                                Simpan status
                            </button>

                        </form>

                    <?php elseif (
                        $o['status'] === 'completed'
                    ): ?>

                        <span class="order-final-state">

                            <i
                                class="bi bi-check-circle-fill me-1"
                            ></i>

                            Pesanan selesai

                        </span>

                    <?php else: ?>

                        <span
                            class="order-final-state is-cancelled"
                        >

                            <i
                                class="bi bi-x-circle-fill me-1"
                            ></i>

                            Pesanan dibatalkan

                        </span>

                    <?php endif; ?>

                </div>

            </article>

        <?php endforeach; ?>

    </div>

</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
