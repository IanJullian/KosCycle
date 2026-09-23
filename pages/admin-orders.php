<?php
require_once __DIR__.'/../includes/authorization.php';require_role('admin');
require_once __DIR__.'/../includes/Repositories/OrderRepository.php';
require_once __DIR__.'/../includes/Repositories/UserRepository.php';
require_once __DIR__.'/../includes/pagination.php';

$repo=new OrderRepository();$transitions=['requested'=>['accepted','cancelled'],'accepted'=>['completed','cancelled'],'completed'=>[],'cancelled'=>[]];$errors=[];
if(is_post()){
 if(!verify_csrf()){flash('error','Sesi formulir tidak valid.');redirect(page_url('admin-orders'));}
 $id=filter_input(INPUT_POST,'order_id',FILTER_VALIDATE_INT);$new=(string)($_POST['status']??'');
 $st=$id?db()->prepare('SELECT status FROM orders WHERE id=?'):null;if($st)$st->execute([$id]);$cur=$st?$st->fetchColumn():false;
 if(!$id||!$cur||!isset($transitions[$cur])||!in_array($new,$transitions[$cur],true))$errors[]='Transisi status tidak valid.';
 else{try{$repo->setStatus((int)$id,$new);flash('success','Status transaksi diperbarui.');redirect(page_url('admin-orders'));}catch(Throwable $e){$errors[]=$e->getMessage();}}
}
$filters=[
 'q'=>trim((string)($_GET['q']??'')),
 'buyer_id'=>(int)($_GET['buyer_id']??0),
 'seller_id'=>(int)($_GET['seller_id']??0),
 'status'=>trim((string)($_GET['status']??'')),
 'payment_status'=>trim((string)($_GET['payment_status']??'')),
];
$total=$repo->adminCount($filters);$pager=pager_meta($total,(int)($_GET['p']??1),12);
$orders=$repo->adminPage($filters,$pager['per_page'],$pager['offset']);
$users=(new UserRepository())->all();$buyers=array_values(array_filter($users,fn($u)=>$u['role']==='customer'));$sellers=array_values(array_filter($users,fn($u)=>$u['role']==='seller'));
$pageTitle='Kelola Transaksi';require __DIR__.'/../includes/header.php';
?>
<section class="section-padding page-section"><div class="container">
<div class="page-toolbar"><?=back_link('admin-dashboard','Kembali ke dashboard admin')?></div>
<div class="section-heading mb-4"><span class="eyebrow">Admin</span><h2>Kelola <em>transaksi.</em></h2><p class="text-muted">Filter berdasarkan akun, status pesanan, dan status pembayaran.</p></div>

<form method="get" class="glass-card kc-filter-bar">
<input type="hidden" name="page" value="admin-orders">
<div><label class="form-label">Cari</label><input class="form-control" name="q" value="<?=e($filters['q'])?>" placeholder="Customer, seller, produk..."></div>
<div><label class="form-label">Customer</label><select class="form-select" name="buyer_id"><option value="">Semua customer</option><?php foreach($buyers as $u):?><option value="<?=(int)$u['id']?>" <?=$filters['buyer_id']===(int)$u['id']?'selected':''?>><?=e((string)$u['full_name'])?></option><?php endforeach;?></select></div>
<div><label class="form-label">Seller</label><select class="form-select" name="seller_id"><option value="">Semua seller</option><?php foreach($sellers as $u):?><option value="<?=(int)$u['id']?>" <?=$filters['seller_id']===(int)$u['id']?'selected':''?>><?=e((string)($u['shop_name']?:$u['full_name']))?></option><?php endforeach;?></select></div>
<div><label class="form-label">Status</label><select class="form-select" name="status"><option value="">Semua</option><?php foreach(['requested','accepted','completed','cancelled'] as $st):?><option value="<?=$st?>" <?=$filters['status']===$st?'selected':''?>><?=$st?></option><?php endforeach;?></select></div>
<div><label class="form-label">Pembayaran</label><select class="form-select" name="payment_status"><option value="">Semua</option><?php foreach(['unpaid','pending','paid','failed','expired','cancelled'] as $st):?><option value="<?=$st?>" <?=$filters['payment_status']===$st?'selected':''?>><?=$st?></option><?php endforeach;?></select></div>
<div class="kc-filter-actions"><button class="btn btn-primary">Terapkan</button><a class="btn btn-ghost" href="<?=e(page_url('admin-orders'))?>">Reset</a></div>
</form>

<div class="kc-result-meta"><?=$total?> transaksi ditemukan · halaman <?=$pager['page']?>/<?=$pager['pages']?></div>
<?php if($errors):?><div class="alert alert-danger"><?=implode('<br>',array_map('e',$errors))?></div><?php endif;?>
<?php foreach($orders as $o):?><article class="glass-card p-4 mb-3"><div class="d-flex justify-content-between gap-3 flex-wrap"><div><span class="kc-order-local">Pesanan #<?=(int)$o['local_order_no']?> milik <a class="kc-account-link" href="<?=e(page_url('admin-orders',['buyer_id'=>$o['buyer_id']]))?>"><?=e($o['buyer_name'])?></a></span><small class="d-block text-muted">@<?=e($o['buyer_username'])?> · <?=e((string)$o['created_at'])?></small><small class="d-block mt-1">Seller: <a class="kc-account-link" href="<?=e(page_url('admin-orders',['seller_id'=>$o['seller_id']]))?>"><?=e((string)$o['seller_names'])?></a></small></div><div><span class="badge text-bg-light"><?=e($o['status'])?></span> <span class="badge text-bg-light"><?=e($o['payment_status'])?></span></div></div><div class="mt-3"><strong><?=format_price((int)$o['total'])?></strong> · <?=(int)$o['item_count']?> item</div><?php if($transitions[$o['status']]??[]):?><form class="d-flex gap-2 mt-3" method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="order_id" value="<?=(int)$o['id']?>"><select class="form-select" name="status"><?php foreach($transitions[$o['status']] as $st):?><option value="<?=e($st)?>"><?=e($st)?></option><?php endforeach;?></select><button class="btn btn-primary">Simpan status</button></form><?php endif;?></article><?php endforeach;?>
<?=pager_render($pager)?>
</div></section>
<?php require __DIR__.'/../includes/footer.php';?>
