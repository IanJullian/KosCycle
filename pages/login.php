<?php
require_once __DIR__ . '/../includes/auth.php';
require_guest();
$errors = [];
if (is_post()) {
    if (!verify_csrf()) $errors[] = 'Sesi formulir tidak valid. Silakan coba lagi.';
    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($identity === '') $errors[] = 'Username atau email wajib diisi.';
    if ($password === '') $errors[] = 'Password wajib diisi.';
    if (!recaptcha_valid($_POST['g-recaptcha-response'] ?? null)) $errors[] = 'Verifikasi reCAPTCHA belum berhasil.';
    if (!$errors) {
        $stmt = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$identity, $identity]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash']) && $user['status'] === 'active') {
            session_regenerate_id(true); $_SESSION['user_id'] = $user['id']; clear_old();
            flash('success', 'Selamat datang kembali, ' . $user['full_name'] . '!'); redirect(APP_URL . '/?page=account');
        }
        $errors[] = 'Username/email atau password belum sesuai.';
    }
}
$pageTitle = 'Masuk'; require __DIR__ . '/../includes/header.php';
?><section class="auth-section"><div class="auth-shell"><div class="auth-intro"><span class="eyebrow">Selamat datang lagi</span><h1>Temukan barang<br><em>yang berarti.</em></h1><p>Masuk untuk menyimpan barang favorit dan mulai memesan dari pemilik di dekatmu.</p><a href="<?= APP_URL ?>/" class="text-link"><i class="bi bi-arrow-left me-2"></i>Kembali ke beranda</a></div><div class="auth-panel glass-card"><div class="auth-heading"><span class="auth-icon"><i class="bi bi-person-lock"></i></span><div><h2>Masuk ke KosCycle</h2><p>Gunakan akunmu untuk melanjutkan.</p></div></div><?php if ($errors): ?><div class="alert alert-danger small"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?><form method="post" novalidate><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="mb-3"><label class="form-label">Username atau email</label><input class="form-control" type="text" name="identity" value="<?= old('identity') ?>" placeholder="kamu@email.com" autocomplete="username"></div><div class="mb-2"><label class="form-label">Password</label><div class="input-with-icon"><input class="form-control" type="password" name="password" placeholder="Masukkan password" autocomplete="current-password"><button type="button" class="toggle-password"><i class="bi bi-eye"></i></button></div></div><div class="d-flex justify-content-end mb-4"><a href="<?= APP_URL ?>/?page=forgot-password" class="small text-link">Lupa password?</a></div><?php if (RECAPTCHA_SITE_KEY !== 'GANTI_DENGAN_SITE_KEY'): ?><div class="g-recaptcha mb-3" data-sitekey="<?= e(RECAPTCHA_SITE_KEY) ?>"></div><?php else: ?><div class="recaptcha-placeholder mb-3"><i class="bi bi-shield-check"></i> reCAPTCHA siap dikonfigurasi</div><?php endif; ?><button class="btn btn-primary w-100" type="submit">Masuk sekarang <i class="bi bi-arrow-right ms-2"></i></button></form><p class="auth-switch">Belum punya akun? <a href="<?= APP_URL ?>/?page=register">Daftar sekarang</a></p></div></div></section><?php if (RECAPTCHA_SITE_KEY !== 'GANTI_DENGAN_SITE_KEY'): ?><script src="https://www.google.com/recaptcha/api.js" async defer></script><?php endif; ?><?php require __DIR__ . '/../includes/footer.php'; ?>