<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../includes/Repositories/ProductRepository.php';
require_once __DIR__ . '/../includes/Repositories/CategoryRepository.php';

$productRepo = new ProductRepository();
$categoryRepo = new CategoryRepository();

$q = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$city = trim((string) ($_GET['city'] ?? ''));
$sort = trim((string) ($_GET['sort'] ?? 'latest'));
if (!in_array($sort, ['latest', 'price_low', 'price_high', 'rating'], true)) {
    $sort = 'latest';
}

$products = $productRepo->search($q, $category, $city, 60, $sort);
$categories = $categoryRepo->active();
$hasFilter = $q !== '' || $category !== '' || $city !== '';
$pageTitle = 'Marketplace';

require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/catalog-polish.css">

<section class="section-padding page-section marketplace-page">
    <div class="container">
        <?php if (current_user()): ?>
            <div class="page-toolbar"><?= back_link(dashboard_page_for_role(current_role()), 'Kembali ke dashboard') ?></div>
        <?php endif; ?>

        <div class="marketplace-hero glass-card">
            <div>
                <span class="eyebrow"><i class="bi bi-shop me-1"></i>Marketplace KosCycle</span>
                <h1>Temukan barang yang<br><em>masih berarti.</em></h1>
                <p>Barang bekas layak pakai dari seller di sekitar kamu. Cari lebih cepat, lihat detail lebih jelas, lalu chat seller sebelum membeli.</p>
            </div>
            <div class="marketplace-hero-mark" aria-hidden="true">
                <span></span><i class="bi bi-arrow-repeat"></i>
            </div>
        </div>

        <div class="marketplace-toolbar glass-card">
            <form method="get" class="catalog-search-form" id="catalog-filter-form">
                <input type="hidden" name="page" value="marketplace">
                <div class="catalog-search-main">
                    <i class="bi bi-search"></i>
                    <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Cari barang, toko, kategori, atau kota">
                </div>
                <button class="btn btn-primary catalog-search-button" type="submit">Cari</button>

                <div class="catalog-filter-row">
                    <div class="catalog-filter-group">
                        <label for="category" class="catalog-filter-label">Kategori</label>
                        <select id="category" class="form-select" name="category">
                            <option value="">Semua kategori</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= e((string) $c['name']) ?>" <?= $category === $c['name'] ? 'selected' : '' ?>><?= e((string) $c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="catalog-filter-group">
                        <label for="city" class="catalog-filter-label">Kota</label>
                        <input id="city" class="form-control" name="city" value="<?= e($city) ?>" placeholder="Contoh: Purwokerto">
                    </div>
                    <div class="catalog-filter-group catalog-sort-group">
                        <label for="sort" class="catalog-filter-label">Urutkan</label>
                        <select id="sort" class="form-select" name="sort">
                            <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Terbaru</option>
                            <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Harga terendah</option>
                            <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Harga tertinggi</option>
                            <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Rating tertinggi</option>
                        </select>
                    </div>
                    <a class="catalog-reset <?= $hasFilter ? '' : 'is-disabled' ?>" href="<?= e(page_url('marketplace')) ?>"><i class="bi bi-arrow-counterclockwise"></i>Reset</a>
                </div>
            </form>
        </div>

        <?php if ($hasFilter): ?>
            <div class="catalog-active-filters">
                <span class="catalog-results-count"><strong><?= number_format(count($products)) ?></strong> barang ditemukan</span>
                <?php if ($q !== ''): ?><span class="catalog-chip">“<?= e($q) ?>”</span><?php endif; ?>
                <?php if ($category !== ''): ?><span class="catalog-chip"><?= e($category) ?></span><?php endif; ?>
                <?php if ($city !== ''): ?><span class="catalog-chip"><?= e($city) ?></span><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="catalog-results-head">
                <div><span class="eyebrow">Katalog aktif</span><h2>Barang <em>terbaru.</em></h2></div>
                <span class="catalog-results-count"><strong><?= number_format(count($products)) ?></strong> barang tersedia</span>
            </div>
        <?php endif; ?>

        <div class="row g-4 catalog-grid">
            <?php if (!$products): ?>
                <div class="col-12">
                    <div class="glass-card catalog-empty-state">
                        <span><i class="bi bi-search"></i></span>
                        <h3>Belum menemukan barang yang cocok.</h3>
                        <p>Coba kata kunci lain, pilih kategori berbeda, atau kosongkan filter.</p>
                        <a class="btn btn-primary" href="<?= e(page_url('marketplace')) ?>">Tampilkan semua katalog</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ($products as $p): ?>
                <div class="col-md-6 col-xl-4">
                    <article class="catalog-card glass-card">
                        <a class="catalog-card-media" href="<?= e(page_url('product-detail', ['id' => $p['id']])) ?>">
                            <?php if (!empty($p['image_path'])): ?>
                                <img src="<?= e(APP_URL . '/' . ltrim((string) $p['image_path'], '/')) ?>" alt="<?= e((string) $p['name']) ?>">
                            <?php else: ?>
                                <div class="catalog-placeholder"><i class="bi bi-box-seam"></i></div>
                            <?php endif; ?>
                            <span class="catalog-category-pill"><?= e((string) $p['category']) ?></span>
                            <span class="catalog-condition-pill"><?= e((string) $p['condition_label']) ?></span>
                        </a>

                        <div class="catalog-card-body">
                            <div class="catalog-location"><i class="bi bi-geo-alt"></i><?= e((string) $p['city']) ?></div>
                            <a class="catalog-card-title" href="<?= e(page_url('product-detail', ['id' => $p['id']])) ?>"><?= e((string) $p['name']) ?></a>
                            <div class="catalog-seller-line"><i class="bi bi-shop"></i><?= e((string) $p['seller_name']) ?></div>

                            <div class="catalog-meta-row">
                                <span class="catalog-rating"><i class="bi bi-star-fill"></i><?= number_format((float) $p['avg_rating'], 1) ?> <small>(<?= (int) $p['review_count'] ?>)</small></span>
                                <span class="catalog-stock">Stok <?= (int) $p['stock'] ?></span>
                            </div>

                            <div class="catalog-card-footer">
                                <div>
                                    <small>Harga</small>
                                    <strong><?= format_price((int) $p['price']) ?></strong>
                                </div>
                                <a class="btn btn-soft" href="<?= e(page_url('product-detail', ['id' => $p['id']])) ?>">Lihat detail <i class="bi bi-arrow-up-right ms-1"></i></a>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
