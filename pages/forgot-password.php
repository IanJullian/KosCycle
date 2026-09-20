<?php
require_once __DIR__ . '/../includes/auth.php';
require_guest();
$errors=[];
if(is_post()){
    $identity=trim($_POST['identity']??'');
    if(!verify_csrf())$errors[]='Sesi formulir tidak valid.';
    if($identity==='')$errors[]='Masukkan email atau username.';
    if(!recaptcha_valid($_POST['g-recaptcha-response'] ?? null))$errors[]='Verifikasi reCAPTCHA belum berhasil.';
    if(!$errors){
        $stmt=db()->prepare('SELECT id,email FROM users WHERE email=? OR username=? LIMIT 1');$stmt->execute([$identity,$identity]);$user=$stmt->fetch();
        $recent=db()->prepare('SELECT created_at FROM password_otps WHERE user_id=? ORDER BY id DESC LIMIT 1');$recent->execute([(int)($user['id']??0)]);$last=$recent->fetchColumn();
        if($last && (time()-strtotime($last))<OTP_RATE_LIMIT_SECONDS)$errors[]='Silakan tunggu sebentar sebelum meminta OTP lagi.';
        else{
            // Enumeration-safe response: invalid account does not reveal account existence.
            if($user && !empty($user['email'])){
                $otp=(string)random_int(100000,999999);
                if(deliver_otp('email',$user['email'],$otp)){
                    $stmt=db()->prepare('INSERT INTO password_otps (user_id,channel,destination,otp_hash,expires_at) VALUES (?,"email",?,?,DATE_ADD(NOW(),INTERVAL ? MINUTE))');
                    $stmt->execute([(int)$user['id'],$user['email'],password_hash($otp,PASSWORD_DEFAULT),OTP_EXPIRY_MINUTES]);
                    $_SESSION['otp_user_id']=(int)$user['id'];$_SESSION['otp_channel']='email';
                }
            }
            if($user && !empty($user['email']) && isset($_SESSION['otp_user_id'])){
                flash('success','Jika akun memiliki email yang terdaftar, kode OTP telah diproses. Periksa inbox dan spam.');
                redirect(page_url('verify-otp'));
            }
            // Same visible message for missing account or delivery failure.
            $errors[]='Jika akun ditemukan, kode OTP akan dikirim. Pastikan email/username benar dan periksa inbox atau spam.';
        }
    }
}
$pageTitle='Lupa Password';require __DIR__.'/../includes/header.php';
?>
<section class="auth-section"><div class="auth-shell"><div class="auth-intro"><span class="eyebrow">Akses kembali</span><h1>Keamanan akun,<br><em>tetap terjaga.</em></h1><p>Kami akan mengirimkan kode sekali pakai ke email yang terdaftar di akunmu.</p></div><div class="auth-panel glass-card"><div class="auth-heading"><span class="auth-icon"><i class="bi bi-key"></i></span><div><h2>Lupa password?</h2><p>Masukkan email atau username akunmu.</p></div></div><?php if($errors):?><div class="alert alert-danger small"><?=implode('<br>',array_map('e',$errors))?></div><?php endif;?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><label class="form-label">Email atau username</label><input class="form-control mb-3" name="identity" placeholder="kamu@email.com atau username"><div class="recaptcha-placeholder mb-3"><i class="bi bi-envelope me-2"></i>OTP email berlaku <?=OTP_EXPIRY_MINUTES?> menit.</div><?php if(recaptcha_configured()):?><div class="g-recaptcha mb-3" data-sitekey="<?=e(RECAPTCHA_SITE_KEY)?>"></div><?php endif;?><button class="btn btn-primary w-100" type="submit">Kirim kode OTP <i class="bi bi-send ms-2"></i></button></form><p class="auth-switch"><a href="<?=e(page_url('login'))?>"><i class="bi bi-arrow-left me-1"></i>Kembali ke login</a></p></div></div></section>
<?php if(recaptcha_configured()):?><script src="https://www.google.com/recaptcha/api.js" async defer></script><?php endif;?><?php require __DIR__.'/../includes/footer.php'; ?>
