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
    'name'=>'',
    'category'=>'',
    'description'=>'',
    'price'=>'',
    'condition_label'=>'layak pakai',
    'city'=>'',
    'stock'=>0,
    'status'=>'available'
];

$errors=[];
$cropEnabled=$repo->cropMetadataEnabled();

function normalize_upload_files(array $files):array
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

function safe_unlink_product_image(string $relativePath):void
{
    $uploadsBase=realpath(__DIR__.'/../uploads/products');
    $candidate=realpath(__DIR__.'/../'.ltrim($relativePath,'/'));

    if(!$uploadsBase||!$candidate)return;

    $prefix=rtrim($uploadsBase,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    if(str_starts_with($candidate,$prefix)&&is_file($candidate)){
        @unlink($candidate);
    }
}

function handle_product_uploads(
    ProductRepository $repo,
    int $productId,
    int $sellerId,
    array $newCropX,
    array $newCropY
):array {
    $check=db()->prepare('SELECT id FROM products WHERE id=? AND seller_id=? LIMIT 1');
    $check->execute([$productId,$sellerId]);
    if(!$check->fetchColumn())throw new RuntimeException('Ownership produk tidak valid.');

    $files=normalize_upload_files($_FILES['images']??[]);
    if(!$files)return [];

    $countStmt=db()->prepare('SELECT COUNT(*) FROM product_images WHERE product_id=?');
    $countStmt->execute([$productId]);
    $existingCount=(int)$countStmt->fetchColumn();

    if($existingCount+count($files)>5){
        throw new RuntimeException('Maksimal 5 gambar per produk.');
    }

    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $dir=__DIR__.'/../uploads/products';

    if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir)){
        throw new RuntimeException('Folder upload tidak tersedia.');
    }

    $cropEnabled=$repo->cropMetadataEnabled();
    $createdIds=[];

    foreach($files as $index=>$file){
        if((int)$file['error']!==UPLOAD_ERR_OK){
            throw new RuntimeException('Salah satu upload gambar gagal.');
        }
        if((int)$file['size']>5*1024*1024){
            throw new RuntimeException('Ukuran setiap gambar maksimal 5 MB.');
        }

        $tmp=(string)$file['tmp_name'];
        $finfo=new finfo(FILEINFO_MIME_TYPE);
        $mime=$finfo->file($tmp);
        $imageInfo=@getimagesize($tmp);

        if(!isset($allowed[$mime])||!$imageInfo){
            throw new RuntimeException('Semua file harus JPG, PNG, atau WEBP.');
        }

        $name=bin2hex(random_bytes(16)).'.'.$allowed[$mime];
        $path=$dir.'/'.$name;

        if(!move_uploaded_file($tmp,$path)){
            throw new RuntimeException('Gambar gagal disimpan.');
        }

        $sort=$existingCount+$index;
        $x=max(0,min(100,(int)($newCropX[$index]??50)));
        $y=max(0,min(100,(int)($newCropY[$index]??50)));

        if($cropEnabled){
            $stmt=db()->prepare(
                'INSERT INTO product_images(product_id,image_path,sort_order,crop_x,crop_y)
                 VALUES(?,?,?,?,?)'
            );
            $stmt->execute([$productId,'uploads/products/'.$name,$sort,$x,$y]);
        }else{
            $stmt=db()->prepare(
                'INSERT INTO product_images(product_id,image_path,sort_order)
                 VALUES(?,?,?)'
            );
            $stmt->execute([$productId,'uploads/products/'.$name,$sort]);
        }

        $createdIds[]=(int)db()->lastInsertId();
    }

    return $createdIds;
}

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

    if(!in_array($data['status'],['available','reserved','sold','archived'],true)){
        $errors[]='Status produk tidak valid.';
    }

    $catNames=array_column($categories,'name');
    if(!$categories){
        $errors[]='Belum ada kategori aktif. Admin harus membuat kategori terlebih dahulu.';
    }elseif(!in_array($data['category'],$catNames,true)){
        $errors[]='Kategori tidak valid.';
    }

    $deleteIds=is_array($_POST['delete_image_ids']??null)?$_POST['delete_image_ids']:[];
    $cropX=is_array($_POST['crop_x']??null)?$_POST['crop_x']:[];
    $cropY=is_array($_POST['crop_y']??null)?$_POST['crop_y']:[];
    $newCropX=is_array($_POST['new_crop_x']??null)?$_POST['new_crop_x']:[];
    $newCropY=is_array($_POST['new_crop_y']??null)?$_POST['new_crop_y']:[];
    $primaryChoice=(string)($_POST['primary_choice']??'');

    $orderedIds=[];
    $orderRaw=(string)($_POST['image_order_json']??'[]');
    $decoded=json_decode($orderRaw,true);
    if(is_array($decoded))$orderedIds=$decoded;

    if(!$errors){
        try{
            $productId=$editing?(int)$id:$repo->create(['seller_id'=>$sellerId]+$data);

            if($editing){
                $repo->update($productId,$sellerId,$data);
                $repo->setStock($productId,$sellerId,(int)$data['stock']);
            }else{
                $repo->setStock($productId,$sellerId,(int)$data['stock']);
            }

            $primaryExisting=0;
            if(str_starts_with($primaryChoice,'e:')){
                $primaryExisting=(int)substr($primaryChoice,2);
            }

            $deletedPaths=$repo->updateExistingImages(
                $productId,
                $sellerId,
                $deleteIds,
                $orderedIds,
                $primaryExisting,
                $cropX,
                $cropY
            );

            $newIds=handle_product_uploads(
                $repo,
                $productId,
                $sellerId,
                $newCropX,
                $newCropY
            );

            if(str_starts_with($primaryChoice,'n:')){
                $newIndex=(int)substr($primaryChoice,2);
                if(isset($newIds[$newIndex])){
                    $repo->makeImagePrimary($productId,$sellerId,(int)$newIds[$newIndex]);
                }
            }

            foreach($deletedPaths as $deletedPath){
                safe_unlink_product_image((string)$deletedPath);
            }

            flash(
                'success',
                $editing
                    ? 'Produk, stok, urutan foto, dan crop berhasil diperbarui.'
                    : 'Produk berhasil dibuat.'
            );

            redirect(page_url('seller-products'));
        }catch(Throwable $e){
            if(!$editing&&!empty($productId)){
                try{$repo->archive((int)$productId,$sellerId);}catch(Throwable){}
            }
            $errors[]=$e->getMessage()?:'Produk gagal disimpan.';
        }
    }
}

if($editing){
    $existing=$repo->find((int)$id,false);
}

$pageTitle=$editing?'Edit Produk':'Tambah Produk';
require __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="<?=e(APP_URL)?>/assets/css/ux-v3.css?v=20260923-8">
<link rel="stylesheet" href="<?=e(APP_URL)?>/assets/css/seller-media-v8.css?v=20260923-8">

<section class="auth-section seller-product-form-page">
<div class="auth-shell">
    <div class="auth-intro">
        <div class="page-toolbar"><?=back_link('seller-products','Kembali ke produk saya')?></div>
        <span class="eyebrow">Seller</span>
        <h1><?=$editing?'Edit':'Tambah'?> <em>produk.</em></h1>
        <p>Kelola maksimal 5 foto. Kamu bisa menentukan foto utama, menghapus foto mana pun, mengubah urutan, dan mengatur titik crop 1:1.</p>
    </div>

    <div class="auth-panel glass-card">
        <?php if($errors):?><div class="alert alert-danger"><?=implode('<br>',array_map('e',$errors))?></div><?php endif;?>

        <?php if(!$cropEnabled):?>
            <div class="alert alert-warning small">
                Fitur crop posisi belum tersimpan permanen karena kolom crop database belum ada.
                Jalankan <strong>database.image-crop-v8.sql</strong> sekali di phpMyAdmin.
            </div>
        <?php endif;?>

        <form method="post" enctype="multipart/form-data" id="product-form">
            <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
            <input type="hidden" name="image_order_json" id="image-order-json" value="[]">

            <label class="form-label">Nama produk</label>
            <input class="form-control mb-3" name="name" value="<?=e((string)$data['name'])?>" required>

            <label class="form-label">Kategori</label>
            <select class="form-select mb-3" name="category" required>
                <option value="">Pilih kategori</option>
                <?php foreach($categories as $c):?>
                    <option value="<?=e($c['name'])?>" <?=$data['category']===$c['name']?'selected':''?>><?=e($c['name'])?></option>
                <?php endforeach;?>
            </select>

            <label class="form-label">Deskripsi</label>
            <textarea class="form-control mb-3" rows="5" name="description"><?=e((string)$data['description'])?></textarea>

            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label">Harga</label>
                    <input class="form-control" type="number" min="0" name="price" value="<?=e((string)$data['price'])?>" required>
                </div>
                <div class="col-6">
                    <label class="form-label">Stok</label>
                    <input class="form-control" type="number" min="0" name="stock" value="<?=e((string)$data['stock'])?>" required>
                </div>
                <div class="col-6">
                    <label class="form-label">Kondisi</label>
                    <input class="form-control" name="condition_label" value="<?=e((string)$data['condition_label'])?>" required>
                </div>
                <div class="col-6">
                    <label class="form-label">Kota</label>
                    <input class="form-control" name="city" value="<?=e((string)$data['city'])?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <?php foreach(['available','reserved','sold','archived'] as $s):?>
                            <option value="<?=$s?>" <?=$data['status']===$s?'selected':''?>><?=$s?></option>
                        <?php endforeach;?>
                    </select>
                </div>

                <div class="col-12 seller-media-manager">
                    <label class="form-label">Kelola foto produk</label>
                    <div class="seller-media-help">
                        <i class="bi bi-crop"></i>
                        <span>
                            Crop di KosCycle tidak merusak file asli. Preview selalu 1:1 dan slider menentukan bagian foto yang menjadi fokus.
                            Horizontal menggeser fokus kiri/kanan, vertikal menggeser fokus atas/bawah.
                        </span>
                    </div>

                    <?php if($editing&&!empty($existing['images'])):?>
                        <div class="seller-image-editor-grid" id="existing-image-grid">
                            <?php foreach($existing['images'] as $index=>$img):?>
                                <?php
                                $imageId=(int)$img['id'];
                                $cropXValue=(int)($img['crop_x']??50);
                                $cropYValue=(int)($img['crop_y']??50);
                                ?>
                                <div class="seller-image-editor-card" data-existing-image data-image-id="<?=$imageId?>">
                                    <div class="seller-image-crop-preview">
                                        <img
                                            src="<?=e(APP_URL.'/'.ltrim((string)$img['image_path'],'/'))?>"
                                            alt="Foto produk"
                                            style="object-position:<?=$cropXValue?>% <?=$cropYValue?>%"
                                        >
                                        <span class="seller-image-card-badge"><?=$index===0?'Utama':'Foto '.($index+1)?></span>
                                    </div>

                                    <div class="seller-image-card-actions">
                                        <button class="btn btn-ghost btn-sm image-move-left" type="button" title="Geser ke kiri"><i class="bi bi-arrow-left"></i></button>
                                        <button class="btn btn-ghost btn-sm image-move-right" type="button" title="Geser ke kanan"><i class="bi bi-arrow-right"></i></button>

                                        <label class="seller-image-option">
                                            <input type="radio" name="primary_choice" value="e:<?=$imageId?>" <?=$index===0?'checked':''?>>
                                            Foto utama
                                        </label>

                                        <label class="seller-image-option is-delete">
                                            <input type="checkbox" name="delete_image_ids[]" value="<?=$imageId?>" class="delete-existing-image">
                                            Hapus
                                        </label>
                                    </div>

                                    <div class="seller-crop-controls">
                                        <div class="seller-crop-control">
                                            <label>Horizontal</label>
                                            <input class="crop-x-range" type="range" min="0" max="100" value="<?=$cropXValue?>" name="crop_x[<?=$imageId?>]">
                                            <output><?=$cropXValue?>%</output>
                                        </div>
                                        <div class="seller-crop-control">
                                            <label>Vertikal</label>
                                            <input class="crop-y-range" type="range" min="0" max="100" value="<?=$cropYValue?>" name="crop_y[<?=$imageId?>]">
                                            <output><?=$cropYValue?>%</output>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach;?>
                        </div>
                    <?php else:?>
                        <p class="text-muted small mt-2 mb-0">Belum ada foto tersimpan.</p>
                    <?php endif;?>

                    <div class="mt-3">
                        <label class="form-label">Tambah foto baru</label>
                        <input class="form-control" id="product-images" type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp">
                        <small class="text-muted">JPG/PNG/WEBP, maksimal 5 MB per foto dan maksimal total 5 foto per produk.</small>
                        <div class="seller-new-image-grid" id="seller-image-preview"></div>
                    </div>
                </div>
            </div>

            <button class="btn btn-primary w-100 mt-4">
                <?=$editing?'Simpan perubahan':'Tambah ke katalog'?>
                <i class="bi bi-arrow-right ms-2"></i>
            </button>
        </form>
    </div>
</div>
</section>

<script>
(()=>{
    const form=document.getElementById('product-form');
    const existingGrid=document.getElementById('existing-image-grid');
    const orderInput=document.getElementById('image-order-json');
    const fileInput=document.getElementById('product-images');
    const preview=document.getElementById('seller-image-preview');

    const updateExistingOrder=()=>{
        if(!orderInput)return;
        const ids=Array.from(document.querySelectorAll('[data-existing-image]'))
            .map(card=>Number(card.dataset.imageId||0))
            .filter(Boolean);
        orderInput.value=JSON.stringify(ids);
    };

    const syncCropCard=card=>{
        const img=card.querySelector('.seller-image-crop-preview img');
        const x=card.querySelector('.crop-x-range');
        const y=card.querySelector('.crop-y-range');
        if(!img||!x||!y)return;
        img.style.objectPosition=`${x.value}% ${y.value}%`;

        const xOut=x.closest('.seller-crop-control')?.querySelector('output');
        const yOut=y.closest('.seller-crop-control')?.querySelector('output');
        if(xOut)xOut.textContent=`${x.value}%`;
        if(yOut)yOut.textContent=`${y.value}%`;
    };

    document.querySelectorAll('[data-existing-image]').forEach(card=>{
        card.querySelectorAll('input[type="range"]').forEach(range=>{
            range.addEventListener('input',()=>syncCropCard(card));
        });

        const del=card.querySelector('.delete-existing-image');
        del?.addEventListener('change',()=>{
            card.classList.toggle('is-deleted',del.checked);
            const primary=card.querySelector('input[name="primary_choice"]');
            if(primary){
                primary.disabled=del.checked;
                if(del.checked&&primary.checked){
                    primary.checked=false;
                    const fallback=Array.from(document.querySelectorAll('[data-existing-image]'))
                        .find(other=>other!==card&&!other.querySelector('.delete-existing-image')?.checked)
                        ?.querySelector('input[name="primary_choice"]');
                    if(fallback)fallback.checked=true;
                }
            }
        });

        card.querySelector('.image-move-left')?.addEventListener('click',()=>{
            const prev=card.previousElementSibling;
            if(prev){
                card.parentElement.insertBefore(card,prev);
                updateExistingOrder();
            }
        });

        card.querySelector('.image-move-right')?.addEventListener('click',()=>{
            const next=card.nextElementSibling;
            if(next){
                card.parentElement.insertBefore(next,card);
                updateExistingOrder();
            }
        });
    });

    updateExistingOrder();

    let selectedFiles=[];

    const retainedExistingCount=()=>Array.from(document.querySelectorAll('[data-existing-image]'))
        .filter(card=>!card.querySelector('.delete-existing-image')?.checked).length;

    const rebuildFileInput=()=>{
        const transfer=new DataTransfer();
        selectedFiles.forEach(file=>transfer.items.add(file));
        fileInput.files=transfer.files;
    };

    const renderNewFiles=()=>{
        preview.innerHTML='';

        selectedFiles.forEach((file,index)=>{
            const url=URL.createObjectURL(file);
            const card=document.createElement('div');
            card.className='seller-image-editor-card';
            card.dataset.newIndex=String(index);

            card.innerHTML=`
                <div class="seller-image-crop-preview">
                    <img alt="Preview foto baru">
                    <span class="seller-image-card-badge">Baru ${index+1}</span>
                </div>
                <div class="seller-image-card-actions">
                    <label class="seller-image-option">
                        <input type="radio" name="primary_choice" value="n:${index}">
                        Jadikan utama
                    </label>
                    <button class="btn btn-outline-danger btn-sm remove-new-image" type="button">
                        <i class="bi bi-trash"></i> Hapus
                    </button>
                </div>
                <div class="seller-crop-controls">
                    <div class="seller-crop-control">
                        <label>Horizontal</label>
                        <input class="crop-x-range" type="range" min="0" max="100" value="50" name="new_crop_x[]">
                        <output>50%</output>
                    </div>
                    <div class="seller-crop-control">
                        <label>Vertikal</label>
                        <input class="crop-y-range" type="range" min="0" max="100" value="50" name="new_crop_y[]">
                        <output>50%</output>
                    </div>
                </div>
            `;

            const img=card.querySelector('img');
            img.src=url;
            img.onload=()=>URL.revokeObjectURL(url);

            card.querySelectorAll('input[type="range"]').forEach(range=>{
                range.addEventListener('input',()=>{
                    const x=card.querySelector('.crop-x-range');
                    const y=card.querySelector('.crop-y-range');
                    img.style.objectPosition=`${x.value}% ${y.value}%`;
                    x.closest('.seller-crop-control').querySelector('output').textContent=`${x.value}%`;
                    y.closest('.seller-crop-control').querySelector('output').textContent=`${y.value}%`;
                });
            });

            card.querySelector('.remove-new-image').addEventListener('click',()=>{
                selectedFiles.splice(index,1);
                rebuildFileInput();
                renderNewFiles();
            });

            preview.appendChild(card);
        });
    };

    fileInput?.addEventListener('change',()=>{
        selectedFiles=Array.from(fileInput.files||[]);

        if(retainedExistingCount()+selectedFiles.length>5){
            alert('Total foto produk maksimal 5. Hapus foto lama atau kurangi foto baru.');
            selectedFiles=[];
            fileInput.value='';
        }

        renderNewFiles();
    });

    form?.addEventListener('submit',event=>{
        updateExistingOrder();

        if(retainedExistingCount()+selectedFiles.length>5){
            event.preventDefault();
            alert('Total foto produk maksimal 5.');
        }
    });
})();
</script>

<?php require __DIR__.'/../includes/footer.php';?>
