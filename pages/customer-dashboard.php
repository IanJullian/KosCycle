<?php
require_once __DIR__.'/../includes/authorization.php';
require_role('customer');
require_once __DIR__.'/../includes/Repositories/OrderRepository.php';
require_once __DIR__.'/../includes/Repositories/ProductRepository.php';
require_once __DIR__.'/../includes/pagination.php';

$uid=(int)$_SESSION['user_id'];$orderRepo=new OrderRepository();
$stats=$orderRepo->buyerStats($uid);
$pager=pager_meta($stats['total'],(int)($_GET['p']??1),5);
$recent=$orderRepo->byBuyerPage($uid,$pager['per_page'],$pager['offset']);
$products=(new ProductRepository())->featured(4);
$pageTitle='Dashboard Customer';require __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="<?=e(APP_URL)?>/assets/css/customer-dashboard-polish.css"><link rel="stylesheet" href="<?=e(APP_URL)?>/assets/css/dashboard-v3.css">
<section class="section-padding page-section customer-dashboard-page"><div class="container">
<div class="page-toolbar"><?=back_link('home','Kembali ke beranda')?></div>
<div class="dashboard-top"><div><span class="eyebrow">Customer</span><h1>Dashboard <em>kamu.</em></h1><p class="text-muted">Data diambil langsung dari database setiap halaman dibuka.</p></div><div class="customer-hero-actions"><a class="btn btn-primary" href="<?=e(page_url('marketplace'))?>">Cari barang</a><a class="btn btn-ghost" data-no-preloader="1" href="<?=e(page_url('chat',['new'=>'support']))?>">Bantuan</a></div></div>
<div class="dashboard-grid customer-actions-grid">
<a href="<?=e(page_url('orders'))?>" class="dashboard-action glass-card"><i class="bi bi-bag-check"></i><div><strong><?=$stats['total']?> pesanan</strong><small><?=$stats['open']?> berjalan · <?=$stats['completed']?> selesai</small></div><i class="bi bi-arrow-right arrow"></i></a>
<a href="<?=e(page_url('cart'))?>" class="dashboard-action glass-card"><i class="bi bi-cart3"></i><div><strong>Keranjang</strong><small>Periksa item checkout</small></div><i class="bi bi-arrow-right arrow"></i></a>
<a data-no-preloader="1" href="<?=e(page_url('chat'))?>" class="dashboard-action glass-card"><i class="bi bi-chat-dots"></i><div><strong>Live chat</strong><small>Chat seller dan admin</small></div><i class="bi bi-arrow-right arrow"></i></a>
<a href="<?=e(page_url('profile'))?>" class="dashboard-action glass-card"><i class="bi bi-person"></i><div><strong>Profil saya</strong><small>Kelola akun</small></div><i class="bi bi-arrow-right arrow"></i></a>
</div>
<div class="row g-3 mt-2"><div class="col-6 col-lg-3"><div class="metric-card"><span>Total</span><strong><?=$stats['total']?></strong></div></div><div class="col-6 col-lg-3"><div class="metric-card"><span>Berjalan</span><strong><?=$stats['open']?></strong></div></div><div class="col-6 col-lg-3"><div class="metric-card"><span>Selesai</span><strong><?=$stats['completed']?></strong></div></div><div class="col-6 col-lg-3"><div class="metric-card"><span>Dibatalkan</span><strong><?=$stats['cancelled']?></strong></div></div></div>

<div class="glass-card kc-dashboard-orders">
<div class="d-flex justify-content-between align-items-end gap-2 flex-wrap mb-2"><div><span class="eyebrow">Aktivitas</span><h2 class="h4 mt-1 mb-0">Pesanan terbaru</h2></div><a class="text-link" href="<?=e(page_url('orders'))?>">Buka riwayat</a></div>
<?php if(!$recent):?><p class="text-muted mb-0">Belum ada pesanan.</p><?php endif;?>
<?php foreach($recent as $o):?><div class="kc-dashboard-order"><div><strong>Pesanan #<?=(int)$o['local_order_no']?> · <?=format_price((int)$o['total'])?></strong><small><?=e((string)$o['created_at'])?> · <?=e((string)($o['payment_status']??'unpaid'))?></small></div><a class="btn btn-soft btn-sm" href="<?=e(page_url('order-detail',['id'=>$o['id']]))?>">Detail</a></div><?php endforeach;?>
<?=pager_render($pager)?>
</div>

<div class="customer-products-panel glass-card mt-4"><div class="customer-products-head"><div><span class="eyebrow">Rekomendasi</span><h2>Barang terbaru <em>buat kamu.</em></h2></div><a class="text-link" href="<?=e(page_url('marketplace'))?>">Lihat semua</a></div><div class="customer-product-grid"><?php foreach($products as $p):?><article class="customer-product-card"><a class="customer-product-media" href="<?=e(page_url('product-detail',['id'=>$p['id']]))?>"><?php if(!empty($p['image_path'])):?><img src="<?=e(APP_URL.'/'.ltrim((string)$p['image_path'],'/'))?>" alt="<?=e((string)$p['name'])?>" style="object-position:<?=(int)($p['image_crop_x']??50)?>% <?=(int)($p['image_crop_y']??50)?>%"><?php else:?><span class="customer-product-placeholder"><i class="bi bi-box-seam"></i></span><?php endif;?></a><div class="customer-product-body"><a class="customer-product-title" href="<?=e(page_url('product-detail',['id'=>$p['id']]))?>"><?=e((string)$p['name'])?></a><div class="customer-product-bottom"><strong><?=format_price((int)$p['price'])?></strong><a class="btn btn-soft btn-sm" href="<?=e(page_url('product-detail',['id'=>$p['id']]))?>">Lihat</a></div></div></article><?php endforeach;?></div></div>
</div></section>
<?php require __DIR__.'/../includes/footer.php';?>
