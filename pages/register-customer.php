<?php
require_once __DIR__ . '/../includes/auth.php';
require_guest();

$errors = [];
$data = ['full_name' => '', 'username' => '', 'whatsapp' => '', 'email' => ''];

if (is_post()) {
    $data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'username' => trim($_POST['username'] ?? ''),
        'whatsapp' => trim($_POST['whatsapp'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
    ];
    $password = $_POST['password'] ?? '';
    $confirmation = $_POST['password_confirmation'] ?? '';

    if (!verify_csrf()) $errors[] = 'Sesi formulir tidak valid. Silakan muat ulang halaman.';
    if (mb_strlen($data['full_name']) < 3 || mb_strlen($data['full_name']) > 120) $errors[] = 'Nama lengkap 3-120 karakter.';
    if (!preg_match('/^[a-zA-Z0-9_]{4,30}$/', $data['username'])) $errors[] = 'Username 4-30 karakter, hanya huruf, angka, dan underscore.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email belum valid.';
    $phone = preg_replace('/[^0-9]/', '', $data['whatsapp']) ?? '';
    if (!preg_match('/^(08|62)[0-9]{8,13}$/', $phone)) $errors[] = 'Nomor WhatsApp belum valid.';
    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) $errors[] = 'Password minimal 8 karakter dan harus memiliki huruf besar serta angka.';
    if ($password !== $confirmation) $errors[] = 'Konfirmasi password tidak sama.';
    if (!recaptcha_valid($_POST['g-recaptcha-response'] ?? null)) $errors[] = 'Verifikasi reCAPTCHA belum berhasil.';

    if (!$errors) {
        $check = db()->prepare('SELECT id FROM users WHERE username=? OR email=? LIMIT 1');
        $check->execute([$data['username'], $data['email']]);
        if ($check->fetch()) {
            $errors[] = 'Username atau email sudah digunakan.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO users (full_name,username,whatsapp,email,password_hash,role) VALUES (?,?,?,?,?,?)');
        $stmt->execute([
            $data['full_name'],
            $data['username'],
            normalize_phone($data['whatsapp']),
            $data['email'],
            password_hash($password, PASSWORD_DEFAULT),
            'customer',
        ]);
        clear_old();
        flash('success', 'Akun customer berhasil dibuat. Silakan masuk.');
        redirect(page_url('login'));
    }

    set_old($data);
}

$pageTitle = 'Daftar Customer';
require __DIR__ . '/../includes/header.php';
?>
<section class="auth-section">
    <div class="auth-shell">
        <div class="auth-intro">
            <span class="eyebrow">Customer</span>
            <h1>Mulai <em>belanja</em><br>yang lebih gampang.</h1>
            <p>Daftar sebagai customer untuk mencari barang bekas favorit, menabung, dan menyelesaikan transaksi dengan mudah.</p>
            <div class="benefit-list">
                <div><i class="bi bi-check2-circle"></i><span>Cari barang sesuai kebutuhan</span></div>
                <div><i class="bi bi-check2-circle"></i><span>Simpan barang ke keranjang</span></div>
                <div><i class="bi bi-check2-circle"></i><span>Kelola pesanan di dashboard</span></div>
            </div>
        </div>

        <div class="auth-panel glass-card">
            <div class="auth-heading">
                <span class="auth-icon"><i class="bi bi-bag-heart"></i></span>
                <div>
                    <h2>Buat akun customer</h2>
                    <p>Masukkan data dirimu untuk mulai belanja.</p>
                </div>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-danger small"><?= implode('<br>', array_map('e', $errors)) ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Nama lengkap</label>
                        <input class="form-control" name="full_name" value="<?= old('full_name') ?>" placeholder="Nama kamu" autocomplete="name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input class="form-control" name="username" value="<?= old('username') ?>" placeholder="contoh: anak_kos" autocomplete="username">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nomor WhatsApp</label>
                        <input class="form-control" name="whatsapp" value="<?= old('whatsapp') ?>" placeholder="08xxxxxxxxxx" autocomplete="tel">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Email aktif</label>
                        <input class="form-control" type="email" name="email" value="<?= old('email') ?>" placeholder="kamu@email.com" autocomplete="email">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <input class="form-control" type="password" name="password" placeholder="Min. 8 karakter" autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Konfirmasi password</label>
                        <input class="form-control" type="password" name="password_confirmation" placeholder="Ulangi password" autocomplete="new-password">
                    </div>
                </div>

                <div class="form-hint mt-3"><i class="bi bi-info-circle me-1"></i> Gunakan huruf besar dan angka dalam password.</div>

                <?php if (recaptcha_configured()): ?>
                    <div class="g-recaptcha mt-3" data-sitekey="<?= e(RECAPTCHA_SITE_KEY) ?>"></div>
                <?php else: ?>
                    <div class="recaptcha-placeholder mt-3"><i class="bi bi-shield-check"></i> reCAPTCHA dalam mode local.</div>
                <?php endif; ?>

                <button class="btn btn-primary w-100 mt-4" type="submit">Buat akun customer <i class="bi bi-arrow-right ms-2"></i></button>
            </form>

            <p class="auth-switch">Sudah punya akun? <a href="<?= e(page_url('login')) ?>">Masuk di sini</a></p>
            <p class="auth-switch mt-2">Ingin jadi seller? <a href="<?= e(page_url('register-seller')) ?>">Daftar seller</a></p>
        </div>
    </div>
</section>

<?php if (recaptcha_configured()): ?><script src="https://www.google.com/recaptcha/api.js" async defer></script><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
