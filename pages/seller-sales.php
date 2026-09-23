<?php
require_once __DIR__.'/../includes/authorization.php';require_role('seller');
require_once __DIR__.'/../includes/Repositories/OrderRepository.php';
require_once __DIR__.'/../includes/pagination.php';
$uid=(int)$_SESSION['user_id'];$repo=new OrderRepository();
$totalRows=$repo->salesCount($uid);$pager=pager_meta($totalRows,(int)($_GET['p']??1),12);
$rows=$repo->salesHistoryPage($uid,$pager['per_page'],$pager['offset']);$total=$repo->salesTotal($uid);
$pageTitle='Riwayat Penjualan';require __DIR__.'/../includes/header.php';
?>
<section class="section-padding page-section"><div class="container"><div class="page-toolbar"><?=back_link('seller-dashboard','Kembali ke dashboard')?></div><div class="section-heading mb-4"><span class="eyebrow">Seller</span><h2>Riwayat <em>penjualan.</em></h2><p class="text-muted">Data selesai dengan pagination agar halaman tetap ringan.</p></div><div class="row g-4 mb-4"><div class="col-md-6"><div class="glass-card p-4"><span class="eyebrow">Item selesai</span><h2><?=$totalRows?></h2></div></div><div class="col-md-6"><div class="glass-card p-4"><span class="eyebrow">Total penjualan paid</span><h2><?=format_price($total)?></h2></div></div></div><div class="glass-card p-3 table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Pesanan</th><th>Customer</th><th>Produk</th><th>Qty</th><th>Nilai</th><th>Tanggal</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td>#<?=(int)$r['local_order_no']?></td><td><?=e((string)$r['buyer_name'])?></td><td><?=e((string)$r['product_name'])?></td><td><?=(int)$r['quantity']?></td><td><?=format_price((int)$r['line_total'])?></td><td><?=e((string)$r['created_at'])?></td></tr><?php endforeach;?></tbody></table></div><?=pager_render($pager)?></div></section>
<?php require __DIR__.'/../includes/footer.php';?>
