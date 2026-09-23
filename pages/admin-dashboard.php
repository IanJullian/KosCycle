<?php
require_once __DIR__.'/../includes/authorization.php';require_role('admin');require_once __DIR__.'/../includes/Repositories/UserRepository.php';
$u=(new UserRepository())->dashboardCounts();
$counts=['products'=>(int)db()->query('SELECT COUNT(*) FROM products')->fetchColumn(),'available'=>(int)db()->query("SELECT COUNT(*) FROM products p LEFT JOIN product_inventory i ON i.product_id=p.id WHERE p.status='available' AND COALESCE(i.quantity,0)>0")->fetchColumn(),'orders'=>(int)db()->query('SELECT COUNT(*) FROM orders')->fetchColumn(),'completed'=>(int)db()->query("SELECT COUNT(*) FROM orders WHERE status='completed'")->fetchColumn(),'pending'=>(int)db()->query("SELECT COUNT(*) FROM orders WHERE status IN ('requested','accepted')")->fetchColumn(),'reviews'=>(int)db()->query('SELECT COUNT(*) FROM reviews')->fetchColumn()];
$openComplaints=(int)db()->query("SELECT COUNT(*) FROM chat_conversations WHERE type='support' AND status='open'")->fetchColumn();
$pageTitle='Dashboard Admin';require __DIR__.'/../includes/header.php';
?>
<section class="section-padding page-section admin-dashboard-page"><div class="container">
<div class="page-toolbar"><?=back_link('home','Kembali ke beranda')?></div><div class="dashboard-top admin-dashboard-hero"><div><span class="eyebrow">Administrator</span><h1>Pusat kontrol <em>sistem.</em></h1><p class="text-muted">Navbar admin sekarang dibuat sederhana: Dashboard dan Chat. Modul administrasi dibuka dari kartu di halaman ini.</p></div></div>
<div class="admin-shortcuts admin-shortcuts-enhanced mt-4">
<a href="<?=e(page_url('admin-users'))?>" class="admin-shortcut glass-card"><i class="bi bi-people"></i><div><strong>Pengguna</strong><small>Akun, role, status</small></div><i class="bi bi-arrow-right arrow"></i></a>
<a href="<?=e(page_url('admin-categories'))?>" class="admin-shortcut glass-card"><i class="bi bi-tags"></i><div><strong>Kategori</strong><small>Kelola kategori</small></div><i class="bi bi-arrow-right arrow"></i></a>
<a href="<?=e(page_url('admin-products'))?>" class="admin-shortcut glass-card"><i class="bi bi-box-seam"></i><div><strong>Produk</strong><small>Pencarian, filter, pagination</small></div><i class="bi bi-arrow-right arrow"></i></a>
<a href="<?=e(page_url('admin-orders'))?>" class="admin-shortcut glass-card"><i class="bi bi-receipt"></i><div><strong>Transaksi</strong><small>Filter customer/seller</small></div><i class="bi bi-arrow-right arrow"></i></a>
<a data-no-preloader="1" href="<?=e(page_url('chat'))?>" class="admin-shortcut glass-card"><i class="bi bi-chat-dots"></i><div><strong>Chat</strong><small><?=$openComplaints?> pengaduan terbuka</small></div><i class="bi bi-arrow-right arrow"></i></a>
<a href="<?=e(page_url('admin-reviews'))?>" class="admin-shortcut glass-card"><i class="bi bi-star"></i><div><strong>Ulasan</strong><small>Moderasi ulasan</small></div><i class="bi bi-arrow-right arrow"></i></a>
<a href="<?=e(page_url('admin-reports'))?>" class="admin-shortcut glass-card"><i class="bi bi-bar-chart"></i><div><strong>Laporan</strong><small>Ringkasan platform</small></div><i class="bi bi-arrow-right arrow"></i></a>
</div>
<div class="row g-3 mt-4"><div class="col-6 col-lg-3"><div class="metric-card"><span>Pengguna</span><strong><?=$u['users']?></strong></div></div><div class="col-6 col-lg-3"><div class="metric-card"><span>Produk aktif</span><strong><?=$counts['available']?></strong></div></div><div class="col-6 col-lg-3"><div class="metric-card"><span>Pesanan</span><strong><?=$counts['orders']?></strong></div></div><div class="col-6 col-lg-3"><div class="metric-card"><span>Chat terbuka</span><strong><?=$openComplaints?></strong></div></div></div>
</div></section>
<?php require __DIR__.'/../includes/footer.php';?>
