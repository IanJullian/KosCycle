<?php
require_once __DIR__ . '/../includes/authorization.php';
require_role('seller');
require_once __DIR__ . '/../includes/Repositories/ProductRepository.php';
require_once __DIR__ . '/../includes/Repositories/OrderRepository.php';

$uid = (int) $_SESSION['user_id'];
$products = (new ProductRepository())->bySeller($uid);
$orders = (new OrderRepository())->sellerOrders($uid);
$activeProducts = count(array_filter($products, fn($p) => in_array($p['status'], ['available', 'reserved'], true)));
$pending = count(array_filter($orders, fn($o) => in_array($o['status'], ['requested', 'accepted'], true)));
$completed = array_filter($orders, fn($o) => $o['status'] === 'completed');
$revenue = 0;
foreach ($completed as $o) $revenue += (int) $o['total'];

$pageTitle = 'Dashboard Seller';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/dashboard-v3.css">
<section class="section-padding page-section seller-dashboard-v3">
    <div class="container">
        <div class="page-toolbar"><?= back_link('home', 'Kembali ke beranda') ?></div>
        <div class="dashboard-top seller-dashboard-hero">
            <div><span class="eyebrow">Seller</span><h1>Dashboard <em>toko kamu.</em></h1><p class="text-muted mb-0">Kelola katalog, stok, pesanan, live chat customer, dan riwayat penjualan.</p></div>
            <div class="d-flex gap-2 flex-wrap"><a class="btn btn-primary" href="<?= e(page_url('seller-product-form')) ?>"><i class="bi bi-plus-lg me-1"></i>Tambah produk</a><a class="btn btn-ghost" href="<?= e(page_url('seller-products')) ?>">Kelola katalog</a></div>
        </div>

        <div class="seller-action-grid">
            <a class="dashboard-action glass-card" href="<?= e(page_url('seller-products')) ?>"><i class="bi bi-box-seam"></i><div><strong>Produk saya</strong><small><?= count($products) ?> produk · <?= $activeProducts ?> aktif</small></div><i class="bi bi-arrow-right arrow"></i></a>
            <a class="dashboard-action glass-card" href="<?= e(page_url('seller-orders')) ?>"><i class="bi bi-bag-check"></i><div><strong>Pesanan masuk</strong><small><?= $pending ?> masih perlu ditangani</small></div><i class="bi bi-arrow-right arrow"></i></a>
            <a data-no-preloader="1" class="dashboard-action glass-card" href="<?= e(page_url('chat')) ?>"><i class="bi bi-chat-dots"></i><div><strong>Live chat customer</strong><small>Pesan masuk otomatis tanpa refresh</small></div><i class="bi bi-arrow-right arrow"></i></a>
            <a class="dashboard-action glass-card" href="<?= e(page_url('seller-sales')) ?>"><i class="bi bi-graph-up"></i><div><strong>Riwayat penjualan</strong><small><?= count($completed) ?> order selesai</small></div><i class="bi bi-arrow-right arrow"></i></a>
        </div>

        <div class="row g-4 mt-2">
            <div class="col-6 col-lg-3"><div class="metric-card"><span>Total produk</span><strong><?= count($products) ?></strong></div></div>
            <div class="col-6 col-lg-3"><div class="metric-card"><span>Produk aktif</span><strong><?= $activeProducts ?></strong></div></div>
            <div class="col-6 col-lg-3"><div class="metric-card"><span>Order selesai</span><strong><?= count($completed) ?></strong></div></div>
            <div class="col-6 col-lg-3"><div class="metric-card"><span>Penjualan selesai</span><strong><?= format_price($revenue) ?></strong></div></div>
        </div>

        <div class="glass-card seller-help-card p-4 mt-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><span class="eyebrow">Bantuan seller</span><h2 class="h3 mt-1 mb-0">Butuh bantuan admin?</h2></div><a data-no-preloader="1" class="btn btn-primary" href="<?= e(page_url('chat', ['new' => 'support'])) ?>"><i class="bi bi-life-preserver me-1"></i>Hubungi admin</a></div>
            <p class="text-muted mb-0">Gunakan pengaduan untuk masalah akun, katalog, pesanan, atau kendala lain di KosCycle.</p>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
