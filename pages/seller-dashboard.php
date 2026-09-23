<?php
require_once __DIR__.'/../includes/authorization.php';
require_role('seller');
require_once __DIR__.'/../includes/Repositories/ProductRepository.php';
require_once __DIR__.'/../includes/Repositories/OrderRepository.php';

$uid=(int)$_SESSION['user_id'];
$productStats=(new ProductRepository())->sellerStats($uid);
$orderStats=(new OrderRepository())->sellerDashboardStats($uid);
$pageTitle='Dashboard Seller';require __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="<?=e(APP_URL)?>/assets/css/dashboard-v3.css">
<section class="section-padding page-section seller-dashboard-v3"><div class="container">
<div class="page-toolbar"><?=back_link('home','Kembali ke beranda')?></div>
<div class="dashboard-top seller-dashboard-hero"><div><span class="eyebrow">Seller</span><h1>Dashboard <em>toko kamu.</em></h1><p class="text-muted mb-0">Statistik dihitung langsung dari database agar selalu mengikuti perubahan stok, order, dan pembayaran.</p></div><div class="d-flex gap-2 flex-wrap"><a class="btn btn-primary" href="<?=e(page_url('seller-product-form'))?>">Tambah produk</a><a class="btn btn-ghost" href="<?=e(page_url('seller-products'))?>">Kelola katalog</a></div></div>

<div class="seller-action-grid">
<a class="dashboard-action glass-card" href="<?=e(page_url('seller-products'))?>"><i class="bi bi-box-seam"></i><div><strong>Produk saya</strong><small><?=$productStats['total']?> produk · <?=$productStats['active']?> aktif · stok <?=$productStats['stock']?></small></div><i class="bi bi-arrow-right arrow"></i></a>
<a class="dashboard-action glass-card" href="<?=e(page_url('seller-orders'))?>"><i class="bi bi-bag-check"></i><div><strong>Pesanan masuk</strong><small><?=$orderStats['ready']?> sudah dibayar & siap diproses</small></div><i class="bi bi-arrow-right arrow"></i></a>
<a data-no-preloader="1" class="dashboard-action glass-card" href="<?=e(page_url('chat'))?>"><i class="bi bi-chat-dots"></i><div><strong>Live chat</strong><small>Satu ruang per customer & produk</small></div><i class="bi bi-arrow-right arrow"></i></a>
<a class="dashboard-action glass-card" href="<?=e(page_url('seller-sales'))?>"><i class="bi bi-graph-up"></i><div><strong>Riwayat penjualan</strong><small><?=$orderStats['completed']?> order selesai</small></div><i class="bi bi-arrow-right arrow"></i></a>
</div>

<div class="row g-4 mt-2">
<div class="col-6 col-lg-3"><div class="metric-card"><span>Total produk</span><strong><?=$productStats['total']?></strong><small>Aktif <?=$productStats['active']?></small></div></div>
<div class="col-6 col-lg-3"><div class="metric-card"><span>Siap diproses</span><strong><?=$orderStats['ready']?></strong><small>Sudah dibayar</small></div></div>
<div class="col-6 col-lg-3"><div class="metric-card"><span>Sedang diproses</span><strong><?=$orderStats['processing']?></strong><small>Status accepted</small></div></div>
<div class="col-6 col-lg-3"><div class="metric-card"><span>Penjualan selesai</span><strong><?=format_price($orderStats['revenue'])?></strong><small>Completed + paid</small></div></div>
</div>
<div class="glass-card seller-help-card p-4 mt-4"><div class="d-flex justify-content-between align-items-center flex-wrap gap-2"><div><span class="eyebrow">Bantuan</span><h2 class="h4 mt-1 mb-0">Butuh admin?</h2></div><a data-no-preloader="1" class="btn btn-primary" href="<?=e(page_url('chat',['new'=>'support']))?>">Hubungi admin</a></div></div>
</div></section>
<?php require __DIR__.'/../includes/footer.php';?>
