<?php
require_once __DIR__ . '/../includes/auth.php';
require_guest();

$errors = [];
$data = ['full_name' => '', 'shop_name' => '', 'shop_city' => '', 'shop_description' => '', 'username' => '', 'whatsapp' => '', 'email' => ''];

if (is_post()) {
    $data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'shop_name' => trim($_POST['shop_name'] ?? ''),
        'shop_city' => trim($_POST['shop_city'] ?? ''),
        'shop_description' => trim($_POST['shop_description'] ?? ''),
        'username' => trim($_POST['username'] ?? ''),
        'whatsapp' => trim($_POST['whatsapp'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
    ];
    $password = $_POST['password'] ?? '';
    $confirmation = $_POST['password_confirmation'] ?? '';

    if (!verify_csrf()) $errors[] = 'Sesi formulir tidak valid. Silakan muat ulang halaman.';
    if (mb_strlen($data['full_name']) < 3 || mb_strlen($data['full_name']) > 120) $errors[] = 'Nama lengkap 3-120 karakter.';
    if (mb_strlen($data['shop_name']) < 3 || mb_strlen($data['shop_name']) > 120) $errors[] = 'Nama toko 3-120 karakter.';
    if (mb_strlen($data['shop_city']) < 2 || mb_strlen($data['shop_city']) > 80) $errors[] = 'Kota toko 2-80 karakter.';
    if (mb_strlen($data['shop_description']) < 10 || mb_strlen($data['shop_description']) > 500) $errors[] = 'Deskripsi toko minimal 10 karakter dan maksimal 500 karakter.';
    if (!preg_match('/^[a-zA-Z0-9_]{4,30}$/', $data['username'])) $errors[] = 'Username 4-30 karakter, hanya huruf, angka, dan underscore.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email belum valid.';
    $phone = preg_replace('/[^0-9]/', '', $data['whatsapp']) ?? '';
    if (!preg_match('/^(08|62)[0-9]{8,13}$/', $phone)) $errors[] = 'Nomor WhatsApp belum valid.';
    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) $errors[] = 'Password minimal 8 karakter dan harus memiliki huruf besar serta angka.';
    if ($password !== $confirmation) $errors[] = 'Konfirmasi password tidak sama.';
    if (!recaptcha_valid($_POST['g-recaptcha-response'] ?? null, 'register_seller')) $errors[] = 'Verifikasi reCAPTCHA belum berhasil.';

    if (!$errors) {
        $check = db()->prepare('SELECT id FROM users WHERE username=? OR email=? LIMIT 1');
        $check->execute([$data['username'], $data['email']]);
        if ($check->fetch()) {
            $errors[] = 'Username atau email sudah digunakan.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO users (full_name,username,shop_name,shop_city,shop_description,whatsapp,email,password_hash,role) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $data['full_name'],
            $data['username'],
            $data['shop_name'],
            $data['shop_city'],
            $data['shop_description'],
            normalize_phone($data['whatsapp']),
            $data['email'],
            password_hash($password, PASSWORD_DEFAULT),
            'seller',
        ]);

        // Seller langsung login setelah registrasi berhasil.
        $sellerId = (int) db()->lastInsertId();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $sellerId;
        clear_old();
        flash('success', 'Akun seller berhasil dibuat. Selamat datang di dashboard tokomu!');
        redirect(page_url('seller-dashboard'));
    }

    set_old($data);
}

$pageTitle = 'Daftar Seller';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/ux-v3.css">
<section class="auth-section auth-register-page">
    <div class="auth-shell">
        <div class="auth-intro">
            <span class="eyebrow">Seller</span>
            <h1>Jual barangmu<br><em>lebih cepat.</em></h1>
            <p>Daftar sebagai seller untuk memasarkan produk bekas, mengatur stok, dan mengelola pesanan dari satu dashboard.</p>
            <div class="benefit-list">
                <div><i class="bi bi-check2-circle"></i><span>Tambah produk ke katalog</span></div>
                <div><i class="bi bi-check2-circle"></i><span>Kelola stok dan pesanan</span></div>
                <div><i class="bi bi-check2-circle"></i><span>Promosikan barang dengan lebih mudah</span></div>
            </div>
        </div>

        <div class="auth-panel glass-card">
            <div class="auth-heading">
                <span class="auth-icon"><i class="bi bi-shop"></i></span>
                <div><h2>Buat akun seller</h2><p>Daftarkan toko kecilmu dan mulai jualan.</p></div>
            </div>

            <?php if ($errors): ?><div class="alert alert-danger small"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

            <form method="post" novalidate data-recaptcha-action="register_seller">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="g-recaptcha-response" value="">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label">Nama lengkap</label><input class="form-control" name="full_name" value="<?= old('full_name') ?>" placeholder="Nama pemilik toko" autocomplete="name"></div>
                    <div class="col-12"><label class="form-label">Nama toko / brand</label><input class="form-control" name="shop_name" value="<?= old('shop_name') ?>" placeholder="contoh: Antik Shop" autocomplete="organization"></div>
                    <div class="col-md-6"><label class="form-label">Kota toko</label><input class="form-control" name="shop_city" value="<?= old('shop_city') ?>" placeholder="contoh: Bandung" autocomplete="address-level2"></div>
                    <div class="col-md-6"><label class="form-label">Username</label><input class="form-control" name="username" value="<?= old('username') ?>" placeholder="contoh: toko_kos" autocomplete="username"></div>
                    <div class="col-12"><label class="form-label">Deskripsi toko</label><textarea class="form-control" name="shop_description" rows="3" placeholder="Ceritakan singkat tentang produk yang kamu jual, gaya toko, atau layanan yang tersedia."><?= old('shop_description') ?></textarea></div>
                    <div class="col-md-6"><label class="form-label">Nomor WhatsApp</label><input class="form-control" name="whatsapp" value="<?= old('whatsapp') ?>" placeholder="08xxxxxxxxxx" autocomplete="tel"></div>
                    <div class="col-md-6"><label class="form-label">Email aktif</label><input class="form-control" type="email" name="email" value="<?= old('email') ?>" placeholder="toko@email.com" autocomplete="email"></div>
                    <div class="col-md-6"><label class="form-label">Password</label><input class="form-control" type="password" name="password" placeholder="Min. 8 karakter" autocomplete="new-password"></div>
                    <div class="col-md-6"><label class="form-label">Konfirmasi password</label><input class="form-control" type="password" name="password_confirmation" placeholder="Ulangi password" autocomplete="new-password"></div>
                </div>

                <div class="form-hint mt-3"><i class="bi bi-info-circle me-1"></i> Gunakan huruf besar dan angka dalam password.</div>
                <div class="recaptcha-placeholder mt-3"><i class="bi bi-shield-check"></i> Perlindungan reCAPTCHA v3 aktif.</div>
                <button class="btn btn-primary w-100 mt-4" type="submit">Buat akun seller <i class="bi bi-arrow-right ms-2"></i></button>
            </form>

            <p class="auth-switch">Sudah punya akun? <a href="<?= e(page_url('login')) ?>">Masuk di sini</a></p>
            <p class="auth-switch mt-2">Mau daftar sebagai customer? <a href="<?= e(page_url('register-customer')) ?>">Daftar customer</a></p>
        </div>
    </div>
</section>

<?php if (recaptcha_configured()): ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?= e(RECAPTCHA_SITE_KEY) ?>" async defer></script>
<script>
(() => {
    const form = document.querySelector('form[data-recaptcha-action="register_seller"]');
    if (!form) return;
    form.addEventListener('submit', function (event) {
        if (form.dataset.recaptchaReady === '1') return;
        event.preventDefault();
        if (typeof grecaptcha === 'undefined') return;
        grecaptcha.ready(function () {
            grecaptcha.execute('<?= e(RECAPTCHA_SITE_KEY) ?>', { action: 'register_seller' }).then(function (token) {
                form.querySelector('[name="g-recaptcha-response"]').value = token;
                form.dataset.recaptchaReady = '1';
                form.submit();
            });
        });
    });
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
