<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../includes/Repositories/ProductRepository.php';
require_once __DIR__ . '/../includes/Repositories/CategoryRepository.php';
$productRepo = new ProductRepository();
$categoryRepo = new CategoryRepository();
$q = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$city = trim($_GET['city'] ?? '');
$products = $productRepo->search($q, $category, $city);
$categories = $categoryRepo->active();
$pageTitle = 'Marketplace';
require __DIR__ . '/../includes/header.php';
?>
<section class="section-padding page-section"><div class="container"><?php if(current_user()): ?><div class="page-toolbar"><?=back_link(dashboard_page_for_role(current_role()),'Kembali ke dashboard')?></div><?php endif; ?>
<div class="section-heading mb-4"><span class="eyebrow">Marketplace</span><h2>Temukan <em>barangmu.</em></h2></div>
<form class="glass-card p-3 mb-4" method="get"><input type="hidden" name="page" value="marketplace"><div class="row g-2">
<div class="col-lg-5"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Cari nama, deskripsi, kategori, kota"></div>
<div class="col-lg-3"><select class="form-select" name="category"><option value="">Semua kategori</option><?php foreach($categories as $c): ?><option value="<?= e($c['name']) ?>" <?= $category===$c['name']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-lg-2"><input class="form-control" name="city" value="<?= e($city) ?>" placeholder="Kota"></div>
<div class="col-lg-2"><button class="btn btn-primary w-100">Cari</button></div></div></form>
<div class="row g-4"><?php if (!$products): ?><div class="col-12"><div class="glass-card p-5 text-center"><h3>Tidak ada produk</h3><p class="text-muted">Coba kata kunci atau kategori lain.</p></div></div><?php endif; ?>
<?php foreach($products as $p): ?><div class="col-md-6 col-lg-4"><article class="product-card glass-card">
<div class="product-visual"><?php if($p['image_path']): ?><img src="<?= e(APP_URL . '/' . ltrim($p['image_path'],'/')) ?>" alt="<?= e($p['name']) ?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?><i class="bi bi-box-seam"></i><?php endif; ?><span class="product-tag"><?= e($p['category']) ?></span><span class="product-condition"><?= e($p['condition_label']) ?></span></div>
<div class="p-3"><small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($p['city']) ?></small><h3><?= e($p['name']) ?></h3><div class="d-flex justify-content-between align-items-center"><strong class="product-price"><?= format_price((int)$p['price']) ?></strong><a class="btn btn-sm btn-soft" href="<?= e(page_url('product-detail',['id'=>$p['id']])) ?>">Detail</a></div></div>
</article></div><?php endforeach; ?></div></div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
