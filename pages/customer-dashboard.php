<?php
require_once __DIR__ . '/../includes/authorization.php';
require_role('customer');
require_once __DIR__ . '/../includes/Repositories/OrderRepository.php';
require_once __DIR__ . '/../includes/Repositories/ProductRepository.php';

$uid = (int) $_SESSION['user_id'];
$orders = (new OrderRepository())->byBuyer($uid);
$products = (new ProductRepository())->featured(4);
$open = count(array_filter($orders, fn($o) => in_array($o['status'], ['requested', 'accepted'], true)));
$completed = count(array_filter($orders, fn($o) => $o['status'] === 'completed'));
$cancelled = count(array_filter($orders, fn($o) => $o['status'] === 'cancelled'));

$pageTitle = 'Dashboard Customer';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/customer-dashboard-polish.css">
<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/dashboard-v3.css">

<section class="section-padding page-section customer-dashboard-page">
    <div class="container">
        <div class="page-toolbar"><?= back_link('home', 'Kembali ke beranda') ?></div>

        <div class="dashboard-top">
            <div>
                <span class="eyebrow">Customer</span>
                <h1>Dashboard <em>kamu.</em></h1>
                <p class="text-muted">Pantau pesanan, lanjutkan percakapan, dan temukan barang yang masih kamu cari.</p>
            </div>
            <div class="customer-hero-actions">
                <a class="btn btn-primary" href="<?= e(page_url('marketplace')) ?>"><i class="bi bi-search me-2"></i>Cari barang</a>
                <a class="btn btn-ghost" data-no-preloader="1" href="<?= e(page_url('chat', ['new' => 'support'])) ?>"><i class="bi bi-life-preserver me-1"></i>Bantuan</a>
            </div>
        </div>

        <div class="dashboard-grid customer-actions-grid">
            <a href="<?= e(page_url('orders')) ?>" class="dashboard-action glass-card"><i class="bi bi-bag-check"></i><div><strong><?= count($orders) ?> pesanan</strong><small><?= $open ?> berjalan · <?= $completed ?> selesai<?php if ($cancelled): ?> · <?= $cancelled ?> dibatalkan<?php endif; ?></small></div><i class="bi bi-arrow-right arrow"></i></a>
            <a href="<?= e(page_url('cart')) ?>" class="dashboard-action glass-card"><i class="bi bi-cart3"></i><div><strong>Keranjang</strong><small>Periksa item sebelum checkout</small></div><i class="bi bi-arrow-right arrow"></i></a>
            <a data-no-preloader="1" href="<?= e(page_url('chat')) ?>" class="dashboard-action glass-card"><i class="bi bi-chat-dots"></i><div><strong>Live chat & pengaduan</strong><small>Hubungi seller atau admin tanpa refresh</small></div><i class="bi bi-arrow-right arrow"></i></a>
            <a href="<?= e(page_url('profile')) ?>" class="dashboard-action glass-card"><i class="bi bi-person"></i><div><strong>Profil saya</strong><small>Kelola data akun</small></div><i class="bi bi-arrow-right arrow"></i></a>
        </div>

        <div class="row g-3 mt-2">
            <div class="col-6 col-lg-3"><div class="metric-card customer-metric"><span>Total pesanan</span><strong><?= count($orders) ?></strong><small>Semua transaksi</small></div></div>
            <div class="col-6 col-lg-3"><div class="metric-card customer-metric"><span>Sedang berjalan</span><strong><?= $open ?></strong><small>Perlu dipantau</small></div></div>
            <div class="col-6 col-lg-3"><div class="metric-card customer-metric"><span>Selesai</span><strong><?= $completed ?></strong><small>Bisa diberi ulasan</small></div></div>
            <div class="col-6 col-lg-3"><div class="metric-card customer-metric"><span>Dibatalkan</span><strong><?= $cancelled ?></strong><small>Riwayat pembatalan</small></div></div>
        </div>

        <div class="customer-products-panel glass-card mt-4">
            <div class="customer-products-head">
                <div><span class="eyebrow">Rekomendasi</span><h2>Barang terbaru <em>buat kamu.</em></h2><p>Pilihan produk aktif dengan stok tersedia.</p></div>
                <a class="text-link" href="<?= e(page_url('marketplace')) ?>">Lihat semua <i class="bi bi-arrow-up-right"></i></a>
            </div>

            <div class="customer-product-grid">
                <?php foreach ($products as $p): ?>
                    <article class="customer-product-card">
                        <a class="customer-product-media" href="<?= e(page_url('product-detail', ['id' => $p['id']])) ?>">
                            <?php if (!empty($p['image_path'])): ?><img src="<?= e(APP_URL . '/' . ltrim((string) $p['image_path'], '/')) ?>" alt="<?= e((string) $p['name']) ?>"><?php else: ?><span class="customer-product-placeholder"><i class="bi bi-box-seam"></i></span><?php endif; ?>
                            <span class="customer-product-badge"><?= e((string) $p['category']) ?></span><span class="customer-product-condition"><?= e((string) $p['condition_label']) ?></span>
                        </a>
                        <div class="customer-product-body">
                            <div class="customer-product-location"><i class="bi bi-geo-alt"></i><?= e((string) $p['city']) ?></div>
                            <a class="customer-product-title" href="<?= e(page_url('product-detail', ['id' => $p['id']])) ?>"><?= e((string) $p['name']) ?></a>
                            <div class="customer-product-seller"><i class="bi bi-shop"></i><?= e((string) $p['seller_name']) ?></div>
                            <div class="customer-product-bottom"><div><small>Mulai dari</small><strong><?= format_price((int) $p['price']) ?></strong></div><a class="btn btn-soft btn-sm" href="<?= e(page_url('product-detail', ['id' => $p['id']])) ?>">Lihat</a></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
