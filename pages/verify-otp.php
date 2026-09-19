<?php
require_once __DIR__ . '/../includes/auth.php';
require_guest();
if (empty($_SESSION['otp_user_id'])) { flash('error', 'Minta kode OTP terlebih dahulu.'); redirect(APP_URL . '/?page=forgot-password'); }
$errors = [];
if (is_post()) {
    $otp = trim($_POST['otp'] ?? '');
    if (!verify_csrf()) $errors[] = 'Sesi formulir tidak valid.';
    if (!preg_match('/^\d{6}$/', $otp)) $errors[] = 'Kode OTP harus terdiri dari 6 angka.';
    if (!$errors) {
        $stmt = db()->prepare('SELECT * FROM password_otps WHERE user_id = ? AND channel = "email" AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
        $stmt->execute([$_SESSION['otp_user_id']]);
        $record = $stmt->fetch();
        if (!$record || !password_verify($otp, $record['otp_hash'])) $errors[] = 'Kode OTP salah atau sudah kedaluwarsa.';
        else { db()->prepare('UPDATE password_otps SET used_at = NOW() WHERE id = ?')->execute([$record['id']]); $_SESSION['reset_verified'] = true; redirect(APP_URL . '/?page=reset-password'); }
    }
}
$pageTitle = 'Verifikasi OTP'; require __DIR__ . '/../includes/header.php';
?>
<section class="auth-section"><div class="auth-shell"><div class="auth-intro"><span class="eyebrow">Satu langkah lagi</span><h1>Periksa kotak<br><em>pesanmu.</em></h1><p>Masukkan kode 6 angka yang kami kirim ke email akunmu.</p></div><div class="auth-panel glass-card"><div class="auth-heading"><span class="auth-icon"><i class="bi bi-shield-lock"></i></span><div><h2>Verifikasi OTP</h2><p>Kode berlaku selama <?= OTP_EXPIRY_MINUTES ?> menit.</p></div></div><?php if ($message = flash('success')): ?><div class="alert alert-success small"><?= e($message) ?></div><?php endif; ?><?php if ($errors): ?><div class="alert alert-danger small"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label class="form-label">Kode OTP</label><input class="form-control otp-input mb-4" name="otp" inputmode="numeric" maxlength="6" placeholder="000000" autofocus><button class="btn btn-primary w-100" type="submit">Verifikasi kode <i class="bi bi-arrow-right ms-2"></i></button></form><p class="auth-switch"><a href="<?= APP_URL ?>/?page=forgot-password">Kirim ulang kode</a></p></div></div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>