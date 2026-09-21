<?php
require_once __DIR__ . '/../includes/auth.php';
require_guest();

$pageTitle = 'Daftar';
require __DIR__ . '/../includes/header.php';
?>
<section class="auth-section">
    <div class="auth-shell auth-shell-wide">
        <div class="auth-intro">
            <span class="eyebrow">Mulai berputar</span>
            <h1>Satu akun,<br><em>banyak kemungkinan.</em></h1>
            <p>Pilih cara kamu menggunakan KosCycle. Belanja sebagai customer atau jual barang sebagai seller.</p>
            <div class="benefit-list">
                <div><i class="bi bi-check2-circle"></i><span>Temukan barang di sekitar kamu</span></div>
                <div><i class="bi bi-check2-circle"></i><span>Jual barang yang sudah tidak terpakai</span></div>
                <div><i class="bi bi-check2-circle"></i><span>Transaksi dengan lebih nyaman</span></div>
            </div>
        </div>

        <div class="auth-panel glass-card">
            <div class="auth-heading">
                <span class="auth-icon"><i class="bi bi-person-plus"></i></span>
                <div>
                    <h2>Pilih tipe akun</h2>
                    <p>Pilih sesuai kebutuhanmu.</p>
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-12 col-lg-6">
                    <a href="<?= e(page_url('register-customer')) ?>" class="btn btn-primary w-100 py-3">
                        <i class="bi bi-bag-heart me-2"></i>Daftar sebagai Customer
                    </a>
                    <p class="small text-muted mt-2 mb-0">Cari, simpan, dan beli barang bekas dengan mudah.</p>
                </div>
                <div class="col-12 col-lg-6">
                    <a href="<?= e(page_url('register-seller')) ?>" class="btn btn-outline-primary w-100 py-3">
                        <i class="bi bi-shop me-2"></i>Daftar sebagai Seller
                    </a>
                    <p class="small text-muted mt-2 mb-0">Jual barangmu dan kelola pesanan di satu dashboard.</p>
                </div>
            </div>

            <p class="auth-switch mt-4">Sudah punya akun? <a href="<?= e(page_url('login')) ?>">Masuk di sini</a></p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
