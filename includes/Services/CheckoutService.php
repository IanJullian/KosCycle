<?php
declare(strict_types=1);
class CheckoutService{
 public function checkout(int $buyerId,string $note=''):array{
  $pdo=db();$pdo->beginTransaction();
  try{
   $st=$pdo->prepare('SELECT id FROM carts WHERE user_id=? LIMIT 1');$st->execute([$buyerId]);$cartId=(int)$st->fetchColumn();if(!$cartId)throw new RuntimeException('Keranjang kosong.');
   $st=$pdo->prepare("SELECT ci.product_id,ci.quantity,p.seller_id,p.name,p.price,p.status,COALESCE(pi.quantity,0) stock FROM cart_items ci JOIN products p ON p.id=ci.product_id LEFT JOIN product_inventory pi ON pi.product_id=p.id WHERE ci.cart_id=? ORDER BY ci.id FOR UPDATE");$st->execute([$cartId]);$items=$st->fetchAll();if(!$items)throw new RuntimeException('Keranjang kosong.');
   $groups=[];foreach($items as $item){if($item['status']!=='available')throw new RuntimeException('Produk "'.$item['name'].'" tidak tersedia.');$lock=$pdo->prepare('SELECT quantity FROM product_inventory WHERE product_id=? FOR UPDATE');$lock->execute([(int)$item['product_id']]);$stock=$lock->fetchColumn();$stock=$stock===false?0:(int)$stock;if($stock<(int)$item['quantity'])throw new RuntimeException('Stok "'.$item['name'].'" tidak mencukupi.');$item['unit_price']=(int)$item['price'];$item['line_total']=$item['unit_price']*(int)$item['quantity'];$groups[(int)$item['seller_id']][]=$item;}
   $orderIds=[];
   foreach($groups as $sellerId=>$group){$first=$group[0];$st=$pdo->prepare('INSERT INTO orders(buyer_id,product_id,seller_id,status,note) VALUES(?,?,?,"requested",?)');$st->execute([$buyerId,(int)$first['product_id'],(int)$sellerId,$note]);$orderId=(int)$pdo->lastInsertId();$orderIds[]=$orderId;$itemStmt=$pdo->prepare('INSERT INTO order_items(order_id,product_id,seller_id,product_name,unit_price,quantity,line_total) VALUES(?,?,?,?,?,?,?)');$stockStmt=$pdo->prepare('UPDATE product_inventory SET quantity=quantity-?,updated_at=CURRENT_TIMESTAMP WHERE product_id=? AND quantity>=?');foreach($group as $item){$itemStmt->execute([$orderId,(int)$item['product_id'],(int)$item['seller_id'],$item['name'],(int)$item['unit_price'],(int)$item['quantity'],(int)$item['line_total']]);$stockStmt->execute([(int)$item['quantity'],(int)$item['product_id'],(int)$item['quantity']]);if($stockStmt->rowCount()!==1)throw new RuntimeException('Stok berubah saat checkout. Silakan coba lagi.');$left=(int)$item['stock']-(int)$item['quantity'];if($left===0)$pdo->prepare("UPDATE products SET status='sold' WHERE id=?")->execute([(int)$item['product_id']]);}$pdo->prepare('INSERT INTO order_status_history(order_id,status,changed_by) VALUES(? ,"requested",?)')->execute([$orderId,$buyerId]);}
   $pdo->prepare('DELETE FROM cart_items WHERE cart_id=?')->execute([$cartId]);$pdo->commit();return$orderIds;
  }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
 }
}
