<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../includes/Repositories/ProductRepository.php';

$productRepo = new ProductRepository();
$products = $productRepo->featured(6);
$productCount = count($products);

$pageTitle = 'Marketplace Reuseable untuk Anak Kos';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/home-v2.css?v=20260923-1">

<section class="hero-section">
    <div class="container position-relative">
        <div class="hero-orbit orbit-one"></div>
        <div class="hero-orbit orbit-two"></div>

        <div class="row align-items-center g-5">
            <div class="col-lg-7 hero-copy reveal">
                <span class="eyebrow">
                    <i class="bi bi-stars"></i>
                    Marketplace anak kos
                </span>

                <h1>Barang baik,<br><em>cerita baru.</em></h1>

                <p class="hero-lead">
                    Temukan barang bekas layak pakai di sekitar kamu.
                    Lebih hemat, lebih dekat, dan lebih ramah bumi.
                </p>

                <div class="d-flex flex-wrap gap-3">
                    <a href="#katalog" class="btn btn-primary btn-lg">
                        Jelajahi katalog
                        <i class="bi bi-arrow-down-right ms-2"></i>
                    </a>
                    <a href="#cara-pesan" class="btn btn-ghost btn-lg">
                        Cara kerja
                        <i class="bi bi-play-circle ms-2"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-5 reveal delay-1">
                <div class="hero-card glass-card">
                    <div class="card-topline">
                        <span class="live-dot"></span>
                        Sedang berputar
                    </div>

                    <div class="hero-object">
                        <div class="object-glow"></div>
                        <i class="bi bi-house-heart-fill"></i>
                    </div>

                    <div class="hero-card-info">
                        <div>
                            <span class="text-muted small">Pilihan dari database</span>
                            <h3>Barang bekas layak pakai</h3>
                        </div>
                        <span class="price-pill"><?= $productCount ?> baru</span>
                    </div>

                    <div class="mini-progress"><span></span></div>
                    <small class="text-muted">Katalog ditampilkan dari produk aktif.</small>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="stats-strip">
    <div class="container">
        <div class="row g-4">
            <?php
            $totalProducts = (int) db()->query(
                'SELECT COUNT(*) FROM products WHERE status <> "archived"'
            )->fetchColumn();

            $totalUsers = (int) db()->query(
                'SELECT COUNT(*) FROM users WHERE status = "active"'
            )->fetchColumn();

            $totalOrders = (int) db()->query(
                'SELECT COUNT(*) FROM orders'
            )->fetchColumn();
            ?>

            <div class="col-4 stat-item">
                <strong><?= number_format($totalProducts) ?></strong>
                <span>produk aktif</span>
            </div>
            <div class="col-4 stat-item">
                <strong><?= number_format($totalUsers) ?></strong>
                <span>pengguna aktif</span>
            </div>
            <div class="col-4 stat-item">
                <strong><?= number_format($totalOrders) ?></strong>
                <span>pesanan</span>
            </div>
        </div>
    </div>
</section>

<section class="section-padding home-catalog-section" id="katalog">
    <div class="container">
        <div class="home-catalog-heading">
            <div>
                <span class="eyebrow">Temukan barangmu</span>
                <h2>Yang sedang <em>dicari.</em></h2>
                <p>
                    Pilihan terbaru dari seller KosCycle dengan stok yang masih tersedia.
                </p>
            </div>

            <a class="home-see-all" href="<?= e(page_url('marketplace')) ?>">
                Lihat semua
                <i class="bi bi-arrow-up-right"></i>
            </a>
        </div>

        <?php if (!$products): ?>
            <div class="glass-card home-product-empty">
                <span><i class="bi bi-box-seam"></i></span>
                <div>
                    <strong>Belum ada produk aktif.</strong>
                    <small>Produk seller dengan stok tersedia akan muncul di bagian ini.</small>
                </div>
                <a class="btn btn-primary" href="<?= e(page_url('marketplace')) ?>">
                    Buka katalog
                </a>
            </div>
        <?php else: ?>
            <div class="home-product-grid home-product-count-<?= min($productCount, 3) ?>">
                <?php foreach ($products as $product): ?>
                    <article class="home-product-card glass-card">
                        <a
                            class="home-product-media"
                            href="<?= e(page_url('product-detail', ['id' => $product['id']])) ?>"
                            aria-label="Lihat <?= e((string) $product['name']) ?>"
                        >
                            <?php if (!empty($product['image_path'])): ?>
                                <img
                                    src="<?= e(APP_URL . '/' . ltrim((string) $product['image_path'], '/')) ?>"
                                    alt="<?= e((string) $product['name']) ?>"
                                    loading="lazy"
                                >
                            <?php else: ?>
                                <span class="home-product-placeholder">
                                    <i class="bi bi-box-seam"></i>
                                </span>
                            <?php endif; ?>

                            <span class="home-product-category">
                                <?= e((string) $product['category']) ?>
                            </span>

                            <span class="home-product-condition">
                                <?= e((string) $product['condition_label']) ?>
                            </span>
                        </a>

                        <div class="home-product-body">
                            <div class="home-product-meta">
                                <span>
                                    <i class="bi bi-geo-alt"></i>
                                    <?= e((string) $product['city']) ?>
                                </span>
                                <span>
                                    <i class="bi bi-stack"></i>
                                    Stok <?= (int) $product['stock'] ?>
                                </span>
                            </div>

                            <a
                                class="home-product-title"
                                href="<?= e(page_url('product-detail', ['id' => $product['id']])) ?>"
                            >
                                <?= e((string) $product['name']) ?>
                            </a>

                            <div class="home-product-seller">
                                <i class="bi bi-shop"></i>
                                <?= e((string) $product['seller_name']) ?>
                            </div>

                            <div class="home-product-footer">
                                <div>
                                    <small>Harga</small>
                                    <strong><?= format_price((int) $product['price']) ?></strong>
                                </div>

                                <a
                                    class="btn btn-soft btn-sm"
                                    href="<?= e(page_url('product-detail', ['id' => $product['id']])) ?>"
                                >
                                    Lihat detail
                                    <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section-padding process-section" id="cara-pesan">
    <div class="container">
        <div class="row g-5 align-items-start">
            <div class="col-lg-5">
                <span class="eyebrow">
                    <i class="bi bi-arrow-repeat"></i>
                    Alur KosCycle
                </span>

                <h2>Belanja dengan<br><em>lebih jelas.</em></h2>

                <p class="text-muted">
                    Dari menemukan barang hingga seller memproses pesanan,
                    alurnya dibuat singkat dan status pembayaran terlihat jelas.
                </p>

                <a
                    href="<?= e(current_user() ? page_url('marketplace') : page_url('login')) ?>"
                    class="btn btn-primary mt-3"
                >
                    <?= current_user() ? 'Cari barang sekarang' : 'Masuk untuk mulai' ?>
                    <i class="bi bi-arrow-right ms-2"></i>
                </a>
            </div>

            <div class="col-lg-7">
                <div class="process-list">
                    <?php
                    $steps = [
                        ['01', 'Jelajahi katalog', 'Cari barang berdasarkan kata kunci, kategori, dan kota.', 'bi-search'],
                        ['02', 'Masuk atau daftar', 'Gunakan akun customer supaya keranjang dan transaksi tercatat.', 'bi-person-check'],
                        ['03', 'Masukkan ke keranjang', 'Pilih jumlah sesuai stok lalu periksa kembali pesananmu.', 'bi-bag-heart'],
                        ['04', 'Checkout & bayar', 'Buat pesanan, selesaikan pembayaran, lalu seller dapat memprosesnya.', 'bi-credit-card'],
                    ];
                    ?>

                    <?php foreach ($steps as $step): ?>
                        <div class="process-step">
                            <span class="process-number"><?= e($step[0]) ?></span>
                            <div class="process-content">
                                <h3><?= e($step[1]) ?></h3>
                                <p><?= e($step[2]) ?></p>
                            </div>
                            <span class="process-icon">
                                <i class="bi <?= e($step[3]) ?>"></i>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
