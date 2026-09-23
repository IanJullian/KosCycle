<?php
require_once __DIR__.'/../includes/authorization.php';
require_role('seller');
require_once __DIR__.'/../includes/Repositories/OrderRepository.php';
require_once __DIR__.'/../includes/pagination.php';

$repo=new OrderRepository();$sellerId=(int)$_SESSION['user_id'];
$total=$repo->sellerCount($sellerId);$pager=pager_meta($total,(int)($_GET['p']??1),8);
$orders=$repo->sellerOrdersPage($sellerId,$pager['per_page'],$pager['offset']);
$stats=$repo->sellerDashboardStats($sellerId);
$statusLabels=['requested'=>'Menunggu pembayaran','accepted'=>'Sedang diproses','completed'=>'Selesai','cancelled'=>'Dibatalkan'];
$paymentLabels=['unpaid'=>'Belum dibayar','pending'=>'Menunggu pembayaran','paid'=>'Sudah dibayar','failed'=>'Gagal','expired'=>'Kedaluwarsa','cancelled'=>'Dibatalkan'];
$pageTitle='Pesanan Seller';require __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="<?=e(APP_URL)?>/assets/css/order-review-polish.css"><link rel="stylesheet" href="<?=e(APP_URL)?>/assets/css/seller-orders-v2.css">
<section class="section-padding page-section seller-orders-page seller-orders-v2"><div class="container">
<div class="page-toolbar"><?=back_link('seller-dashboard','Kembali ke dashboard')?></div>
<div class="seller-orders-heading"><div><span class="eyebrow">Seller center</span><h2>Kelola <em>pesanan.</em></h2><p class="text-muted">Nomor pesanan mengikuti urutan transaksi milik customer.</p></div><a class="btn btn-soft" data-no-preloader="1" href="<?=e(page_url('chat',['new'=>'support']))?>">Hubungi admin</a></div>
<div class="seller-order-stats"><div class="glass-card seller-stat-card"><span><i class="bi bi-credit-card"></i></span><div><small>Siap diproses</small><strong><?=$stats['ready']?></strong></div></div><div class="glass-card seller-stat-card"><span><i class="bi bi-box-seam"></i></span><div><small>Diproses</small><strong><?=$stats['processing']?></strong></div></div><div class="glass-card seller-stat-card"><span><i class="bi bi-check2-circle"></i></span><div><small>Selesai</small><strong><?=$stats['completed']?></strong></div></div><div class="glass-card seller-stat-card"><span><i class="bi bi-receipt"></i></span><div><small>Total order</small><strong><?=$stats['total']?></strong></div></div></div>
<?php if(!$orders):?><div class="glass-card seller-order-empty"><h3>Belum ada pesanan</h3></div><?php endif;?>
<div class="seller-orders-list">
<?php foreach($orders as $o):$ps=(string)($o['payment_status']??'unpaid');?>
<article class="glass-card seller-order-card seller-order-card-v2"><div class="seller-order-card-top"><div><span class="seller-order-kicker">Pesanan #<?=(int)$o['local_order_no']?> · <?=e((string)$o['buyer_name'])?></span><h3><?=format_price((int)$o['total'])?></h3><div class="seller-order-contact"><span><i class="bi bi-person"></i>@<?=e((string)$o['buyer_username'])?></span><span><i class="bi bi-calendar3"></i><?=e((string)$o['created_at'])?></span></div></div><div class="seller-order-card-badges"><span class="order-status-badge"><?=e($statusLabels[$o['status']]??$o['status'])?></span><span class="seller-payment-badge"><?=e($paymentLabels[$ps]??$ps)?></span></div></div>
<?php if(!empty($o['note'])):?><div class="seller-order-note-v2"><i class="bi bi-sticky"></i><div><small>Catatan customer</small><p><?=nl2br(e((string)$o['note']))?></p></div></div><?php endif;?>
<div class="seller-order-bottom"><div class="seller-order-total-v2"><small>Total pesanan</small><strong><?=format_price((int)$o['total'])?></strong></div><a class="btn btn-primary" href="<?=e(page_url('seller-order-detail',['id'=>$o['id']]))?>">Lihat detail</a></div>
</article>
<?php endforeach;?>
</div><?=pager_render($pager)?>
</div></section>
<?php require __DIR__.'/../includes/footer.php';?>
