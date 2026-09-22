<?php
require_once __DIR__.'/../includes/authorization.php';
require_role('admin');
require_once __DIR__.'/../includes/Repositories/UserRepository.php';

$u=(new UserRepository())->dashboardCounts();
$counts=[
    'products'=>(int)db()->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'available'=>(int)db()->query("SELECT COUNT(*) FROM products WHERE status='available'")->fetchColumn(),
    'orders'=>(int)db()->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'completed'=>(int)db()->query("SELECT COUNT(*) FROM orders WHERE status='completed'")->fetchColumn(),
    'pending'=>(int)db()->query("SELECT COUNT(*) FROM orders WHERE status IN ('requested','accepted')")->fetchColumn(),
    'cancelled'=>(int)db()->query("SELECT COUNT(*) FROM orders WHERE status='cancelled'")->fetchColumn(),
    'reviews'=>(int)db()->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
    'revenue'=>(int)db()->query("SELECT COALESCE(SUM(line_total),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.status='completed'")->fetchColumn()
];
$openComplaints=(int)db()->query("SELECT COUNT(*) FROM chat_conversations WHERE type='support' AND status='open'")->fetchColumn();
$pageTitle='Dashboard Admin';
require __DIR__.'/../includes/header.php';
?>
<section class="section-padding page-section admin-dashboard-page">
    <div class="container">
        <div class="page-toolbar"><?=back_link('home','Kembali ke beranda')?></div>
        <div class="dashboard-top admin-dashboard-hero">
            <div><span class="eyebrow">Administrator</span><h1>Pusat kontrol <em>sistem.</em></h1><p class="text-muted mb-0">Semua pengelolaan KosCycle dimulai dari sini. Pilih modul sesuai pekerjaan yang ingin dilakukan.</p></div>
            <div class="admin-hero-badge"><i class="bi bi-shield-check"></i><div><strong>Admin aktif</strong><small>Kontrol sistem penuh</small></div></div>
        </div>

        <div class="admin-section-title"><div><span class="eyebrow">Navigasi utama</span><h2>Kelola <em>platform.</em></h2></div><p>Setiap kartu membawa langsung ke modul terkait.</p></div>
        <div class="admin-shortcuts admin-shortcuts-enhanced">
            <a href="<?=e(page_url('admin-users'))?>" class="admin-shortcut glass-card"><i class="bi bi-people"></i><div><strong>Pengguna</strong><small>Buat akun, role, dan status</small></div><i class="bi bi-arrow-right arrow"></i></a>
            <a href="<?=e(page_url('admin-categories'))?>" class="admin-shortcut glass-card"><i class="bi bi-tags"></i><div><strong>Kategori</strong><small>Tambah dan arsipkan kategori</small></div><i class="bi bi-arrow-right arrow"></i></a>
            <a href="<?=e(page_url('admin-products'))?>" class="admin-shortcut glass-card"><i class="bi bi-box-seam"></i><div><strong>Produk</strong><small>Kelola katalog seluruh seller</small></div><i class="bi bi-arrow-right arrow"></i></a>
            <a href="<?=e(page_url('admin-orders'))?>" class="admin-shortcut glass-card"><i class="bi bi-receipt"></i><div><strong>Transaksi</strong><small>Pantau dan ubah status order</small></div><i class="bi bi-arrow-right arrow"></i></a>
            <a href="<?=e(page_url('admin-reviews'))?>" class="admin-shortcut glass-card"><i class="bi bi-chat-square-text"></i><div><strong>Ulasan</strong><small>Moderasi review customer</small></div><i class="bi bi-arrow-right arrow"></i></a>
            <a href="<?=e(page_url('chat'))?>" class="admin-shortcut glass-card"><i class="bi bi-life-preserver"></i><div><strong>Pengaduan</strong><small><?= $openComplaints ?> pengaduan masih terbuka</small></div><i class="bi bi-arrow-right arrow"></i></a>
            <a href="<?=e(page_url('admin-reports'))?>" class="admin-shortcut glass-card"><i class="bi bi-bar-chart"></i><div><strong>Laporan</strong><small>Ringkasan data aktual</small></div><i class="bi bi-arrow-right arrow"></i></a>
        </div>

        <div class="admin-section-title mt-5"><div><span class="eyebrow">Ringkasan</span><h2>Kondisi <em>platform.</em></h2></div><p>Angka di bawah diambil langsung dari database.</p></div>
        <div class="row g-3">
            <div class="col-6 col-lg-3"><div class="metric-card admin-metric"><span>Total pengguna</span><strong><?=$u['users']?></strong><small><?=$u['customers']?> customer · <?=$u['sellers']?> seller · <?=$u['admins']?> admin</small></div></div>
            <div class="col-6 col-lg-3"><div class="metric-card admin-metric"><span>Produk</span><strong><?=$counts['products']?></strong><small><?=$counts['available']?> sedang aktif di katalog</small></div></div>
            <div class="col-6 col-lg-3"><div class="metric-card admin-metric"><span>Pesanan</span><strong><?=$counts['orders']?></strong><small><?=$counts['pending']?> masih berjalan</small></div></div>
            <div class="col-6 col-lg-3"><div class="metric-card admin-metric"><span>Ulasan</span><strong><?=$counts['reviews']?></strong><small>Customer-generated review</small></div></div>
        </div>
        <div class="row g-4 mt-1">
            <div class="col-md-3"><div class="glass-card p-4 admin-summary-card"><span class="eyebrow">Selesai</span><h2><?=$counts['completed']?></h2><p>Order completed</p></div></div>
            <div class="col-md-3"><div class="glass-card p-4 admin-summary-card"><span class="eyebrow">Dibatalkan</span><h2><?=$counts['cancelled']?></h2><p>Order cancelled</p></div></div>
            <div class="col-md-3"><div class="glass-card p-4 admin-summary-card"><span class="eyebrow">Pengaduan</span><h2><?=$openComplaints?></h2><p>Masih terbuka</p></div></div>
            <div class="col-md-3"><div class="glass-card p-4 admin-summary-card"><span class="eyebrow">Nilai selesai</span><h2><?=format_price($counts['revenue'])?></h2><p>Akumulasi transaksi completed</p></div></div>
        </div>
    </div>
</section>
<?php require __DIR__.'/../includes/footer.php'; ?>
