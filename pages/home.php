<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../includes/Repositories/ProductRepository.php';
$productRepo = new ProductRepository();
$products = $productRepo->featured(6);
$pageTitle = 'Marketplace Reuseable untuk Anak Kos';
require __DIR__ . '/../includes/header.php';
?>
<section class="hero-section">
    <div class="container position-relative"><div class="hero-orbit orbit-one"></div><div class="hero-orbit orbit-two"></div>
        <div class="row align-items-center g-5">
            <div class="col-lg-7 hero-copy reveal"><span class="eyebrow"><i class="bi bi-stars"></i> Marketplace anak kos</span><h1>Barang baik,<br><em>cerita baru.</em></h1><p class="hero-lead">Temukan barang bekas layak pakai di sekitar kamu. Lebih hemat, lebih dekat, dan lebih ramah bumi.</p><div class="d-flex flex-wrap gap-3"><a href="#katalog" class="btn btn-primary btn-lg">Jelajahi katalog <i class="bi bi-arrow-down-right ms-2"></i></a><a href="#cara-pesan" class="btn btn-ghost btn-lg">Cara kerja <i class="bi bi-play-circle ms-2"></i></a></div></div>
            <div class="col-lg-5 reveal delay-1"><div class="hero-card glass-card"><div class="card-topline"><span class="live-dot"></span> Sedang berputar</div><div class="hero-object"><div class="object-glow"></div><i class="bi bi-house-heart-fill"></i></div><div class="hero-card-info"><div><span class="text-muted small">Pilihan dari database</span><h3>Barang bekas layak pakai</h3></div><span class="price-pill"><?= count($products) ?> baru</span></div><div class="mini-progress"><span></span></div><small class="text-muted">Katalog ditampilkan dari produk aktif.</small></div></div>
        </div>
    </div>
</section>
<section class="stats-strip"><div class="container"><div class="row g-4">
<?php
$totalProducts=(int)db()->query('SELECT COUNT(*) FROM products WHERE status <> "archived"')->fetchColumn();
$totalUsers=(int)db()->query('SELECT COUNT(*) FROM users WHERE status="active"')->fetchColumn();
$totalOrders=(int)db()->query('SELECT COUNT(*) FROM orders')->fetchColumn();
?>
<div class="col-4 stat-item"><strong><?= number_format($totalProducts) ?></strong><span>produk aktif</span></div>
<div class="col-4 stat-item"><strong><?= number_format($totalUsers) ?></strong><span>pengguna aktif</span></div>
<div class="col-4 stat-item"><strong><?= number_format($totalOrders) ?></strong><span>pesanan</span></div>
</div></div></section>
<section class="section-padding" id="katalog"><div class="container"><div class="section-heading d-flex justify-content-between align-items-end mb-4"><div><span class="eyebrow">Temukan barangmu</span><h2>Yang sedang <em>dicari.</em></h2></div><a class="text-link" href="<?= e(page_url('marketplace')) ?>">Lihat semua <i class="bi bi-arrow-up-right"></i></a></div>
<div class="row g-4"><?php foreach($products as $product): ?><div class="col-md-4"><article class="product-card glass-card"><div class="product-visual"><?php if($product['image_path']): ?><img src="<?= e(APP_URL.'/'.ltrim($product['image_path'],'/')) ?>" alt="<?= e($product['name']) ?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?><i class="bi bi-box-seam"></i><?php endif; ?><span class="product-tag"><?= e($product['category']) ?></span><span class="product-condition"><?= e($product['condition_label']) ?></span></div><div class="p-3"><div class="d-flex justify-content-between align-items-start"><div><h3><?= e($product['name']) ?></h3><small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($product['city']) ?></small></div></div><div class="d-flex justify-content-between align-items-center mt-3"><strong class="product-price"><?= format_price((int)$product['price']) ?></strong><a class="btn btn-sm btn-soft" href="<?= e(page_url('product-detail',['id'=>$product['id']])) ?>">Detail</a></div></div></article></div><?php endforeach; ?></div>
</div></section>
<section class="section-padding process-section" id="cara-pesan"><div class="container"><div class="row g-5 align-items-start"><div class="col-lg-5"><span class="eyebrow"><i class="bi bi-arrow-repeat"></i> Alur KosCycle</span><h2>Pesan dengan<br><em>lebih tenang.</em></h2><p class="text-muted">Dari menemukan barang sampai serah terima, proses dibuat sederhana.</p><a href="<?= e(current_user()?page_url('marketplace'):page_url('login')) ?>" class="btn btn-primary mt-3"><?= current_user()?'Cari barang sekarang':'Masuk untuk mulai' ?> <i class="bi bi-arrow-right ms-2"></i></a></div><div class="col-lg-7"><div class="process-list"><?php foreach([['01','Jelajahi katalog','Cari barang berdasarkan kata kunci, kategori, dan kota.','bi-search'],['02','Login atau daftar','Gunakan akun customer untuk mencatat transaksi.','bi-person-check'],['03','Masukkan keranjang','Pilih jumlah dan cek stok yang tersedia.','bi-bag-heart'],['04','Checkout','Buat pesanan dan tunggu seller memproses.','bi-hand-thumbs-up']] as $step):?><div class="process-step"><span class="process-number"><?=e($step[0])?></span><div class="process-content"><h3><?=e($step[1])?></h3><p><?=e($step[2])?></p></div><span class="process-icon"><i class="bi <?=e($step[3])?>"></i></span></div><?php endforeach;?></div></div></div></div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
