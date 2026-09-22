<?php
require_once __DIR__ . '/../includes/authorization.php';
require_role('customer');
require_once __DIR__ . '/../includes/Repositories/ReviewRepository.php';
require_once __DIR__ . '/../includes/Repositories/ProductRepository.php';

$productId = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
if (!$productId) {
    http_response_code(400);
    exit('Produk tidak valid.');
}

$product = (new ProductRepository())->find((int) $productId);
if (!$product) {
    http_response_code(404);
    exit('Produk tidak ditemukan.');
}

$repo = new ReviewRepository();
$userId = (int) $_SESSION['user_id'];
$errors = [];

$alreadyReviewed = $repo->exists($userId, (int) $productId);
$eligible = $repo->canReview($userId, (int) $productId);

if ($alreadyReviewed || !$eligible) {
    $pageTitle = 'Ulasan belum tersedia';
    require __DIR__ . '/../includes/header.php';
    ?>
    <section class="auth-section review-page">
        <div class="container">
            <div class="glass-card review-not-ready">
                <span class="chat-hero-icon"><i class="bi <?= $alreadyReviewed ? 'bi-check-circle' : 'bi-clock-history' ?>"></i></span>
                <span class="eyebrow">Ulasan</span>
                <h1><?= $alreadyReviewed ? 'Ulasan sudah <em>dikirim.</em>' : 'Ulasan belum <em>aktif.</em>' ?></h1>
                <p><?= $alreadyReviewed ? 'Akunmu sudah memberikan ulasan untuk produk ini.' : 'Fitur ulasan aktif setelah seller menyelesaikan pesanan produk ini.' ?></p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a class="btn btn-primary" href="<?= e(page_url('product-detail', ['id' => $productId])) ?>#ulasan">Kembali ke produk</a>
                    <a class="btn btn-ghost" href="<?= e(page_url('orders')) ?>">Lihat pesanan</a>
                </div>
            </div>
        </div>
    </section>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$ratingValue = (int) ($_POST['rating'] ?? 5);
$reviewValue = (string) ($_POST['review_text'] ?? '');

if (is_post()) {
    if (!verify_csrf()) {
        $errors[] = 'Sesi formulir tidak valid.';
    }

    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    $review = trim((string) ($_POST['review_text'] ?? ''));

    if (!$rating || $rating < 1 || $rating > 5) {
        $errors[] = 'Pilih rating 1 sampai 5.';
    }
    if (mb_strlen($review) > 5000) {
        $errors[] = 'Ulasan maksimal 5000 karakter.';
    }

    if (!$errors) {
        try {
            $repo->create($userId, (int) $productId, (int) $rating, $review);
            flash('success', 'Ulasan berhasil dikirim. Terima kasih sudah berbagi pengalaman.');
            redirect(page_url('product-detail', ['id' => $productId, 'reviewed' => 1]) . '#ulasan');
        } catch (Throwable $e) {
            $errors[] = 'Ulasan gagal disimpan. Coba lagi.';
        }
    }
}

$pageTitle = 'Tulis Ulasan';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/review-polish.css">

<section class="auth-section review-page">
    <div class="auth-shell auth-shell-wide">
        <div class="auth-intro">
            <div class="page-toolbar"><a class="back-link" href="<?= e(page_url('product-detail', ['id' => $productId])) ?>#ulasan"><i class="bi bi-arrow-left me-2"></i>Kembali ke produk</a></div>
            <span class="eyebrow"><i class="bi bi-star me-1"></i>Pesanan selesai</span>
            <h1>Bagikan <em>pengalamanmu.</em></h1>
            <p>Rating dari customer membantu orang lain memahami kondisi barang sebelum membeli.</p>
            <div class="review-product-preview glass-card">
                <div class="review-product-icon"><i class="bi bi-box-seam"></i></div>
                <div><small>Produk yang kamu ulas</small><strong><?= e((string) $product['name']) ?></strong><span><?= e((string) $product['seller_name']) ?></span></div>
            </div>
        </div>

        <div class="auth-panel glass-card review-form-panel">
            <?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label class="form-label">Berapa ratingmu?</label>
                <div class="review-star-picker" role="radiogroup" aria-label="Pilih rating 1 sampai 5">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input id="rating-<?= $i ?>" type="radio" name="rating" value="<?= $i ?>" <?= $ratingValue === $i ? 'checked' : '' ?>>
                        <label for="rating-<?= $i ?>" title="<?= $i ?> dari 5"><i class="bi bi-star-fill"></i><span><?= $i ?></span></label>
                    <?php endfor; ?>
                </div>
                <div class="review-rating-caption" id="review-rating-caption"><?= $ratingValue ?>/5</div>

                <label class="form-label mt-4">Cerita singkat</label>
                <textarea class="form-control" name="review_text" rows="7" maxlength="5000" placeholder="Bagaimana kondisi barang dan pengalaman transaksinya?"><?= e($reviewValue) ?></textarea>
                <small class="review-field-hint">Komentar bersifat opsional.</small>

                <button class="btn btn-primary w-100 mt-4" type="submit">Kirim ulasan <i class="bi bi-send ms-2"></i></button>
            </form>
        </div>
    </div>
</section>

<script>
(() => {
    const inputs = document.querySelectorAll('.review-star-picker input');
    const caption = document.getElementById('review-rating-caption');
    const sync = () => {
        const active = document.querySelector('.review-star-picker input:checked');
        if (active && caption) caption.textContent = `${active.value}/5`;
    };
    inputs.forEach((input) => input.addEventListener('change', sync));
    sync();
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
