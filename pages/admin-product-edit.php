<?php
require_once __DIR__ . '/../includes/authorization.php';
require_role('admin');
require_once __DIR__ . '/../includes/Repositories/ProductRepository.php';
require_once __DIR__ . '/../includes/Repositories/CategoryRepository.php';
require_once __DIR__ . '/../includes/validation.php';
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$id){http_response_code(400);exit('Produk tidak valid.');}
$repo=new ProductRepository();$product=$repo->find((int)$id,false);
if(!$product){http_response_code(404);exit('Produk tidak ditemukan.');}
$categories=(new CategoryRepository())->all();
$errors=[];
if(is_post()){
 if(!verify_csrf())$errors[]='Sesi formulir tidak valid.';
 $data=['name'=>trim($_POST['name']??''),'category'=>trim($_POST['category']??''),'description'=>trim($_POST['description']??''),'price'=>trim($_POST['price']??''),'condition_label'=>trim($_POST['condition_label']??''),'city'=>trim($_POST['city']??''),'status'=>$_POST['status']??'available'];
 $errors=array_merge($errors,validate_product_input($data));
 if(!in_array($data['status'],['available','reserved','sold','archived'],true))$errors[]='Status produk tidak valid.';
 $catNames=array_column(array_filter($categories,fn($c)=>$c['status']==='active'),'name');
 if(!in_array($data['category'],$catNames,true))$errors[]='Kategori harus aktif.';
 if(!$errors){
   try{
     $repo->updateAdmin((int)$id,$data);
     $stock=filter_input(INPUT_POST,'stock',FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
     if($stock===false||$stock===null)throw new RuntimeException('Stok tidak valid.');
     $repo->setStockAdmin((int)$id,(int)$stock);
     flash('success','Produk admin berhasil diperbarui.');redirect(page_url('admin-products'));
   }catch(Throwable $e){$errors[]='Produk gagal diperbarui.';}
 }
}
$pageTitle='Edit Produk Admin';require __DIR__.'/../includes/header.php';
?>
<section class="auth-section"><div class="auth-shell"><div class="auth-intro"><div class="page-toolbar"><?=back_link('admin-products','Kembali ke produk admin')?></div><span class="eyebrow">Admin</span><h1>Edit <em>produk.</em></h1><p>Admin dapat mengubah data produk tanpa mengambil alih ownership seller.</p></div><div class="auth-panel glass-card"><?php if($errors):?><div class="alert alert-danger"><?=implode('<br>',array_map('e',$errors))?></div><?php endif;?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
<label class="form-label">Nama</label><input class="form-control mb-3" name="name" value="<?=e($product['name'])?>">
<label class="form-label">Kategori</label><select class="form-select mb-3" name="category"><?php foreach($categories as $c):if($c['status']!=='active')continue;?><option value="<?=e($c['name'])?>" <?=$product['category']===$c['name']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select>
<label class="form-label">Deskripsi</label><textarea class="form-control mb-3" rows="5" name="description"><?=e($product['description']??'')?></textarea>
<div class="row g-3"><div class="col-6"><label class="form-label">Harga</label><input class="form-control" type="number" min="0" name="price" value="<?=intval($product['price'])?>"></div><div class="col-6"><label class="form-label">Stok</label><input class="form-control" type="number" min="0" name="stock" value="<?=intval($product['stock'])?>"></div><div class="col-6"><label class="form-label">Kondisi</label><input class="form-control" name="condition_label" value="<?=e($product['condition_label'])?>"></div><div class="col-6"><label class="form-label">Kota</label><input class="form-control" name="city" value="<?=e($product['city'])?>"></div><div class="col-12"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach(['available','reserved','sold','archived'] as $s):?><option value="<?=$s?>" <?=$product['status']===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></div></div><button class="btn btn-primary w-100 mt-4">Simpan perubahan</button></form></div></div></section>
<?php require __DIR__.'/../includes/footer.php'; ?>
