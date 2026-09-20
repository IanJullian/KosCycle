<?php
require_once __DIR__ . '/../includes/auth.php';
require_guest();
if(empty($_SESSION['reset_verified'])||empty($_SESSION['reset_user_id'])||empty($_SESSION['reset_otp_id'])||time()>(int)($_SESSION['reset_verified_until']??0)){unset($_SESSION['reset_verified'],$_SESSION['reset_user_id'],$_SESSION['reset_otp_id'],$_SESSION['reset_verified_until']);redirect(page_url('forgot-password'));}
$errors=[];
if(is_post()){
    $password=$_POST['password']??'';$confirmation=$_POST['password_confirmation']??'';
    if(!verify_csrf())$errors[]='Sesi formulir tidak valid.';
    if(!recaptcha_valid($_POST['g-recaptcha-response'] ?? null))$errors[]='Verifikasi reCAPTCHA belum berhasil.';
    if(strlen($password)<8||!preg_match('/[A-Z]/',$password)||!preg_match('/[0-9]/',$password))$errors[]='Password minimal 8 karakter, dengan huruf besar dan angka.';
    if($password!==$confirmation)$errors[]='Konfirmasi password tidak sama.';
    if(!$errors){
        $stmt=db()->prepare('UPDATE users SET password_hash=? WHERE id=?');
        $stmt->execute([password_hash($password,PASSWORD_DEFAULT),(int)$_SESSION['reset_user_id']]);
        unset($_SESSION['otp_user_id'],$_SESSION['otp_channel'],$_SESSION['reset_verified'],$_SESSION['reset_user_id'],$_SESSION['reset_otp_id'],$_SESSION['reset_verified_until']);
        flash('success','Password berhasil diubah. Silakan login dengan password baru.');
        redirect(page_url('login'));
    }
}
$pageTitle='Reset Password';require __DIR__.'/../includes/header.php';
?>
<section class="auth-section"><div class="auth-shell"><div class="auth-intro"><span class="eyebrow">Akun terlindungi</span><h1>Buat password<br><em>yang baru.</em></h1><p>Pilih password yang kuat agar akun dan aktivitasmu tetap aman.</p></div><div class="auth-panel glass-card"><div class="auth-heading"><span class="auth-icon"><i class="bi bi-lock"></i></span><div><h2>Reset password</h2><p>Hampir selesai.</p></div></div><?php if($errors):?><div class="alert alert-danger small"><?=implode('<br>',array_map('e',$errors))?></div><?php endif;?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><label class="form-label">Password baru</label><input class="form-control mb-3" type="password" name="password" placeholder="Min. 8 karakter" autocomplete="new-password"><label class="form-label">Konfirmasi password</label><input class="form-control mb-3" type="password" name="password_confirmation" placeholder="Ulangi password" autocomplete="new-password"><?php if(recaptcha_configured()):?><div class="g-recaptcha mb-3" data-sitekey="<?=e(RECAPTCHA_SITE_KEY)?>"></div><?php endif;?><button class="btn btn-primary w-100" type="submit">Simpan password <i class="bi bi-check2 ms-2"></i></button></form></div></div></section>
<?php if(recaptcha_configured()):?><script src="https://www.google.com/recaptcha/api.js" async defer></script><?php endif;?><?php require __DIR__.'/../includes/footer.php'; ?>
