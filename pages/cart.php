<?php
require_once __DIR__ . '/../includes/authorization.php';
require_role('customer');
require_once __DIR__ . '/../includes/Repositories/CartRepository.php';
$repo = new CartRepository();
$userId = (int)$_SESSION['user_id'];
if (is_post()) {
    if (!verify_csrf()) { flash('error','Sesi formulir tidak valid.'); redirect(page_url('cart')); }
    $action = $_POST['action'] ?? '';
    $itemId = filter_input(INPUT_POST,'cart_item_id',FILTER_VALIDATE_INT);
    if (!$itemId) { flash('error','Item tidak valid.'); redirect(page_url('cart')); }
    if ($action === 'update') {
        $qty = filter_input(INPUT_POST,'quantity',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$qty) flash('error','Jumlah tidak valid.');
        else { try { $repo->update($userId,(int)$itemId,(int)$qty); flash('success','Jumlah keranjang diperbarui.'); } catch (Throwable $e) { flash('error',$e instanceof RuntimeException ? $e->getMessage() : 'Jumlah keranjang gagal diperbarui.'); } }
    } elseif ($action === 'delete') {
        $repo->remove($userId,(int)$itemId);
    }
    redirect(page_url('cart'));
}
$items = $repo->items($userId);
$total=0; foreach($items as $item){$total += (int)$item['price']*(int)$item['quantity'];}
$pageTitle='Keranjang';
require __DIR__ . '/../includes/header.php';
?>
<section class="section-padding page-section"><div class="container"><div class="page-toolbar"><?=back_link('marketplace','Kembali ke marketplace')?></div><div class="section-heading mb-4"><span class="eyebrow">Keranjang</span><h2>Siap <em>checkout?</em></h2></div>
<?php if(!$items): ?><div class="glass-card p-5 text-center"><h3>Keranjang masih kosong</h3><a class="btn btn-primary mt-3" href="<?= e(page_url('marketplace')) ?>">Jelajahi marketplace</a></div>
<?php else: ?><div class="row g-4"><div class="col-lg-8"><?php foreach($items as $item): ?><div class="glass-card p-3 mb-3"><div class="d-flex gap-3 align-items-center">
<div style="width:90px;height:75px;border-radius:10px;overflow:hidden;background:#e6eee7;display:grid;place-items:center"><?php if($item['image_path']): ?><img src="<?= e(APP_URL . '/' . ltrim($item['image_path'],'/')) ?>" style="width:100%;height:100%;object-fit:cover" alt="<?= e($item['name']) ?>"><?php else: ?><i class="bi bi-box-seam"></i><?php endif; ?></div>
<div class="flex-grow-1"><h3 class="h6 mb-1"><?= e($item['name']) ?></h3><small class="text-muted"><?= format_price((int)$item['price']) ?> · stok <?= (int)$item['stock'] ?></small></div>
<form method="post" class="d-flex gap-2 align-items-end"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="cart_item_id" value="<?= (int)$item['cart_item_id'] ?>"><input class="form-control" style="width:90px" type="number" min="1" max="<?= (int)$item['stock'] ?>" name="quantity" value="<?= (int)$item['quantity'] ?>"><button class="btn btn-soft">Ubah</button></form>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="cart_item_id" value="<?= (int)$item['cart_item_id'] ?>"><button class="btn btn-outline-danger">Hapus</button></form>
</div></div><?php endforeach; ?></div>
<div class="col-lg-4"><div class="glass-card p-4"><span class="eyebrow">Ringkasan</span><div class="d-flex justify-content-between mt-3"><span>Subtotal</span><strong><?= format_price($total) ?></strong></div><a class="btn btn-primary w-100 mt-4" href="<?= e(page_url('checkout')) ?>">Lanjut checkout</a></div></div></div><?php endif; ?></div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
