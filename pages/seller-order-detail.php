<?php
require_once __DIR__.'/../includes/authorization.php';
require_role('seller');
require_once __DIR__.'/../includes/Repositories/OrderRepository.php';

$repo=new OrderRepository();$sellerId=(int)$_SESSION['user_id'];$orderId=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$orderId){http_response_code(400);exit('Pesanan tidak valid.');}
$order=$repo->sellerOrder((int)$orderId,$sellerId);
$items=$order?$repo->sellerOrderItems((int)$orderId,$sellerId):[];
if(!$order||!$items){http_response_code(404);exit('Pesanan tidak ditemukan.');}

$transitions=['requested'=>['accepted','cancelled'],'accepted'=>['completed','cancelled'],'completed'=>[],'cancelled'=>[]];
$statusLabels=['requested'=>'Menunggu pembayaran','accepted'=>'Sedang diproses','completed'=>'Selesai','cancelled'=>'Dibatalkan'];
$paymentLabels=['unpaid'=>'Belum dibayar','pending'=>'Menunggu pembayaran','paid'=>'Sudah dibayar','failed'=>'Gagal','expired'=>'Kedaluwarsa','cancelled'=>'Dibatalkan'];
$errors=[];

if(is_post()){
 if(!verify_csrf()){flash('error','Sesi formulir tidak valid.');redirect(page_url('seller-order-detail',['id'=>$orderId]));}
 $new=(string)($_POST['status']??'');$current=(string)$order['status'];
 if(!isset($transitions[$current])||!in_array($new,$transitions[$current],true))$errors[]='Perubahan status tidak valid.';
 if(!$errors){
   try{$repo->setStatusSeller((int)$orderId,$sellerId,$new);flash('success','Status pesanan diperbarui.');redirect(page_url('seller-order-detail',['id'=>$orderId]));}
   catch(Throwable $e){$errors[]=$e->getMessage();}
 }
}
$paymentStatus=(string)($order['payment_status']??'unpaid');
$available=$transitions[$order['status']]??[];
if($paymentStatus!=='paid')$available=array_values(array_filter($available,static fn(string $s):bool=>!in_array($s,['accepted','completed'],true)));
$localNo=(int)$order['local_order_no'];
$pageTitle='Detail Pesanan #'.$localNo;require __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="<?=e(APP_URL)?>/assets/css/order-review-polish.css"><link rel="stylesheet" href="<?=e(APP_URL)?>/assets/css/seller-orders-v2.css">
<section class="section-padding page-section seller-orders-page seller-orders-v2"><div class="container">
<div class="page-toolbar"><?=back_link('seller-orders','Kembali ke daftar pesanan')?></div>
<?php if($errors):?><div class="alert alert-danger"><?=implode('<br>',array_map('e',$errors))?></div><?php endif;?>
<article class="glass-card seller-order-card seller-order-card-v2">
<div class="seller-order-card-top"><div><span class="seller-order-kicker">Pesanan #<?=$localNo?></span><h3><?=e((string)$order['buyer_name'])?></h3><div class="seller-order-contact"><span><i class="bi bi-person"></i>@<?=e((string)$order['buyer_username'])?></span><span><i class="bi bi-whatsapp"></i><?=e((string)$order['buyer_whatsapp'])?></span></div></div><div class="seller-order-card-badges"><span class="order-status-badge"><?=e($statusLabels[$order['status']]??$order['status'])?></span><span class="seller-payment-badge"><?=e($paymentLabels[$paymentStatus]??$paymentStatus)?></span></div></div>
<?php if(!empty($order['note'])):?><div class="seller-order-note-v2"><i class="bi bi-sticky"></i><div><small>Catatan customer</small><p><?=nl2br(e((string)$order['note']))?></p></div></div><?php endif;?>
<div class="seller-products-box"><div class="seller-products-box-head"><span>Produk</span><strong><?=count($items)?> item</strong></div><?php foreach($items as $it):?><div class="seller-product-line"><div class="seller-product-line-icon"><i class="bi bi-box-seam"></i></div><div class="seller-product-line-copy"><strong><?=e((string)$it['product_name'])?></strong><span><?=(int)$it['quantity']?> × <?=format_price((int)$it['unit_price'])?></span></div><strong class="seller-product-line-total"><?=format_price((int)$it['line_total'])?></strong></div><?php endforeach;?></div>
<div class="seller-order-bottom"><div class="seller-order-total-v2"><small>Total</small><strong><?=format_price((int)$order['total'])?></strong></div>
<?php if($available):?><form class="seller-order-status-form" method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><div class="seller-status-controls"><select class="form-select" name="status"><?php foreach($available as $st):?><option value="<?=e($st)?>"><?=e($statusLabels[$st]??$st)?></option><?php endforeach;?></select><button class="btn btn-primary">Simpan</button></div></form><?php elseif($paymentStatus!=='paid'&&$order['status']==='requested'):?><div class="seller-wait-payment"><i class="bi bi-hourglass-split"></i><span><strong>Menunggu pembayaran</strong><small>Seller baru dapat menerima setelah status paid.</small></span></div><?php endif;?>
</div>
</article></div></section>
<?php require __DIR__.'/../includes/footer.php';?>
