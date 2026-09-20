<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../includes/Repositories/ProductRepository.php';
require_once __DIR__ . '/../includes/Repositories/CartRepository.php';
require_once __DIR__ . '/../includes/Repositories/ReviewRepository.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); exit('Produk tidak ditemukan.'); }
$product = (new ProductRepository())->find((int)$id);
if (!$product) { http_response_code(404); exit('Produk tidak ditemukan.'); }
$reviews = (new ReviewRepository())->forProduct((int)$id);
$canReview = current_user() && current_role() === 'customer' && (new ReviewRepository())->canReview((int)$_SESSION['user_id'], (int)$id) && !(new ReviewRepository())->exists((int)$_SESSION['user_id'], (int)$id);
$message = null;
if (is_post()) {
    require_auth();
    if (current_role() !== 'customer') { http_response_code(403); exit('Akses ditolak.'); }
    if (!verify_csrf()) $message = 'Sesi formulir tidak valid.';
    else {
        $qty = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$qty) $message = 'Jumlah tidak valid.';
        elseif ($product['status'] !== 'available' || (int)$product['stock'] < $qty) $message = 'Stok tidak mencukupi atau produk tidak tersedia.';
        else {
            try {
                (new CartRepository())->add((int)$_SESSION['user_id'], (int)$id, (int)$qty);
                flash('success', 'Produk ditambahkan ke keranjang.');
                redirect(page_url('cart'));
            } catch (RuntimeException $e) {
                $message = $e->getMessage();
            }
        }
    }
}
$pageTitle = $product['name'];
require __DIR__ . '/../includes/header.php';
?>
<section class="section-padding page-section"><div class="container"><div class="page-toolbar"><?=back_link('marketplace','Kembali ke marketplace')?></div><div class="row g-5">
<div class="col-lg-6"><div class="glass-card overflow-hidden"><div class="product-visual" style="height:420px"><?php if($product['images']): ?><img src="<?= e(APP_URL . '/' . ltrim($product['images'][0]['image_path'],'/')) ?>" alt="<?= e($product['name']) ?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?><i class="bi bi-box-seam"></i><?php endif; ?></div></div></div>
<div class="col-lg-6"><span class="eyebrow"><?= e($product['category']) ?></span><h1 class="mt-3"><?= e($product['name']) ?></h1><p class="display-6"><?= format_price((int)$product['price']) ?></p><p class="text-muted"><?= nl2br(e($product['description'] ?? '')) ?></p>
<div class="row g-2 text-muted small mb-4"><div class="col-6">Kondisi: <strong><?= e($product['condition_label']) ?></strong></div><div class="col-6">Kota: <strong><?= e($product['city']) ?></strong></div><div class="col-6">Seller: <strong><?= e($product['seller_name']) ?></strong></div><div class="col-6">Stok: <strong><?= (int)$product['stock'] ?></strong></div></div>
<?php if($message): ?><div class="alert alert-danger"><?= e($message) ?></div><?php endif; ?>
<?php if(current_role()==='customer'): ?><form method="post" class="glass-card p-3"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="row g-2 align-items-end"><div class="col-4"><label class="form-label">Jumlah</label><input class="form-control" type="number" min="1" max="<?= (int)$product['stock'] ?>" name="quantity" value="1"></div><div class="col-8"><button class="btn btn-primary w-100" <?= ((int)$product['stock']<1 || $product['status']!=='available')?'disabled':'' ?>>Tambah ke keranjang</button></div></div></form>
<?php elseif(!current_user()): ?><a class="btn btn-primary" href="<?= e(page_url('login')) ?>">Masuk untuk memesan</a><?php endif; ?>
</div></div>
<div class="glass-card p-4 mt-5"><span class="eyebrow">Ulasan</span><h2 class="mt-2"><?= number_format((float)($product['avg_rating'] ?? 0),1) ?>/5</h2>
<?php if($canReview): ?><a class="btn btn-sm btn-soft" href="<?= e(page_url('review',['product_id'=>$product['id']])) ?>">Tulis ulasan</a><?php endif; ?>
<div class="mt-4"><?php foreach($reviews as $r): ?><div class="border-bottom py-3"><strong><?= e($r['full_name']) ?></strong><div>Rating: <?= (int)$r['rating'] ?>/5</div><p class="text-muted mb-0"><?= nl2br(e($r['review_text'])) ?></p></div><?php endforeach; ?></div></div>
</div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
