<?php
require_once __DIR__ . '/../includes/authorization.php';
require_role('customer');
require_once __DIR__ . '/../includes/Repositories/ReviewRepository.php';
$productId=filter_input(INPUT_GET,'product_id',FILTER_VALIDATE_INT);
if(!$productId) { http_response_code(400); exit('Produk tidak valid.'); }
$repo=new ReviewRepository(); $userId=(int)$_SESSION['user_id']; $errors=[];
if(!$repo->canReview($userId,(int)$productId) || $repo->exists($userId,(int)$productId)){ http_response_code(403); exit('Kamu belum memenuhi syarat untuk menilai produk ini.'); }
if(is_post()){
    if(!verify_csrf()) $errors[]='Sesi formulir tidak valid.';
    $rating=filter_input(INPUT_POST,'rating',FILTER_VALIDATE_INT); $review=trim($_POST['review_text']??'');
    if(!$rating || $rating<1 || $rating>5) $errors[]='Rating harus 1 sampai 5.';
    if(mb_strlen($review)>5000) $errors[]='Ulasan terlalu panjang.';
    if(!$errors){ try{$repo->create($userId,(int)$productId,(int)$rating,$review); flash('success','Ulasan berhasil dikirim.'); redirect(page_url('product-detail',['id'=>$productId]));}catch(Throwable $e){$errors[]='Ulasan gagal disimpan.';} }
}
$pageTitle='Tulis Ulasan'; require __DIR__ . '/../includes/header.php';
?>
<section class="auth-section"><div class="auth-shell"><div class="auth-intro"><div class="page-toolbar"><a class="back-link" href="<?=e(page_url('product-detail',['id'=>(int)$productId]))?>"><i class="bi bi-arrow-left me-2"></i>Kembali ke produk</a></div><span class="eyebrow">Pengalamanmu</span><h1>Tulis <em>ulasan.</em></h1><p>Berikan rating dan pengalamanmu setelah transaksi selesai.</p></div><div class="auth-panel glass-card"><?php if($errors): ?><div class="alert alert-danger"><?= implode('<br>',array_map('e',$errors)) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label class="form-label">Rating</label><select class="form-select mb-3" name="rating"><option value="5">5</option><option value="4">4</option><option value="3">3</option><option value="2">2</option><option value="1">1</option></select><label class="form-label">Ulasan</label><textarea class="form-control mb-4" name="review_text" rows="6" placeholder="Bagaimana pengalamanmu?"></textarea><button class="btn btn-primary w-100">Kirim ulasan</button></form></div></div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
