<?php
require_once __DIR__.'/../includes/authorization.php';require_role('seller');
require_once __DIR__.'/../includes/Repositories/ProductRepository.php';
require_once __DIR__.'/../includes/pagination.php';
$repo=new ProductRepository();$sellerId=(int)$_SESSION['user_id'];
if(is_post()){
 if(!verify_csrf()){flash('error','Sesi formulir tidak valid.');redirect(page_url('seller-products'));}
 $id=filter_input(INPUT_POST,'product_id',FILTER_VALIDATE_INT);$action=$_POST['action']??'';
 if(!$id){flash('error','Produk tidak valid.');redirect(page_url('seller-products'));}
 if($action==='archive'){$repo->archive((int)$id,$sellerId);flash('success','Produk diarsipkan.');}
 elseif($action==='restore'){$repo->restore((int)$id,$sellerId);flash('success','Produk diaktifkan kembali.');}
 elseif($action==='stock'){$qty=filter_input(INPUT_POST,'quantity',FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);if($qty===false||$qty===null)flash('error','Stok harus 0 atau lebih.');else{$repo->setStock((int)$id,$sellerId,(int)$qty);flash('success','Stok diperbarui.');}}
 redirect(page_url('seller-products'));
}
$total=$repo->sellerCount($sellerId);$pager=pager_meta($total,(int)($_GET['p']??1),9);
$products=$repo->bySellerPage($sellerId,$pager['per_page'],$pager['offset']);
$pageTitle='Produk Seller';require __DIR__.'/../includes/header.php';
?>
<section class="section-padding page-section"><div class="container">
<div class="page-toolbar d-flex justify-content-between align-items-center"><?=back_link('seller-dashboard','Kembali ke dashboard')?><a class="btn btn-primary btn-sm" href="<?=e(page_url('seller-product-form'))?>">Tambah produk</a></div>
<div class="section-heading mb-4"><span class="eyebrow">Seller</span><h2>Produk <em>kamu.</em></h2><p class="text-muted"><?=$total?> produk · maksimal 9 produk per halaman.</p></div>
<?php if(!$products):?><div class="glass-card empty-state"><h2>Belum ada produk</h2></div><?php endif;?>
<div class="row g-4"><?php foreach($products as $p):?><div class="col-md-6 col-lg-4"><div class="glass-card p-4 h-100"><div class="d-flex justify-content-between"><span class="badge text-bg-light"><?=e($p['status'])?></span><small class="text-muted"><?=e($p['category'])?></small></div><?php if($p['image_path']):?><img class="product-thumb mt-3" src="<?=e(APP_URL.'/'.$p['image_path'])?>" alt="<?=e($p['name'])?>" style="object-position:<?=(int)($p['image_crop_x']??50)?>% <?=(int)($p['image_crop_y']??50)?>%"><?php else:?><div class="product-thumb product-thumb-empty mt-3"><i class="bi bi-box-seam"></i></div><?php endif;?><h3 class="h5 mt-3"><?=e($p['name'])?></h3><strong><?=format_price((int)$p['price'])?></strong><p class="text-muted">Stok: <?=(int)$p['stock']?></p><div class="d-flex gap-2 flex-wrap"><a class="btn btn-soft btn-sm" href="<?=e(page_url('seller-product-form',['id'=>$p['id']]))?>">Edit</a><?php if($p['status']==='archived'):?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="product_id" value="<?=(int)$p['id']?>"><button class="btn btn-soft btn-sm">Aktifkan</button></form><?php else:?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="product_id" value="<?=(int)$p['id']?>"><button class="btn btn-outline-danger btn-sm">Arsipkan</button></form><?php endif;?></div><form class="d-flex gap-2 mt-3" method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="stock"><input type="hidden" name="product_id" value="<?=(int)$p['id']?>"><input class="form-control form-control-sm" type="number" min="0" name="quantity" value="<?=(int)$p['stock']?>"><button class="btn btn-primary btn-sm">Simpan stok</button></form></div></div><?php endforeach;?></div>
<?=pager_render($pager)?>
</div></section>
<?php require __DIR__.'/../includes/footer.php';?>
