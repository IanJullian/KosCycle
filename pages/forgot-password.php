<?php
require_once __DIR__ . '/../includes/auth.php';
require_guest();
require_once __DIR__ . '/../includes/functions.php';
$errors = [];
if (is_post()) {
    $identity = trim($_POST['identity'] ?? '');
    if (!verify_csrf()) $errors[] = 'Sesi formulir tidak valid.';
    if ($identity === '') $errors[] = 'Masukkan email atau username.';
    if (!$errors) {
        $stmt = db()->prepare('SELECT id, email FROM users WHERE email = ? OR username = ? LIMIT 1');
        $stmt->execute([$identity, $identity]);
        $user = $stmt->fetch();
        if (!$user) $errors[] = 'Akun tidak ditemukan.';
        elseif (!$user['email']) $errors[] = 'Akun ini belum memiliki email.';
        else {
            $otp = (string) random_int(100000, 999999);
            if (!deliver_otp('email', $user['email'], $otp)) {
                $errors[] = 'Email OTP gagal dikirim. Periksa konfigurasi SMTP dan App Password Gmail.';
            } else {
                $stmt = db()->prepare('INSERT INTO password_otps (user_id, channel, destination, otp_hash, expires_at) VALUES (?, "email", ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))');
                $stmt->execute([$user['id'], $user['email'], password_hash($otp, PASSWORD_DEFAULT), OTP_EXPIRY_MINUTES]);
                $_SESSION['otp_user_id'] = $user['id']; $_SESSION['otp_channel'] = 'email';
                flash('success', 'Kode OTP berhasil dikirim melalui email.');
                redirect(APP_URL . '/?page=verify-otp');
            }
        }
    }
}
$pageTitle = 'Lupa Password'; require __DIR__ . '/../includes/header.php';
?>
<section class="auth-section"><div class="auth-shell"><div class="auth-intro"><span class="eyebrow">Akses kembali</span><h1>Keamanan akun,<br><em>tetap terjaga.</em></h1><p>Kami akan mengirimkan kode sekali pakai ke email yang terdaftar di akunmu.</p></div><div class="auth-panel glass-card"><div class="auth-heading"><span class="auth-icon"><i class="bi bi-key"></i></span><div><h2>Lupa password?</h2><p>Masukkan email atau username akunmu.</p></div></div><?php if ($errors): ?><div class="alert alert-danger small"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label class="form-label">Email atau username</label><input class="form-control mb-3" name="identity" placeholder="kamu@email.com atau username"><div class="recaptcha-placeholder mb-4"><i class="bi bi-envelope me-2"></i>OTP dikirim ke email dan berlaku <?= OTP_EXPIRY_MINUTES ?> menit.</div><button class="btn btn-primary w-100" type="submit">Kirim kode OTP <i class="bi bi-send ms-2"></i></button></form><p class="auth-switch"><a href="<?= APP_URL ?>/?page=login"><i class="bi bi-arrow-left me-1"></i>Kembali ke login</a></p></div></div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>