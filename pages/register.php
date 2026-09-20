<?php
require_once __DIR__ . '/../includes/auth.php';
require_guest();

$errors = [];
$data = ['full_name'=>'','username'=>'','whatsapp'=>'','email'=>'','role'=>'customer'];
if (!empty($_SESSION['old']['role']) && in_array($_SESSION['old']['role'], ['customer','seller'], true)) $data['role'] = $_SESSION['old']['role'];

if (is_post()) {
    $data = ['full_name'=>trim($_POST['full_name']??''),'username'=>trim($_POST['username']??''),'whatsapp'=>trim($_POST['whatsapp']??''),'email'=>trim($_POST['email']??''),'role'=>(string)($_POST['role']??'')];
    $password = $_POST['password'] ?? '';
    $confirmation = $_POST['password_confirmation'] ?? '';

    if (!verify_csrf()) $errors[]='Sesi formulir tidak valid. Silakan muat ulang halaman.';
    if (!in_array($data['role'], ['customer','seller'], true)) $errors[]='Jenis akun tidak valid.';
    if (mb_strlen($data['full_name']) < 3 || mb_strlen($data['full_name']) > 120) $errors[]='Nama lengkap 3-120 karakter.';
    if (!preg_match('/^[a-zA-Z0-9_]{4,30}$/', $data['username'])) $errors[]='Username 4-30 karakter, hanya huruf, angka, dan underscore.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[]='Format email belum valid.';
    $phone = preg_replace('/[^0-9]/','',$data['whatsapp']) ?? '';
    if (!preg_match('/^(08|62)[0-9]{8,13}$/', $phone)) $errors[]='Nomor WhatsApp belum valid.';
    if (strlen($password)<8 || !preg_match('/[A-Z]/',$password) || !preg_match('/[0-9]/',$password)) $errors[]='Password minimal 8 karakter dan harus memiliki huruf besar serta angka.';
    if ($password !== $confirmation) $errors[]='Konfirmasi password tidak sama.';

    /* Existing CAPTCHA implementation: validation/provider/key are untouched. */
    if (!recaptcha_valid($_POST['g-recaptcha-response'] ?? null)) $errors[]='Verifikasi reCAPTCHA belum berhasil.';

    if (!$errors) {
        $check=db()->prepare('SELECT id FROM users WHERE username=? OR email=? LIMIT 1');
        $check->execute([$data['username'],$data['email']]);
        if ($check->fetch()) $errors[]='Username atau email sudah digunakan.';
    }
    if (!$errors) {
        $stmt=db()->prepare('INSERT INTO users (full_name,username,whatsapp,email,password_hash,role) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$data['full_name'],$data['username'],normalize_phone($data['whatsapp']),$data['email'],password_hash($password,PASSWORD_DEFAULT),$data['role']]);
        clear_old();
        flash('success','Akun ' . ($data['role']==='seller'?'seller':'customer') . ' berhasil dibuat. Silakan masuk.');
        redirect(page_url('login'));
    }
    set_old($data);
}
$pageTitle='Daftar';
require __DIR__ . '/../includes/header.php';
?>
<section class="auth-section"><div class="auth-shell auth-shell-wide"><div class="auth-intro"><span class="eyebrow">Mulai berputar</span><h1>Satu akun,<br><em>banyak kemungkinan.</em></h1><p>Pilih cara kamu menggunakan KosCycle. Belanja sebagai customer atau jual barang sebagai seller.</p><div class="benefit-list"><div><i class="bi bi-check2-circle"></i><span>Temukan barang di sekitar kamu</span></div><div><i class="bi bi-check2-circle"></i><span>Jual barang yang sudah tidak terpakai</span></div><div><i class="bi bi-check2-circle"></i><span>Transaksi dengan lebih nyaman</span></div></div></div>
<div class="auth-panel glass-card"><div class="auth-heading"><span class="auth-icon"><i class="bi bi-person-plus"></i></span><div><h2>Buat akun baru</h2><p>Pilih jenis akun sebelum melanjutkan.</p></div></div>
<?php if($errors):?><div class="alert alert-danger small"><?=implode('<br>',array_map('e',$errors))?></div><?php endif;?>
<form method="post" novalidate><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><label class="form-label">Saya ingin menggunakan KosCycle sebagai</label>
<div class="role-choice-grid mb-4">
<label class="role-choice <?= $data['role']==='customer'?'is-selected':'' ?>" for="role-customer"><input id="role-customer" type="radio" name="role" value="customer" <?= $data['role']==='customer'?'checked':'' ?> required><span class="role-choice-icon"><i class="bi bi-bag-heart"></i></span><span class="role-choice-copy"><strong>Customer</strong><small>Mencari, menyimpan, dan membeli barang.</small></span><span class="role-choice-check"><i class="bi bi-check-circle-fill"></i></span></label>
<label class="role-choice <?= $data['role']==='seller'?'is-selected':'' ?>" for="role-seller"><input id="role-seller" type="radio" name="role" value="seller" <?= $data['role']==='seller'?'checked':'' ?> required><span class="role-choice-icon"><i class="bi bi-shop"></i></span><span class="role-choice-copy"><strong>Seller</strong><small>Menjual barang dan mengelola pesanan.</small></span><span class="role-choice-check"><i class="bi bi-check-circle-fill"></i></span></label>
</div>
<div class="row g-3"><div class="col-12"><label class="form-label">Nama lengkap</label><input class="form-control" name="full_name" value="<?=old('full_name')?>" placeholder="Nama kamu" autocomplete="name"></div><div class="col-md-6"><label class="form-label">Username</label><input class="form-control" name="username" value="<?=old('username')?>" placeholder="contoh: anak_kos" autocomplete="username"></div><div class="col-md-6"><label class="form-label">Nomor WhatsApp</label><input class="form-control" name="whatsapp" value="<?=old('whatsapp')?>" placeholder="08xxxxxxxxxx" autocomplete="tel"></div><div class="col-12"><label class="form-label">Email aktif</label><input class="form-control" type="email" name="email" value="<?=old('email')?>" placeholder="kamu@email.com" autocomplete="email"></div><div class="col-md-6"><label class="form-label">Password</label><input class="form-control" type="password" name="password" placeholder="Min. 8 karakter" autocomplete="new-password"></div><div class="col-md-6"><label class="form-label">Konfirmasi password</label><input class="form-control" type="password" name="password_confirmation" placeholder="Ulangi password" autocomplete="new-password"></div></div>
<div class="form-hint mt-3"><i class="bi bi-info-circle me-1"></i> Gunakan huruf besar dan angka dalam password.</div>
<?php if(defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY!=='GANTI_DENGAN_SITE_KEY'):?><div class="g-recaptcha mt-3" data-sitekey="<?=e(RECAPTCHA_SITE_KEY)?>"></div><?php else:?><div class="recaptcha-placeholder mt-3"><i class="bi bi-shield-check"></i> reCAPTCHA siap dikonfigurasi</div><?php endif;?>
<button class="btn btn-primary w-100 mt-4" type="submit">Buat akun <i class="bi bi-arrow-right ms-2"></i></button></form><p class="auth-switch">Sudah punya akun? <a href="<?=e(page_url('login'))?>">Masuk di sini</a></p></div></div></section>
<?php if(defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY!=='GANTI_DENGAN_SITE_KEY'):?><script src="https://www.google.com/recaptcha/api.js" async defer></script><?php endif;?><script>
document.addEventListener('DOMContentLoaded',function(){const cards=Array.from(document.querySelectorAll('.role-choice'));cards.forEach(function(card){const input=card.querySelector('input[type="radio"]');if(!input)return;card.addEventListener('click',function(){input.checked=true;cards.forEach(function(other){other.classList.toggle('is-selected',other===card);});});input.addEventListener('change',function(){cards.forEach(function(other){const r=other.querySelector('input[type="radio"]');other.classList.toggle('is-selected',!!r&&r.checked);});});});});
</script><?php require __DIR__ . '/../includes/footer.php'; ?>
