<?php
require_once __DIR__.'/../includes/authorization.php';require_role('admin');
require_once __DIR__.'/../includes/Repositories/ProductRepository.php';
require_once __DIR__.'/../includes/Repositories/UserRepository.php';
require_once __DIR__.'/../includes/pagination.php';

$repo=new ProductRepository();$errors=[];
if(is_post()){
 if(!verify_csrf()){flash('error','Sesi formulir tidak valid.');redirect(page_url('admin-products'));}
 $id=filter_input(INPUT_POST,'product_id',FILTER_VALIDATE_INT);$action=$_POST['action']??'';
 if(!$id)$errors[]='Produk tidak valid.';
 elseif($action==='archive'){$repo->archiveAdmin((int)$id);flash('success','Produk diarsipkan.');redirect(page_url('admin-products'));}
 elseif($action==='restore'){$repo->restoreAdmin((int)$id);flash('success','Produk diaktifkan.');redirect(page_url('admin-products'));}
}

$filters=[
 'q'=>trim((string)($_GET['q']??'')),
 'seller_id'=>(int)($_GET['seller_id']??0),
 'status'=>trim((string)($_GET['status']??'')),
 'category'=>trim((string)($_GET['category']??'')),
];
$total=$repo->adminCount($filters);$pager=pager_meta($total,(int)($_GET['p']??1),15);
$products=$repo->adminPage($filters,$pager['per_page'],$pager['offset']);
$sellers=(new UserRepository())->activeSellers();
$categories=db()->query("SELECT name FROM categories WHERE status='active' ORDER BY name")->fetchAll();
$pageTitle='Kelola Produk';require __DIR__.'/../includes/header.php';
?>
<section class="section-padding page-section"><div class="container">
<div class="page-toolbar d-flex justify-content-between align-items-center gap-2 flex-wrap"><?=back_link('admin-dashboard','Kembali ke dashboard admin')?><a class="btn btn-primary btn-sm" href="<?=e(page_url('admin-product-form'))?>">Tambah produk</a></div>
<div class="section-heading mb-4"><span class="eyebrow">Admin</span><h2>Kelola <em>produk.</em></h2><p class="text-muted">Cari, filter, paginate, dan buka seluruh produk milik seller tertentu.</p></div>

<form method="get" class="glass-card kc-filter-bar">
<input type="hidden" name="page" value="admin-products">
<div><label class="form-label">Cari</label><input class="form-control" name="q" value="<?=e($filters['q'])?>" placeholder="Produk, seller, kota..."></div>
<div><label class="form-label">Seller</label><select class="form-select" name="seller_id"><option value="">Semua seller</option><?php foreach($sellers as $s):?><option value="<?=(int)$s['id']?>" <?=$filters['seller_id']===(int)$s['id']?'selected':''?>><?=e((string)($s['shop_name']?:$s['full_name']))?></option><?php endforeach;?></select></div>
<div><label class="form-label">Status</label><select class="form-select" name="status"><option value="">Semua status</option><?php foreach(['available','reserved','sold','archived'] as $st):?><option value="<?=$st?>" <?=$filters['status']===$st?'selected':''?>><?=$st?></option><?php endforeach;?></select></div>
<div><label class="form-label">Kategori</label><select class="form-select" name="category"><option value="">Semua kategori</option><?php foreach($categories as $c):?><option value="<?=e($c['name'])?>" <?=$filters['category']===$c['name']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select></div>
<div class="kc-filter-actions"><button class="btn btn-primary">Terapkan</button><a class="btn btn-ghost" href="<?=e(page_url('admin-products'))?>">Reset</a></div>
</form>

<div class="kc-result-meta"><?=$total?> produk ditemukan · halaman <?=$pager['page']?>/<?=$pager['pages']?></div>
<?php if($errors):?><div class="alert alert-danger"><?=implode('<br>',array_map('e',$errors))?></div><?php endif;?>
<div class="glass-card p-3 table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Produk</th><th>Seller</th><th>Harga</th><th>Stok</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
<?php foreach($products as $p):?><tr><td><strong><?=e($p['name'])?></strong><div class="small text-muted"><?=e($p['category'])?> · <?=e($p['city'])?></div></td><td><a class="kc-account-link" href="<?=e(page_url('admin-products',['seller_id'=>$p['seller_user_id']]))?>"><?=e($p['seller_name'])?></a><small class="d-block text-muted">@<?=e($p['seller_username'])?></small></td><td><?=format_price((int)$p['price'])?></td><td><?=(int)$p['stock']?></td><td><?=e($p['status'])?></td><td><div class="d-flex gap-2"><a class="btn btn-soft btn-sm" href="<?=e(page_url('admin-product-edit',['id'=>$p['id']]))?>">Edit</a><?php if($p['status']==='archived'):?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="product_id" value="<?=(int)$p['id']?>"><button class="btn btn-primary btn-sm">Aktifkan</button></form><?php else:?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="product_id" value="<?=(int)$p['id']?>"><button class="btn btn-outline-danger btn-sm">Arsipkan</button></form><?php endif;?></div></td></tr><?php endforeach;?>
</tbody></table></div>
<?=pager_render($pager)?>
</div></section>
<?php require __DIR__.'/../includes/footer.php';?>
