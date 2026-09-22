<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

$user = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
$role = $user['role'] ?? null;
$dashboardPage = dashboard_page_for_role($role);
$chatUnread = 0;

if ($user) {
    require_once __DIR__ . '/Repositories/ChatRepository.php';
    try {
        $chatUnread = (new ChatRepository())->unreadCount((int) $user['id'], (string) $role);
    } catch (Throwable) {
        $chatUnread = 0;
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(APP_URL) ?>/assets/css/app.css" rel="stylesheet"><link href="<?= e(APP_URL) ?>/assets/css/refinements.css" rel="stylesheet"><link href="<?= e(APP_URL) ?>/assets/css/dashboard-fixes.css" rel="stylesheet"><link href="<?= e(APP_URL) ?>/assets/css/mobile-nav-fixes.css" rel="stylesheet">
</head>
<body>
<div class="site-preloader" id="site-preloader" role="status" aria-label="Memuat KosCycle"><div class="preloader-orbit" aria-hidden="true"><span class="preloader-orbit-ring"></span><span class="preloader-orbit-dot"></span><span class="preloader-orbit-core"><span class="brand-dot"></span></span></div><div class="preloader-wordmark">Kos<span>Cycle</span></div><div class="preloader-track" aria-hidden="true"><span></span></div></div>
<div class="site-noise"></div>
<nav class="navbar navbar-expand-lg fixed-top glass-nav"><div class="container">
<a class="navbar-brand brand-mark" href="<?= e(APP_URL) ?>/"><span class="brand-dot"></span>Kos<span>Cycle</span></a>
<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Buka navigasi"><i class="bi bi-list"></i></button>
<div class="collapse navbar-collapse" id="mainNav">
<ul class="navbar-nav mx-auto gap-lg-2 align-items-lg-center">
<li class="nav-item"><a class="nav-link" href="<?= e(APP_URL) ?>/">Beranda</a></li>
<?php if (!$role || in_array($role, ['customer','seller'], true)): ?><li class="nav-item"><a class="nav-link" href="<?= e(page_url('marketplace')) ?>">Katalog</a></li><?php endif; ?>
<?php if ($role): ?>
<li class="nav-item"><a class="nav-link nav-link-strong" href="<?= e(page_url($dashboardPage)) ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
<?php if ($role === 'customer'): ?>
<li class="nav-item"><a class="nav-link" href="<?= e(page_url('orders')) ?>">Pesanan</a></li>
<li class="nav-item"><a class="nav-link nav-chat-link" href="<?= e(page_url('chat')) ?>"><i class="bi bi-chat-dots me-1"></i>Chat<?php if ($chatUnread > 0): ?><span class="nav-unread-badge"><?= $chatUnread > 99 ? '99+' : $chatUnread ?></span><?php endif; ?></a></li>
<?php elseif ($role === 'seller'): ?>
<li class="nav-item"><a class="nav-link" href="<?= e(page_url('seller-products')) ?>">Produk saya</a></li>
<li class="nav-item"><a class="nav-link" href="<?= e(page_url('seller-orders')) ?>">Pesanan</a></li>
<li class="nav-item"><a class="nav-link nav-chat-link" href="<?= e(page_url('chat')) ?>"><i class="bi bi-chat-dots me-1"></i>Chat<?php if ($chatUnread > 0): ?><span class="nav-unread-badge"><?= $chatUnread > 99 ? '99+' : $chatUnread ?></span><?php endif; ?></a></li>
<?php else: ?>
<li class="nav-item dropdown"><a class="nav-link dropdown-toggle nav-link-strong" href="#" role="button" data-bs-toggle="dropdown">Kelola</a><ul class="dropdown-menu nav-manage-menu">
<li><a class="dropdown-item" href="<?= e(page_url('admin-users')) ?>"><i class="bi bi-people"></i>Pengguna</a></li><li><a class="dropdown-item" href="<?= e(page_url('admin-categories')) ?>"><i class="bi bi-tags"></i>Kategori</a></li><li><a class="dropdown-item" href="<?= e(page_url('admin-products')) ?>"><i class="bi bi-box-seam"></i>Produk</a></li><li><a class="dropdown-item" href="<?= e(page_url('admin-orders')) ?>"><i class="bi bi-receipt"></i>Transaksi</a></li><li><a class="dropdown-item" href="<?= e(page_url('admin-reviews')) ?>"><i class="bi bi-chat-square-text"></i>Ulasan</a></li><li><a class="dropdown-item" href="<?= e(page_url('admin-reports')) ?>"><i class="bi bi-bar-chart"></i>Laporan</a></li>
<li><a class="dropdown-item nav-chat-menu-item" href="<?= e(page_url('chat')) ?>"><i class="bi bi-life-preserver"></i>Pengaduan<?php if ($chatUnread > 0): ?><span class="nav-unread-badge ms-auto"><?= $chatUnread > 99 ? '99+' : $chatUnread ?></span><?php endif; ?></a></li>
</ul></li>
<?php endif; ?>
<?php endif; ?></ul>
<div class="d-flex align-items-center gap-2 nav-actions">
<?php if ($user): ?>
<div class="dropdown"><button class="user-nav-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-person-circle me-2"></i><span class="d-none d-sm-inline"><?= e(explode(' ', $user['full_name'])[0]) ?></span><i class="bi bi-chevron-down ms-1 small"></i></button><ul class="dropdown-menu dropdown-menu-end user-menu"><li><div class="user-menu-head"><strong><?= e($user['full_name']) ?></strong><small><?= e(ucfirst($role)) ?></small></div></li><li><a class="dropdown-item" href="<?= e(page_url('profile')) ?>"><i class="bi bi-person"></i>Profil saya</a></li><?php if ($role === 'customer'): ?><li><a class="dropdown-item" href="<?= e(page_url('cart')) ?>"><i class="bi bi-cart3"></i>Keranjang</a></li><?php elseif ($role === 'seller'): ?><li><a class="dropdown-item" href="<?= e(page_url('seller-sales')) ?>"><i class="bi bi-graph-up"></i>Riwayat penjualan</a></li><?php endif; ?><li><a class="dropdown-item" href="<?= e(page_url('chat', $role === 'admin' ? [] : ['new' => 'support'])) ?>"><i class="bi bi-chat-heart"></i><?= $role === 'admin' ? 'Pengaduan' : 'Bantuan & pengaduan' ?><?php if ($chatUnread > 0): ?><span class="nav-unread-badge ms-auto"><?= $chatUnread > 99 ? '99+' : $chatUnread ?></span><?php endif; ?></a></li><li><hr class="dropdown-divider"></li><li><form method="post" action="<?= e(APP_URL) ?>/?action=logout" class="m-0 px-2"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right"></i>Keluar</button></form></li></ul></div>
<?php else: ?><a class="nav-link d-none d-sm-inline" href="<?= e(page_url('login')) ?>">Masuk</a><a class="btn btn-primary btn-sm" href="<?= e(page_url('register')) ?>">Daftar gratis <i class="bi bi-arrow-up-right ms-1"></i></a><?php endif; ?>
</div>
</div></div></nav><main>
<?php if ($message = flash('success')): ?><div class="container flash-wrap"><div class="alert alert-success glass-alert"><?= e($message) ?></div></div><?php endif; ?>
<?php if ($message = flash('error')): ?><div class="container flash-wrap"><div class="alert alert-danger glass-alert"><?= e($message) ?></div></div><?php endif; ?>
