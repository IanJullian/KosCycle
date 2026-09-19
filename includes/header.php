<?php
require_once __DIR__ . '/auth.php';
$user = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/app.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/refinements.css" rel="stylesheet">
</head>
<body>
<div class="site-preloader" id="site-preloader" role="status" aria-label="Memuat KosCycle">
    <div class="preloader-orbit" aria-hidden="true">
        <span class="preloader-orbit-ring"></span>
        <span class="preloader-orbit-dot"></span>
        <span class="preloader-orbit-core"><span class="brand-dot"></span></span>
    </div>
    <div class="preloader-wordmark">Kos<span>Cycle</span></div>
    <div class="preloader-track" aria-hidden="true"><span></span></div>
</div>
<div class="site-noise"></div>
<nav class="navbar navbar-expand-lg fixed-top glass-nav">
    <div class="container">
        <a class="navbar-brand brand-mark" href="<?= APP_URL ?>/"><span class="brand-dot"></span>Kos<span>Cycle</span></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><i class="bi bi-list"></i></button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav mx-auto gap-lg-3">
                <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/#katalog">Katalog</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/#cara-pesan">Cara pesan</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/#kontak">Kontak</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3 nav-actions">
                <?php if ($user): ?>
                    <a class="btn btn-sm btn-outline-light" href="<?= APP_URL ?>/?page=account"><i class="bi bi-person-circle me-1"></i><?= e(explode(' ', $user['full_name'])[0]) ?></a>
                    <a class="icon-button" href="<?= APP_URL ?>/?action=logout" aria-label="Keluar"><i class="bi bi-box-arrow-right"></i></a>
                <?php else: ?>
                    <a class="nav-link d-none d-sm-inline" href="<?= APP_URL ?>/?page=login">Masuk</a>
                    <a class="btn btn-primary btn-sm" href="<?= APP_URL ?>/?page=register">Daftar gratis <i class="bi bi-arrow-up-right ms-1"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<main>
<?php if ($message = flash('success')): ?><div class="container flash-wrap"><div class="alert alert-success glass-alert"><?= e($message) ?></div></div><?php endif; ?>
<?php if ($message = flash('error')): ?><div class="container flash-wrap"><div class="alert alert-danger glass-alert"><?= e($message) ?></div></div><?php endif; ?>
