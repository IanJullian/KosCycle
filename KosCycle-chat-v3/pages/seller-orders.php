<?php
require_once __DIR__.'/../includes/authorization.php';
require_role('seller');
require_once __DIR__.'/../includes/Repositories/OrderRepository.php';
$repo=new OrderRepository();
$sellerId=(int)$_SESSION['user_id'];
$errors=[];
$transitions=['requested'=>['accepted','cancelled'],'accepted'=>['completed','cancelled'],'completed'=>[],'cancelled'=>[]];
if(is_post()){
 if(!verify_csrf()){
  flash('error','Sesi formulir tidak valid.');
  redirect(page_url('seller-orders'));
 }
 $orderId=filter_input(INPUT_POST,'order_id',FILTER_VALIDATE_INT);
 $new=$_POST['status']??'';
 $items=$orderId?$repo->sellerOrderItems((int)$orderId,$sellerId):[];
 $stmt=$orderId?db()->prepare('SELECT status FROM orders WHERE id=? LIMIT 1'):null;
 if($stmt)$stmt->execute([$orderId]);
 $current=$stmt?$stmt->fetchColumn():false;
 if(!$orderId||!$items||!isset($transitions[$current])||!in_array($new,$transitions[$current],true)){
  $errors[]='Perubahan status tidak valid atau pesanan bukan milik produkmu.';
 }else{
  try{
   $repo->setStatusSeller((int)$orderId,$sellerId,$new);
   flash('success','Status pesanan diperbarui.');
   redirect(page_url('seller-orders'));
  }catch(Throwable $e){
   $errors[]=$e instanceof RuntimeException ? $e->getMessage() : 'Status pesanan gagal diperbarui.';
  }
 }
}
$orders=$repo->sellerOrders($sellerId);
$pageTitle='Pesanan Seller';
require __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/seller-orders-polish.css">

<section class="section-padding page-section seller-orders-page">
 <div class="container">
  <div class="page-toolbar"><?=back_link('seller-dashboard','Kembali ke dashboard')?></div>
  <div class="section-heading mb-4">
   <span class="eyebrow">Seller</span>
   <h2>Kelola <em>pesanan.</em></h2>
   <p class="text-muted">Lihat detail pesanan, catatan customer, lalu perbarui status sesuai prosesnya.</p>
  </div>
  <?php if($errors):?><div class="alert alert-danger"><?=implode('<br>',array_map('e',$errors))?></div><?php endif;?>
  <?php if(!$orders):?>
   <div class="glass-card empty-state seller-order-empty"><span><i class="bi bi-bag-check"></i></span><h2>Belum ada pesanan</h2><p>Pesanan dari customer akan muncul di sini.</p></div>
  <?php endif;?>
  <div class="seller-order-list">
  <?php foreach($orders as $o):
   $status=(string)$o['status'];
   $statusMap=[
    'requested'=>['label'=>'Menunggu konfirmasi','class'=>'seller-status-requested','icon'=>'bi-hourglass-split'],
    'accepted'=>['label'=>'Diproses','class'=>'seller-status-accepted','icon'=>'bi-arrow-repeat'],
    'completed'=>['label'=>'Selesai','class'=>'seller-status-completed','icon'=>'bi-check-circle-fill'],
    'cancelled'=>['label'=>'Dibatalkan','class'=>'seller-status-cancelled','icon'=>'bi-x-circle-fill'],
   ];
   $badge=$statusMap[$status]??['label'=>ucfirst($status),'class'=>'','icon'=>'bi-info-circle'];
   $items=$repo->sellerOrderItems((int)$o['id'],$sellerId);
  ?>
   <article class="glass-card seller-order-card <?=$badge['class']?>">
    <div class="seller-order-header">
      <div class="seller-order-title-block">
       <div class="seller-order-title-row"><span class="seller-order-number">Pesanan #<?=intval($o['id'])?></span><span class="seller-order-status"><i class="bi <?=$badge['icon']?>"></i><?=$badge['label']?></span></div>
       <div class="seller-order-buyer"><i class="bi bi-person"></i><?=e($o['buyer_name'])?><span>•</span><i class="bi bi-whatsapp"></i><?=e($o['buyer_whatsapp'])?></div>
      </div>
      <div class="seller-order-total"><small>Total pesanan</small><strong><?=format_price((int)$o['total'])?></strong><time><?=e($o['created_at'])?></time></div>
    </div>

    <?php if(trim((string)($o['note']??''))!==''): ?>
      <div class="seller-order-note">
       <div class="seller-order-note-icon"><i class="bi bi-sticky"></i></div>
       <div><span>Catatan dari customer</span><p><?=nl2br(e((string)$o['note']))?></p></div>
      </div>
    <?php else: ?>
      <div class="seller-order-note is-empty"><div class="seller-order-note-icon"><i class="bi bi-sticky"></i></div><div><span>Catatan dari customer</span><p>Customer tidak menambahkan catatan saat checkout.</p></div></div>
    <?php endif; ?>

    <div class="seller-order-items">
      <div class="seller-order-items-head"><span>Item pesanan</span><span><?=count($items)?> produk</span></div>
      <?php foreach($items as $it): ?>
       <div class="seller-order-item">
        <div><strong><?=e($it['product_name'])?></strong><small><?=intval($it['quantity'])?> × <?=format_price((int)$it['unit_price'])?></small></div>
        <strong><?=format_price((int)$it['line_total'])?></strong>
       </div>
      <?php endforeach; ?>
    </div>

    <?php if($status==='completed'): ?>
      <div class="seller-order-state seller-order-state-success"><i class="bi bi-check2-circle"></i><div><strong>Pesanan sudah selesai</strong><span>Transaksi ini sudah masuk ke riwayat penjualan.</span></div><a class="btn btn-soft btn-sm" href="<?=e(page_url('seller-sales'))?>">Lihat penjualan</a></div>
    <?php elseif($status==='cancelled'): ?>
      <div class="seller-order-state seller-order-state-cancelled"><i class="bi bi-x-circle"></i><div><strong>Pesanan dibatalkan</strong><span>Stok yang terkait sudah dikembalikan sesuai alur sistem.</span></div></div>
    <?php elseif($transitions[$status]??[]): ?>
      <form class="seller-order-action" method="post">
       <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
       <input type="hidden" name="order_id" value="<?=intval($o['id'])?>">
       <div><label class="seller-order-action-label">Perbarui status</label><select class="form-select" name="status"><?php foreach($transitions[$status] as $s): ?><option value="<?=$s?>"><?=$s==='accepted'?'Terima pesanan':($s==='completed'?'Tandai selesai':'Batalkan pesanan')?></option><?php endforeach;?></select></div>
       <button class="btn btn-primary seller-order-save" type="submit"><i class="bi bi-check2 me-1"></i>Simpan status</button>
      </form>
    <?php endif; ?>
   </article>
  <?php endforeach; ?>
  </div>
 </div>
</section>
<?php require __DIR__.'/../includes/footer.php'; ?>
