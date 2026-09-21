<?php
declare(strict_types=1);
class OrderRepository{
 public function byBuyer(int $buyerId):array{$st=db()->prepare("SELECT o.id,o.status,o.note,o.created_at,o.updated_at,COUNT(oi.id) item_count,COALESCE(SUM(oi.line_total),0) total FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id WHERE o.buyer_id=? GROUP BY o.id ORDER BY o.id DESC");$st->execute([$buyerId]);return$st->fetchAll();}
 public function items(int $orderId,int $buyerId):array{$st=db()->prepare("SELECT o.id order_id,o.status,o.note,o.created_at,oi.id order_item_id,oi.product_id,oi.seller_id,oi.product_name,oi.unit_price,oi.quantity,oi.line_total,p.city,COALESCE(NULLIF(u.shop_name,''),u.full_name) seller_name FROM orders o JOIN order_items oi ON oi.order_id=o.id JOIN products p ON p.id=oi.product_id JOIN users u ON u.id=oi.seller_id WHERE o.id=? AND o.buyer_id=? ORDER BY oi.id");$st->execute([$orderId,$buyerId]);return$st->fetchAll();}
 public function sellerOrders(int $sellerId):array{$st=db()->prepare("SELECT o.id,o.buyer_id,o.status,o.note,o.created_at,o.updated_at,u.full_name buyer_name,u.whatsapp buyer_whatsapp,COALESCE(SUM(oi.line_total),0) total FROM orders o JOIN order_items oi ON oi.order_id=o.id JOIN users u ON u.id=o.buyer_id WHERE oi.seller_id=? GROUP BY o.id,o.buyer_id,o.status,o.note,o.created_at,o.updated_at,u.full_name,u.whatsapp ORDER BY o.id DESC");$st->execute([$sellerId]);return$st->fetchAll();}
 public function sellerOrderItems(int $orderId,int $sellerId):array{$st=db()->prepare('SELECT oi.* FROM order_items oi WHERE oi.order_id=? AND oi.seller_id=? ORDER BY oi.id');$st->execute([$orderId,$sellerId]);return$st->fetchAll();}
 public function all():array{return db()->query("SELECT o.*,u.full_name buyer_name,(SELECT COUNT(*) FROM order_items oi WHERE oi.order_id=o.id) item_count,COALESCE((SELECT SUM(oi2.line_total) FROM order_items oi2 WHERE oi2.order_id=o.id),0) total FROM orders o JOIN users u ON u.id=o.buyer_id ORDER BY o.id DESC")->fetchAll();}
 public function setStatus(int $orderId,string $status):void{$this->changeStatus($orderId,null,$status);}
 public function setStatusSeller(int $orderId,int $sellerId,string $status):void{$this->changeStatus($orderId,$sellerId,$status);}
 private function changeStatus(int $orderId,?int $sellerId,string $status):void{
  $pdo=db();$pdo->beginTransaction();
  try{
   $sql='SELECT id,status,buyer_id FROM orders WHERE id=? LIMIT 1 FOR UPDATE';$st=$pdo->prepare($sql);$st->execute([$orderId]);$order=$st->fetch();if(!$order)throw new RuntimeException('Pesanan tidak ditemukan.');
   if($sellerId!==null){$check=$pdo->prepare('SELECT 1 FROM order_items WHERE order_id=? AND seller_id=? LIMIT 1');$check->execute([$orderId,$sellerId]);if(!$check->fetchColumn())throw new RuntimeException('Pesanan bukan milik seller.');}
   if($status==='cancelled' && $order['status']!=='cancelled'){
     $items=$pdo->prepare('SELECT product_id,quantity FROM order_items WHERE order_id=?');$items->execute([$orderId]);
     $itemsRows=$items->fetchAll();
     foreach($itemsRows as $item){
       $up=$pdo->prepare('INSERT INTO product_inventory(product_id,quantity) VALUES(?,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity),updated_at=CURRENT_TIMESTAMP');$up->execute([(int)$item['product_id'],(int)$item['quantity']]);
       $pdo->prepare("UPDATE products SET status=IF(status='sold','available',status) WHERE id=? AND status<>'archived'")->execute([(int)$item['product_id']]);
     }
   }
   $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$status,$orderId]);
   $changedBy=$sellerId??(int)($_SESSION['user_id']??0);
   $pdo->prepare('INSERT INTO order_status_history(order_id,status,changed_by) VALUES(?,?,?)')->execute([$orderId,$status,$changedBy]);
   $pdo->commit();
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
 }
 public function salesHistory(int $sellerId):array{$st=db()->prepare("SELECT oi.order_id,oi.product_id,oi.product_name,oi.quantity,oi.line_total,o.created_at FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.seller_id=? AND o.status='completed' ORDER BY o.id DESC,oi.id");$st->execute([$sellerId]);return$st->fetchAll();}
}
