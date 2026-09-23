<?php
require_once __DIR__.'/../includes/authorization.php';
require_role('seller');
require_once __DIR__.'/../includes/Repositories/ProductRepository.php';
require_once __DIR__.'/../includes/Repositories/CategoryRepository.php';
require_once __DIR__.'/../includes/validation.php';

$repo=new ProductRepository();
$categories=(new CategoryRepository())->active();
$sellerId=(int)$_SESSION['user_id'];
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
$editing=(bool)$id;
$existing=$editing?$repo->find((int)$id,false):null;

if($editing&&(!$existing||(int)$existing['seller_id']!==$sellerId)){
    http_response_code(404);
    exit('Produk tidak ditemukan.');
}

$data=$existing?[
    'name'=>$existing['name'],
    'category'=>$existing['category'],
    'description'=>$existing['description'],
    'price'=>$existing['price'],
    'condition_label'=>$existing['condition_label'],
    'city'=>$existing['city'],
    'stock'=>$existing['stock'],
    'status'=>$existing['status']
]:[
    'name'=>'','category'=>'','description'=>'','price'=>'','condition_label'=>'layak pakai','city'=>'','stock'=>0,'status'=>'available'
];
$errors=[];

if(is_post()){
    if(!verify_csrf())$errors[]='Sesi formulir tidak valid.';

    $data=[
        'name'=>trim($_POST['name']??''),
        'category'=>trim($_POST['category']??''),
        'description'=>trim($_POST['description']??''),
        'price'=>trim($_POST['price']??''),
        'condition_label'=>trim($_POST['condition_label']??''),
        'city'=>trim($_POST['city']??''),
        'stock'=>trim($_POST['stock']??''),
        'status'=>$_POST['status']??'available'
    ];

    $errors=array_merge($errors,validate_product_input($data));
    if(!in_array($data['status'],['available','reserved','sold','archived'],true))$errors[]='Status produk tidak valid.';

    $catNames=array_column($categories,'name');
    if(!$categories)$errors[]='Belum ada kategori aktif. Admin harus membuat kategori terlebih dahulu.';
    elseif(!in_array($data['category'],$catNames,true))$errors[]='Kategori tidak valid.';

    if(!$errors){
        try{
            if($editing){
                $repo->update((int)$id,$sellerId,$data);
                $repo->setStock((int)$id,$sellerId,(int)$data['stock']);
                handle_product_uploads((int)$id,$sellerId);
                flash('success','Produk, stok, dan gambar berhasil diperbarui.');
            }else{
                $newId=$repo->create(['seller_id'=>$sellerId]+$data);

                // Hardening bug stok: sinkronkan lagi inventory setelah create
                // sehingga nilai yang baru diinput langsung muncul di Produk Saya.
                $repo->setStock($newId,$sellerId,(int)$data['stock']);

                try{
                    handle_product_uploads($newId,$sellerId);
                }catch(Throwable $uploadError){
                    $repo->archive($newId,$sellerId);
                    throw $uploadError;
                }
                flash('success','Produk berhasil dibuat dengan stok '.$data['stock'].' dan masuk ke katalog.');
            }
            redirect(page_url('seller-products'));
        }catch(Throwable $e){
            $errors[]=$e->getMessage() ?: 'Produk gagal disimpan. Pastikan database.mysql sudah dijalankan dan folder uploads/products dapat ditulis.';
        }
    }
}

function normalize_upload_files(array $files): array
{
    $normalized=[];
    $names=$files['name']??[];
    if(!is_array($names))return [];

    foreach($names as $i=>$name){
        if((string)$name==='')continue;
        $normalized[]=[
            'name'=>$name,
            'type'=>$files['type'][$i]??'',
            'tmp_name'=>$files['tmp_name'][$i]??'',
            'error'=>$files['error'][$i]??UPLOAD_ERR_NO_FILE,
            'size'=>$files['size'][$i]??0,
        ];
    }
    return $normalized;
}

function handle_product_uploads(int $productId,int $sellerId):void
{
    $check=db()->prepare('SELECT id FROM products WHERE id=? AND seller_id=? LIMIT 1');
    $check->execute([$productId,$sellerId]);
    if(!$check->fetchColumn())throw new RuntimeException('Ownership produk tidak valid.');

    $files=normalize_upload_files($_FILES['images']??[]);
    if(!$files)return;

    $countStmt=db()->prepare('SELECT COUNT(*) FROM product_images WHERE product_id=?');
    $countStmt->execute([$productId]);
    $existingCount=(int)$countStmt->fetchColumn();

    if($existingCount+count($files)>5){
        throw new RuntimeException('Maksimal 5 gambar per produk. Hapus/kurangi pilihan gambar lalu coba lagi.');
    }

    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $dir=__DIR__.'/../uploads/products';
    if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Folder upload tidak tersedia.');

    $sort=$existingCount;
    foreach($files as $file){
        if((int)$file['error']!==UPLOAD_ERR_OK)throw new RuntimeException('Salah satu upload gambar gagal.');
        if((int)$file['size']>5*1024*1024)throw new RuntimeException('Ukuran setiap gambar maksimal 5 MB.');

        $tmp=(string)$file['tmp_name'];
        $finfo=new finfo(FILEINFO_MIME_TYPE);
        $mime=$finfo->file($tmp);
        $imageInfo=@getimagesize($tmp);
        if(!isset($allowed[$mime])||!$imageInfo)throw new RuntimeException('Semua file harus gambar JPG, PNG, atau WEBP.');

        $name=bin2hex(random_bytes(16)).'.'.$allowed[$mime];
        $path=$dir.'/'.$name;
        if(!move_uploaded_file($tmp,$path))throw new RuntimeException('Gambar gagal disimpan.');

        db()->prepare('INSERT INTO product_images(product_id,image_path,sort_order) VALUES(?,?,?)')
            ->execute([$productId,'uploads/products/'.$name,$sort]);
        $sort++;
    }
}

$pageTitle=$editing?'Edit Produk':'Tambah Produk';
require __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/ux-v3.css">
<section class="auth-section seller-product-form-page">
    <div class="auth-shell">
        <div class="auth-intro">
            <div class="page-toolbar"><?=back_link('seller-products','Kembali ke produk saya')?></div>
            <span class="eyebrow">Seller</span>
            <h1><?=$editing?'Edit':'Tambah'?> <em>produk.</em></h1>
            <p>Tambahkan maksimal 5 foto. Semua foto ditampilkan dalam rasio 1:1 agar katalog rapi di desktop maupun mobile.</p>
        </div>

        <div class="auth-panel glass-card">
            <?php if($errors):?><div class="alert alert-danger"><?=implode('<br>',array_map('e',$errors))?></div><?php endif;?>
            <form method="post" enctype="multipart/form-data" id="product-form">
                <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                <label class="form-label">Nama produk</label>
                <input class="form-control mb-3" name="name" value="<?=e((string)$data['name'])?>" required>

                <label class="form-label">Kategori</label>
                <select class="form-select mb-3" name="category" required>
                    <option value="">Pilih kategori</option>
                    <?php foreach($categories as $c):?><option value="<?=e($c['name'])?>" <?=$data['category']===$c['name']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?>
                </select>

                <label class="form-label">Deskripsi</label>
                <textarea class="form-control mb-3" rows="5" name="description"><?=e((string)$data['description'])?></textarea>

                <div class="row g-3">
                    <div class="col-6"><label class="form-label">Harga</label><input class="form-control" type="number" min="0" name="price" value="<?=e((string)$data['price'])?>" required></div>
                    <div class="col-6"><label class="form-label">Stok</label><input class="form-control" type="number" min="0" name="stock" value="<?=e((string)$data['stock'])?>" required></div>
                    <div class="col-6"><label class="form-label">Kondisi</label><input class="form-control" name="condition_label" value="<?=e((string)$data['condition_label'])?>" required></div>
                    <div class="col-6"><label class="form-label">Kota</label><input class="form-control" name="city" value="<?=e((string)$data['city'])?>" required></div>
                    <div class="col-12"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach(['available','reserved','sold','archived'] as $s):?><option value="<?=$s?>" <?=$data['status']===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></div>

                    <?php if($editing && !empty($existing['images'])): ?>
                        <div class="col-12">
                            <label class="form-label">Gambar tersimpan</label>
                            <div class="seller-image-existing-grid">
                                <?php foreach($existing['images'] as $img): ?>
                                    <img src="<?=e(APP_URL.'/'.ltrim((string)$img['image_path'],'/'))?>" alt="Foto produk">
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="col-12">
                        <label class="form-label">Foto produk (maks. 5)</label>
                        <input class="form-control" id="product-images" type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp">
                        <small class="text-muted">JPG/PNG/WEBP, maksimal 5 MB per foto. Tampilan katalog otomatis 1:1 dengan crop visual.</small>
                        <div class="seller-image-preview-grid mt-3" id="seller-image-preview"></div>
                    </div>
                </div>

                <button class="btn btn-primary w-100 mt-4"><?=$editing?'Simpan perubahan':'Tambah ke katalog'?> <i class="bi bi-arrow-right ms-2"></i></button>
            </form>
        </div>
    </div>
</section>
<script>
(() => {
    const input = document.getElementById('product-images');
    const preview = document.getElementById('seller-image-preview');
    if (!input || !preview) return;

    input.addEventListener('change', () => {
        preview.innerHTML = '';
        const files = Array.from(input.files || []);
        if (files.length > 5) {
            alert('Maksimal 5 gambar dalam satu produk.');
            input.value = '';
            return;
        }
        files.forEach((file, index) => {
            const url = URL.createObjectURL(file);
            const item = document.createElement('div');
            item.className = 'seller-image-preview-item';
            const img = document.createElement('img');
            img.src = url;
            img.alt = `Preview ${index + 1}`;
            img.onload = () => URL.revokeObjectURL(url);
            const badge = document.createElement('span');
            badge.textContent = index === 0 ? 'Utama' : String(index + 1);
            item.append(img, badge);
            preview.appendChild(item);
        });
    });
})();
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
