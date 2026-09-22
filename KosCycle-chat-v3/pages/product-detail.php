<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../includes/Repositories/ProductRepository.php';
require_once __DIR__ . '/../includes/Repositories/CartRepository.php';
require_once __DIR__ . '/../includes/Repositories/ReviewRepository.php';
require_once __DIR__ . '/../includes/Repositories/ChatRepository.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    exit('Produk tidak ditemukan.');
}

$productRepo = new ProductRepository();
$product = $productRepo->find((int) $id);
if (!$product) {
    http_response_code(404);
    exit('Produk tidak ditemukan.');
}

$reviewRepo = new ReviewRepository();
$reviews = $reviewRepo->forProduct((int) $id);
$user = current_user();
$hasReview = $user && current_role() === 'customer'
    ? $reviewRepo->exists((int) $user['id'], (int) $id)
    : false;
$canReview = $user && current_role() === 'customer'
    ? !$hasReview && $reviewRepo->canReview((int) $user['id'], (int) $id)
    : false;

$message = null;

if (is_post()) {
    require_auth();

    if (!verify_csrf()) {
        $message = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $action = (string) ($_POST['detail_action'] ?? '');

        if ($action === 'chat_seller') {
            if (current_role() !== 'customer') {
                http_response_code(403);
                exit('Akses ditolak.');
            }

            try {
                $conversationId = (new ChatRepository())->startMarketplace(
                    (int) $_SESSION['user_id'],
                    (string) current_role(),
                    (int) $id
                );
                redirect(page_url('chat', ['id' => $conversationId]));
            } catch (Throwable $e) {
                $message = $e->getMessage();
            }
        } elseif ($action === 'add_cart') {
            if (current_role() !== 'customer') {
                http_response_code(403);
                exit('Akses ditolak.');
            }

            $qty = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$qty) {
                $message = 'Jumlah tidak valid.';
            } elseif ($product['status'] !== 'available' || (int) $product['stock'] < $qty) {
                $message = 'Stok tidak mencukupi atau produk tidak tersedia.';
            } else {
                try {
                    (new CartRepository())->add((int) $_SESSION['user_id'], (int) $id, (int) $qty);
                    flash('success', 'Produk ditambahkan ke keranjang.');
                    redirect(page_url('cart'));
                } catch (RuntimeException $e) {
                    $message = $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = $product['name'];
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/product-detail-polish.css">

<section class="section-padding page-section product-detail-page">
    <div class="container">
        <div class="page-toolbar"><?= back_link('marketplace', 'Kembali ke katalog') ?></div>

        <div class="row g-4 g-xl-5">
            <div class="col-lg-7">
                <div class="product-detail-gallery glass-card overflow-hidden">
                    <div class="product-detail-main-image">
                        <?php if (!empty($product['images'])): ?>
                            <img src="<?= e(APP_URL . '/' . ltrim((string) $product['images'][0]['image_path'], '/')) ?>" alt="<?= e((string) $product['name']) ?>">
                        <?php else: ?>
                            <div class="product-detail-placeholder"><i class="bi bi-box-seam"></i></div>
                        <?php endif; ?>
                        <span class="product-detail-category"><?= e((string) $product['category']) ?></span>
                    </div>
                </div>

                <div class="product-detail-seller glass-card mt-4">
                    <div class="product-detail-seller-icon"><i class="bi bi-shop"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <small>Seller</small>
                        <strong><?= e((string) $product['seller_name']) ?></strong>
                        <span><i class="bi bi-geo-alt me-1"></i><?= e((string) $product['city']) ?></span>
                    </div>
                    <?php if ($user && current_role() === 'customer'): ?>
                        <form method="post" class="m-0">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="detail_action" value="chat_seller">
                            <button class="btn btn-soft" type="submit"><i class="bi bi-chat-dots me-1"></i>Chat seller</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="product-detail-copy">
                    <div class="product-detail-topline">
                        <span class="eyebrow"><?= e((string) $product['condition_label']) ?></span>
                        <span class="product-detail-stock <?= (int) $product['stock'] > 0 ? 'is-available' : 'is-empty' ?>">
                            <?= (int) $product['stock'] > 0 ? 'Stok tersedia' : 'Stok habis' ?>
                        </span>
                    </div>
                    <h1><?= e((string) $product['name']) ?></h1>
                    <div class="product-detail-price"><?= format_price((int) $product['price']) ?></div>

                    <div class="product-detail-rating-row">
                        <span class="product-rating-stars" aria-label="Rating <?= number_format((float) $product['avg_rating'], 1) ?> dari 5">
                            <?php for ($i = 1; $i <= 5; $i++): ?><i class="bi <?= $i <= round((float) $product['avg_rating']) ? 'bi-star-fill' : 'bi-star' ?>"></i><?php endfor; ?>
                        </span>
                        <strong><?= number_format((float) ($product['avg_rating'] ?? 0), 1) ?></strong>
                        <span><?= (int) ($product['review_count'] ?? 0) ?> ulasan</span>
                    </div>

                    <p class="product-detail-description"><?= nl2br(e((string) ($product['description'] ?? 'Produk layak pakai untuk dipakai kembali.'))) ?></p>

                    <div class="product-detail-facts">
                        <div><i class="bi bi-box-seam"></i><span>Kondisi<strong><?= e((string) $product['condition_label']) ?></strong></span></div>
                        <div><i class="bi bi-geo-alt"></i><span>Lokasi<strong><?= e((string) $product['city']) ?></strong></span></div>
                        <div><i class="bi bi-stack"></i><span>Stok<strong><?= (int) $product['stock'] ?> item</strong></span></div>
                        <div><i class="bi bi-shop"></i><span>Penjual<strong><?= e((string) $product['seller_name']) ?></strong></span></div>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-danger mt-4"><?= e($message) ?></div>
                    <?php endif; ?>

                    <?php if (current_role() === 'customer'): ?>
                        <form method="post" class="product-detail-buy-box glass-card">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="detail_action" value="add_cart">
                            <div>
                                <label class="form-label">Jumlah</label>
                                <input class="form-control" type="number" min="1" max="<?= max(1, (int) $product['stock']) ?>" name="quantity" value="1" <?= (int) $product['stock'] < 1 || $product['status'] !== 'available' ? 'disabled' : '' ?>>
                            </div>
                            <button class="btn btn-primary" type="submit" <?= (int) $product['stock'] < 1 || $product['status'] !== 'available' ? 'disabled' : '' ?>><i class="bi bi-cart3 me-2"></i>Tambah ke keranjang</button>
                        </form>
                        <div class="product-detail-help-note"><i class="bi bi-chat-heart"></i><span>Masih ragu? <strong>Chat seller</strong> untuk menanyakan kondisi atau detail produk.</span></div>
                    <?php elseif (!$user): ?>
                        <div class="d-flex gap-2 flex-wrap mt-4"><a class="btn btn-primary" href="<?= e(page_url('login')) ?>">Masuk untuk membeli</a><a class="btn btn-ghost" href="<?= e(page_url('register-customer')) ?>">Buat akun customer</a></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="glass-card p-4 p-lg-5 mt-5 product-review-section" id="ulasan">
            <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
                <div>
                    <span class="eyebrow"><i class="bi bi-star me-1"></i>Pengalaman customer</span>
                    <h2 class="mt-2 mb-1">Ulasan <em>produk.</em></h2>
                    <p class="text-muted mb-0">Rating hanya dapat diberikan setelah pesanan produk selesai.</p>
                </div>
                <div class="product-review-summary">
                    <strong><?= number_format((float) ($product['avg_rating'] ?? 0), 1) ?></strong>
                    <span><?= (int) ($product['review_count'] ?? 0) ?> ulasan</span>
                </div>
            </div>

            <?php if ($canReview): ?>
                <div class="review-action-card">
                    <div><strong>Pesananmu sudah selesai.</strong><span>Bagikan pengalamanmu agar customer lain mendapat gambaran.</span></div>
                    <a class="btn btn-primary" href="<?= e(page_url('review', ['product_id' => $product['id']])) ?>"><i class="bi bi-star me-1"></i>Tulis ulasan</a>
                </div>
            <?php elseif ($hasReview): ?>
                <div class="review-status-card"><i class="bi bi-check-circle-fill"></i><span>Kamu sudah memberikan ulasan untuk produk ini.</span></div>
            <?php elseif (current_role() === 'customer'): ?>
                <div class="review-status-card is-muted"><i class="bi bi-clock-history"></i><span>Tombol ulasan akan aktif setelah seller menyelesaikan pesananmu.</span></div>
            <?php endif; ?>

            <div class="product-review-list">
                <?php if (!$reviews): ?>
                    <div class="product-review-empty"><i class="bi bi-chat-square-heart"></i><strong>Belum ada ulasan.</strong><span>Jadilah customer pertama yang berbagi pengalaman.</span></div>
                <?php else: ?>
                    <?php foreach ($reviews as $r): ?>
                        <article class="product-review-item">
                            <div class="product-review-avatar"><?= e(strtoupper(substr((string) $r['full_name'], 0, 1))) ?></div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex justify-content-between gap-2 flex-wrap">
                                    <div><strong><?= e((string) $r['full_name']) ?></strong><small><?= e((string) $r['created_at']) ?></small></div>
                                    <span class="product-review-stars"><?php for ($i = 1; $i <= 5; $i++): ?><i class="bi <?= $i <= (int) $r['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i><?php endfor; ?></span>
                                </div>
                                <p><?= $r['review_text'] !== null && $r['review_text'] !== '' ? nl2br(e((string) $r['review_text'])) : 'Customer memberikan rating tanpa komentar.' ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
