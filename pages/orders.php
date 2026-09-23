<?php
require_once __DIR__.'/../includes/authorization.php';
require_role('customer');
require_once __DIR__.'/../includes/Repositories/OrderRepository.php';
require_once __DIR__.'/../includes/pagination.php';

$repo=new OrderRepository();$uid=(int)$_SESSION['user_id'];
$total=$repo->buyerCount($uid);$pager=pager_meta($total,(int)($_GET['p']??1),8);
$orders=$repo->byBuyerPage($uid,$pager['per_page'],$pager['offset']);
$status=['requested'=>'Menunggu seller','accepted'=>'Diproses','completed'=>'Selesai','cancelled'=>'Dibatalkan'];
$payments=['unpaid'=>'Belum dibayar','pending'=>'Menunggu pembayaran','paid'=>'Sudah dibayar','failed'=>'Gagal','expired'=>'Kedaluwarsa','cancelled'=>'Dibatalkan'];
$pageTitle='Riwayat Pesanan';require __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="<?=e(APP_URL)?>/assets/css/order-review-polish.css">
<section class="section-padding page-section orders-page"><div class="container">
<div class="page-toolbar"><?=back_link('customer-dashboard','Kembali ke dashboard')?></div>
<div class="section-heading mb-4"><span class="eyebrow">Pesanan</span><h2>Riwayat <em>transaksi.</em></h2><p class="text-muted">Nomor pesanan dihitung khusus untuk akunmu, bukan ID global sistem.</p></div>
<div class="kc-result-meta"><?=$total?> pesanan · halaman <?=$pager['page']?> dari <?=$pager['pages']?></div>
<?php if(!$orders):?><div class="glass-card empty-state"><span><i class="bi bi-bag"></i></span><h2>Belum ada pesanan</h2><p>Pesananmu akan muncul di sini.</p></div><?php endif;?>
<div class="row g-3">
<?php foreach($orders as $o):$ps=(string)($o['payment_status']??'unpaid');?>
<div class="col-12"><article class="glass-card p-4 d-flex justify-content-between align-items-center gap-3 flex-wrap">
<div><span class="kc-order-local">Pesanan #<?=(int)$o['local_order_no']?></span><small class="kc-order-internal d-block">Referensi sistem: <?=e(date('Ymd',strtotime((string)$o['created_at'])))?>-<?=(int)$o['id']?></small><strong class="d-block mt-2"><?=format_price((int)$o['total'])?></strong><small class="text-muted"><?=(int)$o['item_count']?> item · <?=e((string)$o['created_at'])?></small></div>
<div class="d-flex align-items-center gap-2 flex-wrap"><span class="badge text-bg-light"><?=e($status[$o['status']]??$o['status'])?></span><span class="badge text-bg-light"><?=e($payments[$ps]??$ps)?></span><a class="btn btn-primary btn-sm" href="<?=e(page_url('order-detail',['id'=>$o['id']]))?>">Detail</a></div>
</article></div>
<?php endforeach;?>
</div>
<?=pager_render($pager)?>
</div></section>
<?php require __DIR__.'/../includes/footer.php';?>
