<?php
require_once __DIR__ . '/../includes/auth.php';
require_guest();

$errors = [];
if (is_post()) {
    if (!verify_csrf()) {
        $errors[] = 'Sesi formulir tidak valid. Silakan coba lagi.';
    }

    $identity = trim((string) ($_POST['identity'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($identity === '') {
        $errors[] = 'Username atau email wajib diisi.';
    }
    if ($password === '') {
        $errors[] = 'Password wajib diisi.';
    }

    $user = null;
    $credentialsValid = false;

    // Validasi kredensial lebih dulu agar user yang salah akun/password tidak
    // menerima pesan yang menyesatkan seolah-olah masalahnya ada di CAPTCHA.
    if (!$errors) {
        $stmt = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$identity, $identity]);
        $user = $stmt->fetch() ?: null;

        $credentialsValid = $user
            && password_verify($password, (string) $user['password_hash'])
            && ($user['status'] ?? '') === 'active';

        if (!$credentialsValid) {
            $errors[] = 'Akun belum terdaftar, password salah, atau akun sedang tidak aktif.';
        }
    }

    // reCAPTCHA tetap divalidasi untuk login yang kredensialnya benar, tetapi
    // tidak menimpa pesan kesalahan akun/password.
    if ($credentialsValid && !recaptcha_valid($_POST['g-recaptcha-response'] ?? null, 'login')) {
        $errors[] = 'Verifikasi keamanan reCAPTCHA belum berhasil. Silakan coba lagi.';
    }

    if (!$errors && $user) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        clear_old();
        flash('success', 'Selamat datang kembali, ' . $user['full_name'] . '!');
        redirect(dashboard_url((string) $user['role']));
    }

    set_old(['identity' => $identity]);
}

$pageTitle = 'Masuk';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/ux-v3.css">
<section class="auth-section auth-login-page">
    <div class="auth-shell">
        <div class="auth-intro">
            <span class="eyebrow">Selamat datang lagi</span>
            <h1>Temukan barang<br><em>yang berarti.</em></h1>
            <p>Masuk ke akunmu untuk berbelanja, menjual barang, atau mengelola KosCycle sebagai admin.</p>
            <a href="<?= e(APP_URL) ?>/" class="text-link"><i class="bi bi-arrow-left me-2"></i>Kembali ke beranda</a>
        </div>
        <div class="auth-panel glass-card">
            <div class="auth-heading">
                <span class="auth-icon"><i class="bi bi-person-lock"></i></span>
                <div><h2>Masuk ke KosCycle</h2><p>Gunakan akunmu untuk melanjutkan.</p></div>
            </div>
            <?php if ($errors): ?><div class="alert alert-danger small"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
            <form method="post" novalidate data-recaptcha-action="login">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="g-recaptcha-response" value="">
                <div class="mb-3">
                    <label class="form-label">Username atau email</label>
                    <input class="form-control" type="text" name="identity" value="<?= old('identity') ?>" placeholder="kamu@email.com" autocomplete="username">
                </div>
                <div class="mb-2">
                    <label class="form-label">Password</label>
                    <div class="input-with-icon">
                        <input class="form-control" type="password" name="password" placeholder="Masukkan password" autocomplete="current-password">
                        <button type="button" class="toggle-password" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
                <div class="d-flex justify-content-end mb-4"><a href="<?= e(page_url('forgot-password')) ?>" class="small text-link">Lupa password?</a></div>
                <div class="recaptcha-placeholder mb-3"><i class="bi bi-shield-check"></i> Perlindungan reCAPTCHA v3 aktif.</div>
                <button class="btn btn-primary w-100" type="submit">Masuk sekarang <i class="bi bi-arrow-right ms-2"></i></button>
            </form>
            <p class="auth-switch">Belum punya akun? <a href="<?= e(page_url('register')) ?>">Daftar sekarang</a></p>
        </div>
    </div>
</section>
<?php if (recaptcha_configured()): ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?= e(RECAPTCHA_SITE_KEY) ?>" async defer></script>
<script>
(() => {
    const form = document.querySelector('form[data-recaptcha-action="login"]');
    if (!form) return;

    form.addEventListener('submit', function (event) {
        if (form.dataset.recaptchaReady === '1') return;
        event.preventDefault();

        if (typeof grecaptcha === 'undefined') {
            const alert = document.createElement('div');
            alert.className = 'alert alert-warning small';
            alert.textContent = 'Sistem keamanan belum siap. Tunggu sebentar lalu coba lagi.';
            form.prepend(alert);
            return;
        }

        grecaptcha.ready(function () {
            grecaptcha.execute('<?= e(RECAPTCHA_SITE_KEY) ?>', { action: 'login' }).then(function (token) {
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
