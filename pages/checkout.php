<?php
require_once __DIR__ . '/../includes/authorization.php';
require_role('customer');
require_once __DIR__ . '/../includes/Repositories/CartRepository.php';
require_once __DIR__ . '/../includes/Services/CheckoutService.php';

$userId = (int) $_SESSION['user_id'];
$cartRepo = new CartRepository();
$items = $cartRepo->items($userId);
$total = 0;
foreach ($items as $i) $total += (int) $i['price'] * (int) $i['quantity'];
$errors = [];

if (!$items) {
    flash('error', 'Keranjang kosong.');
    redirect(page_url('cart'));
}

if (is_post()) {
    if (!verify_csrf()) $errors[] = 'Sesi formulir tidak valid.';
    $note = trim((string) ($_POST['note'] ?? ''));
    if (mb_strlen($note) > 2000) $errors[] = 'Catatan terlalu panjang.';

    if (!$errors) {
        try {
            $orderIds = (new CheckoutService())->checkout($userId, $note);

            // Buyer membayar lebih dulu. Satu seller -> langsung ke pembayaran.
            if (count($orderIds) === 1) {
                flash('success', 'Pesanan berhasil dibuat. Selesaikan pembayaran agar seller dapat mulai memproses.');
                redirect(page_url('payment', ['id' => (int) $orderIds[0]]));
            }

            // Multi-seller menghasilkan beberapa order terpisah.
            flash('success', 'Checkout berhasil. Bayar setiap pesanan agar masing-masing seller dapat memprosesnya.');
            redirect(page_url('orders'));
        } catch (Throwable $e) {
            $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Checkout gagal. Silakan coba lagi.';
        }
    }
}

$pageTitle = 'Checkout';
require __DIR__ . '/../includes/header.php';
?>
<section class="section-padding page-section">
    <div class="container">
        <div class="page-toolbar"><?= back_link('cart', 'Kembali ke keranjang') ?></div>
        <div class="section-heading mb-4">
            <span class="eyebrow">Checkout</span>
            <h2>Konfirmasi <em>pesanan.</em></h2>
            <p class="text-muted">Alur KosCycle: checkout → bayar → pembayaran terkonfirmasi → seller memproses → selesai.</p>
        </div>

        <?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-8">
                <?php foreach ($items as $i): ?>
                    <div class="glass-card p-3 mb-3 d-flex justify-content-between gap-3">
                        <span><?= e($i['name']) ?> × <?= (int) $i['quantity'] ?><small class="text-muted d-block">Seller: <?= e($i['seller_name']) ?></small></span>
                        <strong><?= format_price((int) $i['price'] * (int) $i['quantity']) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="col-lg-4">
                <div class="glass-card p-4">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <label class="form-label">Catatan untuk seller</label>
                        <textarea class="form-control" name="note" rows="4" maxlength="2000" placeholder="Contoh: tolong kabari sebelum pengiriman"></textarea>
                        <div class="d-flex justify-content-between mt-4"><span>Total</span><strong><?= format_price($total) ?></strong></div>
                        <button class="btn btn-primary w-100 mt-4" type="submit"><i class="bi bi-credit-card me-2"></i>Buat pesanan & lanjut bayar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
