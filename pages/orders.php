<?php
require_once __DIR__ . '/../includes/authorization.php';
require_role('customer');
require_once __DIR__ . '/../includes/Repositories/OrderRepository.php';
$repo=new OrderRepository(); $userId=(int)$_SESSION['user_id'];
$orders=$repo->byBuyer($userId);
$selected=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
$details=$selected?$repo->items((int)$selected,$userId):[];
$pageTitle='Riwayat Pesanan'; require __DIR__ . '/../includes/header.php';
?>
<section class="section-padding page-section"><div class="container"><div class="page-toolbar"><?=back_link('customer-dashboard','Kembali ke dashboard customer')?></div><div class="section-heading mb-4"><span class="eyebrow">Pesanan</span><h2>Riwayat <em>transaksi.</em></h2></div>
<div class="row g-4"><div class="col-lg-7"><?php if(!$orders): ?><div class="glass-card p-5 text-center"><h3>Belum ada pesanan</h3></div><?php endif; ?><?php foreach($orders as $o): ?><a class="text-decoration-none text-dark" href="<?= e(page_url('orders',['id'=>$o['id']])) ?>"><div class="glass-card p-3 mb-3"><div class="d-flex justify-content-between"><strong>#<?= (int)$o['id'] ?></strong><span class="badge text-bg-light"><?= e($o['status']) ?></span></div><small class="text-muted"><?= e($o['created_at']) ?> · <?= (int)$o['item_count'] ?> item</small><div class="mt-2"><strong><?= format_price((int)$o['total']) ?></strong></div></div></a><?php endforeach; ?></div>
<div class="col-lg-5"><?php if($details): ?><div class="glass-card p-4"><span class="eyebrow">Detail #<?= (int)$selected ?></span><h3 class="mt-2"><?= e($details[0]['status']) ?></h3><?php if($details[0]['note']): ?><p class="text-muted"><?= nl2br(e($details[0]['note'])) ?></p><?php endif; ?><?php foreach($details as $d): ?><div class="border-top py-3"><div class="d-flex justify-content-between gap-3 align-items-start"><div><strong><?= e($d['product_name']) ?></strong><div><?= (int)$d['quantity'] ?> × <?= format_price((int)$d['unit_price']) ?></div><small class="text-muted">Seller: <?= e($d['seller_name']) ?></small></div><?php if($d['status']==='completed'): ?><a class="btn btn-soft btn-sm" href="<?=e(page_url('review',['product_id'=>$d['product_id']]))?>"><i class="bi bi-star me-1"></i>Ulasan</a><?php endif; ?></div></div><?php endforeach; ?></div><?php endif; ?></div></div></div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
