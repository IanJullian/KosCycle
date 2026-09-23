<?php
declare(strict_types=1);

class OrderRepository
{
    private function localNoSql(string $alias='o'): string
    {
        return "(SELECT COUNT(*) FROM orders ox WHERE ox.buyer_id={$alias}.buyer_id AND ox.id<={$alias}.id)";
    }

    public function buyerCount(int $buyerId): int
    {
        $st=db()->prepare('SELECT COUNT(*) FROM orders WHERE buyer_id=?');
        $st->execute([$buyerId]);
        return (int)$st->fetchColumn();
    }

    public function buyerStats(int $buyerId): array
    {
        $st=db()->prepare(
            "SELECT
                COUNT(*) total,
                SUM(status IN ('requested','accepted')) open_count,
                SUM(status='completed') completed_count,
                SUM(status='cancelled') cancelled_count
             FROM orders WHERE buyer_id=?"
        );
        $st->execute([$buyerId]);
        $r=$st->fetch() ?: [];
        return [
            'total'=>(int)($r['total']??0),
            'open'=>(int)($r['open_count']??0),
            'completed'=>(int)($r['completed_count']??0),
            'cancelled'=>(int)($r['cancelled_count']??0),
        ];
    }

    public function byBuyer(int $buyerId): array
    {
        return $this->byBuyerPage($buyerId,100000,0);
    }

    public function byBuyerPage(int $buyerId,int $limit,int $offset): array
    {
        $limit=max(1,min($limit,100));
        $offset=max(0,$offset);
        $local=$this->localNoSql('o');
        $st=db()->prepare(
            "SELECT o.id,o.status,o.note,o.created_at,o.updated_at,
                    {$local} local_order_no,
                    COUNT(oi.id) item_count,
                    COALESCE(SUM(oi.line_total),0) total,
                    pmt.payment_status,pmt.payment_type
             FROM orders o
             LEFT JOIN order_items oi ON oi.order_id=o.id
             LEFT JOIN payments pmt ON pmt.order_id=o.id
             WHERE o.buyer_id=?
             GROUP BY o.id,o.buyer_id,o.status,o.note,o.created_at,o.updated_at,pmt.payment_status,pmt.payment_type
             ORDER BY o.id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $st->execute([$buyerId]);
        return $st->fetchAll();
    }

    public function items(int $orderId,int $buyerId): array
    {
        $local=$this->localNoSql('o');
        $st=db()->prepare(
            "SELECT o.id order_id,o.buyer_id,o.status,o.note,o.created_at,
                    {$local} local_order_no,
                    oi.id order_item_id,oi.product_id,oi.seller_id,oi.product_name,
                    oi.unit_price,oi.quantity,oi.line_total,p.city,
                    COALESCE(NULLIF(u.shop_name,''),u.full_name) seller_name,
                    r.id review_id,r.rating review_rating,r.review_text review_text,r.created_at review_created_at,
                    pmt.payment_status,pmt.payment_type,pmt.gross_amount payment_gross_amount
             FROM orders o
             JOIN order_items oi ON oi.order_id=o.id
             JOIN products p ON p.id=oi.product_id
             JOIN users u ON u.id=oi.seller_id
             LEFT JOIN reviews r ON r.buyer_id=o.buyer_id AND r.product_id=oi.product_id
             LEFT JOIN payments pmt ON pmt.order_id=o.id
             WHERE o.id=? AND o.buyer_id=?
             ORDER BY oi.id"
        );
        $st->execute([$orderId,$buyerId]);
        return $st->fetchAll();
    }

    public function sellerCount(int $sellerId): int
    {
        $st=db()->prepare(
            'SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON oi.order_id=o.id WHERE oi.seller_id=?'
        );
        $st->execute([$sellerId]);
        return (int)$st->fetchColumn();
    }

    public function sellerOrders(int $sellerId): array
    {
        return $this->sellerOrdersPage($sellerId,100000,0);
    }

    public function sellerOrdersPage(int $sellerId,int $limit,int $offset): array
    {
        $limit=max(1,min($limit,100));
        $offset=max(0,$offset);
        $local=$this->localNoSql('o');
        $st=db()->prepare(
            "SELECT o.id,o.buyer_id,o.status,o.note,o.created_at,o.updated_at,
                    {$local} local_order_no,
                    u.full_name buyer_name,u.username buyer_username,u.whatsapp buyer_whatsapp,
                    COALESCE(SUM(oi.line_total),0) total,
                    pmt.payment_status,pmt.payment_type
             FROM orders o
             JOIN order_items oi ON oi.order_id=o.id
             JOIN users u ON u.id=o.buyer_id
             LEFT JOIN payments pmt ON pmt.order_id=o.id
             WHERE oi.seller_id=?
             GROUP BY o.id,o.buyer_id,o.status,o.note,o.created_at,o.updated_at,
                      u.full_name,u.username,u.whatsapp,pmt.payment_status,pmt.payment_type
             ORDER BY o.id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $st->execute([$sellerId]);
        return $st->fetchAll();
    }

    public function sellerOrder(int $orderId,int $sellerId): ?array
    {
        $local=$this->localNoSql('o');
        $st=db()->prepare(
            "SELECT o.id,o.buyer_id,o.status,o.note,o.created_at,o.updated_at,
                    {$local} local_order_no,
                    u.full_name buyer_name,u.username buyer_username,u.whatsapp buyer_whatsapp,
                    COALESCE(SUM(oi.line_total),0) total,
                    pmt.payment_status,pmt.payment_type
             FROM orders o
             JOIN order_items oi ON oi.order_id=o.id
             JOIN users u ON u.id=o.buyer_id
             LEFT JOIN payments pmt ON pmt.order_id=o.id
             WHERE o.id=? AND oi.seller_id=?
             GROUP BY o.id,o.buyer_id,o.status,o.note,o.created_at,o.updated_at,
                      u.full_name,u.username,u.whatsapp,pmt.payment_status,pmt.payment_type
             LIMIT 1"
        );
        $st->execute([$orderId,$sellerId]);
        return $st->fetch() ?: null;
    }

    public function sellerOrderItems(int $orderId,int $sellerId): array
    {
        $st=db()->prepare(
            "SELECT oi.*,r.id review_id,r.rating review_rating,r.review_text review_text,r.created_at review_created_at
             FROM order_items oi
             JOIN orders o ON o.id=oi.order_id
             LEFT JOIN reviews r ON r.buyer_id=o.buyer_id AND r.product_id=oi.product_id
             WHERE oi.order_id=? AND oi.seller_id=?
             ORDER BY oi.id"
        );
        $st->execute([$orderId,$sellerId]);
        return $st->fetchAll();
    }

    public function sellerDashboardStats(int $sellerId): array
    {
        $st=db()->prepare(
            "SELECT
                COUNT(DISTINCT o.id) total_orders,
                COUNT(DISTINCT CASE WHEN o.status='requested' AND COALESCE(pmt.payment_status,'unpaid')='paid' THEN o.id END) ready_count,
                COUNT(DISTINCT CASE WHEN o.status='accepted' THEN o.id END) processing_count,
                COUNT(DISTINCT CASE WHEN o.status='completed' THEN o.id END) completed_count,
                COALESCE(SUM(CASE WHEN o.status='completed' AND COALESCE(pmt.payment_status,'unpaid')='paid' THEN oi.line_total ELSE 0 END),0) revenue
             FROM order_items oi
             JOIN orders o ON o.id=oi.order_id
             LEFT JOIN payments pmt ON pmt.order_id=o.id
             WHERE oi.seller_id=?"
        );
        $st->execute([$sellerId]);
        $r=$st->fetch() ?: [];
        return [
            'total'=>(int)($r['total_orders']??0),
            'ready'=>(int)($r['ready_count']??0),
            'processing'=>(int)($r['processing_count']??0),
            'completed'=>(int)($r['completed_count']??0),
            'revenue'=>(int)($r['revenue']??0),
        ];
    }

    public function all(): array
    {
        return $this->adminPage([],100000,0);
    }

    public function adminCount(array $filters=[]): int
    {
        [$where,$params]=$this->adminFilterSql($filters);
        $st=db()->prepare(
            "SELECT COUNT(DISTINCT o.id)
             FROM orders o
             JOIN users u ON u.id=o.buyer_id
             JOIN order_items oi ON oi.order_id=o.id
             JOIN users su ON su.id=oi.seller_id
             LEFT JOIN payments pmt ON pmt.order_id=o.id
             {$where}"
        );
        $st->execute($params);
        return (int)$st->fetchColumn();
    }

    private function adminFilterSql(array $f): array
    {
        $where=['1=1'];$params=[];
        $q=trim((string)($f['q']??''));
        if($q!==''){
            $where[]='(u.full_name LIKE ? OR u.username LIKE ? OR su.full_name LIKE ? OR su.username LIKE ? OR oi.product_name LIKE ?)';
            $n='%'.$q.'%';
            array_push($params,$n,$n,$n,$n,$n);
        }
        if(!empty($f['buyer_id'])){$where[]='o.buyer_id=?';$params[]=(int)$f['buyer_id'];}
        if(!empty($f['seller_id'])){$where[]='oi.seller_id=?';$params[]=(int)$f['seller_id'];}
        if(!empty($f['status'])){$where[]='o.status=?';$params[]=(string)$f['status'];}
        if(!empty($f['payment_status'])){$where[]="COALESCE(pmt.payment_status,'unpaid')=?";$params[]=(string)$f['payment_status'];}
        return ['WHERE '.implode(' AND ',$where),$params];
    }

    public function adminPage(array $filters,int $limit,int $offset): array
    {
        $limit=max(1,min($limit,100));$offset=max(0,$offset);
        [$where,$params]=$this->adminFilterSql($filters);
        $local=$this->localNoSql('o');
        $st=db()->prepare(
            "SELECT o.id,o.buyer_id,o.status,o.note,o.created_at,
                    {$local} local_order_no,
                    u.full_name buyer_name,u.username buyer_username,
                    MIN(oi.seller_id) seller_id,
                    GROUP_CONCAT(DISTINCT COALESCE(NULLIF(su.shop_name,''),su.full_name) ORDER BY su.id SEPARATOR ', ') seller_names,
                    COUNT(oi.id) item_count,COALESCE(SUM(oi.line_total),0) total,
                    COALESCE(pmt.payment_status,'unpaid') payment_status,pmt.payment_type
             FROM orders o
             JOIN users u ON u.id=o.buyer_id
             JOIN order_items oi ON oi.order_id=o.id
             JOIN users su ON su.id=oi.seller_id
             LEFT JOIN payments pmt ON pmt.order_id=o.id
             {$where}
             GROUP BY o.id,o.buyer_id,o.status,o.note,o.created_at,u.full_name,u.username,pmt.payment_status,pmt.payment_type
             ORDER BY o.id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $st->execute($params);
        return $st->fetchAll();
    }

    public function setStatus(int $orderId,string $status): void
    {
        $this->changeStatus($orderId,null,$status);
    }

    public function setStatusSeller(int $orderId,int $sellerId,string $status): void
    {
        $this->changeStatus($orderId,$sellerId,$status);
    }

    private function changeStatus(int $orderId,?int $sellerId,string $status): void
    {
        $pdo=db();$pdo->beginTransaction();
        try{
            $st=$pdo->prepare('SELECT id,status,buyer_id FROM orders WHERE id=? LIMIT 1 FOR UPDATE');
            $st->execute([$orderId]);$order=$st->fetch();
            if(!$order)throw new RuntimeException('Pesanan tidak ditemukan.');

            $transitions=['requested'=>['accepted','cancelled'],'accepted'=>['completed','cancelled'],'completed'=>[],'cancelled'=>[]];
            if(!isset($transitions[$order['status']])||!in_array($status,$transitions[$order['status']],true)){
                throw new RuntimeException('Transisi status pesanan tidak valid.');
            }

            if($sellerId!==null){
                $check=$pdo->prepare('SELECT 1 FROM order_items WHERE order_id=? AND seller_id=? LIMIT 1');
                $check->execute([$orderId,$sellerId]);
                $sellerCount=$pdo->prepare('SELECT COUNT(DISTINCT seller_id) FROM order_items WHERE order_id=?');
                $sellerCount->execute([$orderId]);
                if(!$check->fetchColumn()||(int)$sellerCount->fetchColumn()!==1){
                    throw new RuntimeException('Pesanan bukan pesanan tunggal seller ini.');
                }

                if(in_array($status,['accepted','completed'],true)){
                    $pay=$pdo->prepare('SELECT payment_status FROM payments WHERE order_id=? LIMIT 1 FOR UPDATE');
                    $pay->execute([$orderId]);
                    if((string)($pay->fetchColumn()?:'unpaid')!=='paid'){
                        throw new RuntimeException('Pesanan belum dibayar. Tunggu pembayaran customer terkonfirmasi terlebih dahulu.');
                    }
                }
            }

            if($status==='cancelled'&&$order['status']!=='cancelled'){
                $items=$sellerId===null
                    ?$pdo->prepare('SELECT product_id,quantity FROM order_items WHERE order_id=?')
                    :$pdo->prepare('SELECT product_id,quantity FROM order_items WHERE order_id=? AND seller_id=?');
                $sellerId===null?$items->execute([$orderId]):$items->execute([$orderId,$sellerId]);
                foreach($items->fetchAll() as $item){
                    $pdo->prepare(
                        'INSERT INTO product_inventory(product_id,quantity) VALUES(?,?)
                         ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity),updated_at=CURRENT_TIMESTAMP'
                    )->execute([(int)$item['product_id'],(int)$item['quantity']]);
                    $pdo->prepare(
                        "UPDATE products SET status=IF(status='sold','available',status) WHERE id=? AND status<>'archived'"
                    )->execute([(int)$item['product_id']]);
                }
            }

            $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$status,$orderId]);
            $changedBy=$sellerId??(int)($_SESSION['user_id']??0);
            $pdo->prepare('INSERT INTO order_status_history(order_id,status,changed_by) VALUES(?,?,?)')
                ->execute([$orderId,$status,$changedBy]);
            $pdo->commit();
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
    }

    public function salesHistory(int $sellerId): array
    {
        return $this->salesHistoryPage($sellerId,100000,0);
    }

    public function salesCount(int $sellerId): int
    {
        $st=db()->prepare(
            "SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id=oi.order_id
             WHERE oi.seller_id=? AND o.status='completed'"
        );
        $st->execute([$sellerId]);return (int)$st->fetchColumn();
    }

    public function salesTotal(int $sellerId): int
    {
        $st=db()->prepare(
            "SELECT COALESCE(SUM(oi.line_total),0)
             FROM order_items oi JOIN orders o ON o.id=oi.order_id
             LEFT JOIN payments p ON p.order_id=o.id
             WHERE oi.seller_id=? AND o.status='completed' AND COALESCE(p.payment_status,'unpaid')='paid'"
        );
        $st->execute([$sellerId]);return (int)$st->fetchColumn();
    }

    public function salesHistoryPage(int $sellerId,int $limit,int $offset): array
    {
        $limit=max(1,min($limit,100));$offset=max(0,$offset);
        $local=$this->localNoSql('o');
        $st=db()->prepare(
            "SELECT oi.order_id,oi.product_id,oi.product_name,oi.quantity,oi.line_total,o.created_at,
                    {$local} local_order_no,u.full_name buyer_name
             FROM order_items oi
             JOIN orders o ON o.id=oi.order_id
             JOIN users u ON u.id=o.buyer_id
             WHERE oi.seller_id=? AND o.status='completed'
             ORDER BY o.id DESC,oi.id
             LIMIT {$limit} OFFSET {$offset}"
        );
        $st->execute([$sellerId]);return $st->fetchAll();
    }
}
