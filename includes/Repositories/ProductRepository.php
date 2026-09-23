<?php
declare(strict_types=1);

class ProductRepository
{
    private ?bool $cropColumnsAvailable = null;

    private function hasCropColumns(): bool
    {
        if ($this->cropColumnsAvailable !== null) {
            return $this->cropColumnsAvailable;
        }

        try {
            $x = db()->query("SHOW COLUMNS FROM product_images LIKE 'crop_x'")->fetchColumn();
            $y = db()->query("SHOW COLUMNS FROM product_images LIKE 'crop_y'")->fetchColumn();
            $this->cropColumnsAvailable = (bool)$x && (bool)$y;
        } catch (Throwable) {
            $this->cropColumnsAvailable = false;
        }

        return $this->cropColumnsAvailable;
    }

    public function featured(int $limit=6): array
    {
        $limit=max(1,min($limit,24));
        $cropSelect=$this->hasCropColumns()
            ? ",(SELECT pi.crop_x FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.sort_order,pi.id LIMIT 1) image_crop_x,
                (SELECT pi.crop_y FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.sort_order,pi.id LIMIT 1) image_crop_y"
            : ",50 image_crop_x,50 image_crop_y";

        return db()->query(
            "SELECT p.*,COALESCE(NULLIF(u.shop_name,''),u.full_name) seller_name,
                    COALESCE(i.quantity,0) stock,
                    (SELECT pi.image_path FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.sort_order,pi.id LIMIT 1) image_path
                    {$cropSelect}
             FROM products p
             JOIN users u ON u.id=p.seller_id AND u.status='active'
             LEFT JOIN product_inventory i ON i.product_id=p.id
             WHERE p.status='available' AND COALESCE(i.quantity,0)>0
             ORDER BY p.created_at DESC LIMIT {$limit}"
        )->fetchAll();
    }

    public function search(?string $q,?string $category,?string $city,int $limit=60,string $sort='latest'): array
    {
        $sortSql=match($sort){'price_low'=>'p.price ASC,p.created_at DESC','price_high'=>'p.price DESC,p.created_at DESC','rating'=>'avg_rating DESC,p.created_at DESC',default=>'p.created_at DESC'};
        $cropSelect=$this->hasCropColumns()
            ? ",(SELECT pi.crop_x FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.sort_order,pi.id LIMIT 1) image_crop_x,
                (SELECT pi.crop_y FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.sort_order,pi.id LIMIT 1) image_crop_y"
            : ",50 image_crop_x,50 image_crop_y";

        $sql="SELECT p.*,COALESCE(NULLIF(u.shop_name,''),u.full_name) seller_name,u.username seller_username,
                    COALESCE(i.quantity,0) stock,
                    COALESCE((SELECT AVG(r.rating) FROM reviews r WHERE r.product_id=p.id),0) avg_rating,
                    COALESCE((SELECT COUNT(*) FROM reviews r WHERE r.product_id=p.id),0) review_count,
                    (SELECT pi.image_path FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.sort_order,pi.id LIMIT 1) image_path
                    {$cropSelect}
             FROM products p JOIN users u ON u.id=p.seller_id AND u.status='active'
             LEFT JOIN product_inventory i ON i.product_id=p.id
             WHERE p.status='available' AND COALESCE(i.quantity,0)>0";
        $params=[];
        if($q!==null&&$q!==''){$sql.=' AND (p.name LIKE ? OR p.description LIKE ? OR p.category LIKE ? OR p.city LIKE ? OR u.shop_name LIKE ?)';$n='%'.$q.'%';array_push($params,$n,$n,$n,$n,$n);}
        if($category!==null&&$category!==''){$sql.=' AND p.category=?';$params[]=$category;}
        if($city!==null&&$city!==''){$sql.=' AND p.city LIKE ?';$params[]='%'.$city.'%';}
        $sql.=' ORDER BY '.$sortSql.' LIMIT '.max(1,min($limit,100));
        $st=db()->prepare($sql);$st->execute($params);return $st->fetchAll();
    }

    public function find(int $id,bool $publicOnly=true): ?array
    {
        $visibility=$publicOnly?" AND p.status<>'archived' AND u.status='active'":'';
        $st=db()->prepare(
            "SELECT p.*,COALESCE(NULLIF(u.shop_name,''),u.full_name) seller_name,u.username seller_username,u.whatsapp seller_whatsapp,
                    COALESCE(i.quantity,0) stock,
                    (SELECT AVG(r.rating) FROM reviews r WHERE r.product_id=p.id) avg_rating,
                    (SELECT COUNT(*) FROM reviews r WHERE r.product_id=p.id) review_count
             FROM products p JOIN users u ON u.id=p.seller_id
             LEFT JOIN product_inventory i ON i.product_id=p.id
             WHERE p.id=?{$visibility} LIMIT 1"
        );
        $st->execute([$id]);
        $p=$st->fetch();
        if(!$p)return null;

        $imageSql=$this->hasCropColumns()
            ? 'SELECT id,image_path,sort_order,crop_x,crop_y FROM product_images WHERE product_id=? ORDER BY sort_order,id'
            : 'SELECT id,image_path,sort_order FROM product_images WHERE product_id=? ORDER BY sort_order,id';

        $st=db()->prepare($imageSql);
        $st->execute([$id]);
        $p['images']=$st->fetchAll();

        foreach($p['images'] as &$image){
            $image['crop_x']=isset($image['crop_x'])?(int)$image['crop_x']:50;
            $image['crop_y']=isset($image['crop_y'])?(int)$image['crop_y']:50;
        }
        unset($image);

        return $p;
    }

    public function sellerCount(int $sellerId): int
    {
        $st=db()->prepare('SELECT COUNT(*) FROM products WHERE seller_id=?');$st->execute([$sellerId]);return (int)$st->fetchColumn();
    }

    public function sellerStats(int $sellerId): array
    {
        $st=db()->prepare(
            "SELECT COUNT(*) total,
                    SUM(p.status='available' AND COALESCE(i.quantity,0)>0) active_count,
                    COALESCE(SUM(i.quantity),0) stock_total
             FROM products p LEFT JOIN product_inventory i ON i.product_id=p.id
             WHERE p.seller_id=?"
        );
        $st->execute([$sellerId]);$r=$st->fetch()?:[];
        return ['total'=>(int)($r['total']??0),'active'=>(int)($r['active_count']??0),'stock'=>(int)($r['stock_total']??0)];
    }

    public function bySeller(int $sellerId): array{return $this->bySellerPage($sellerId,100000,0);}

    public function bySellerPage(int $sellerId,int $limit,int $offset): array
    {
        $limit=max(1,min($limit,100));$offset=max(0,$offset);
        $cropSelect=$this->hasCropColumns()
            ? ",(SELECT pi.crop_x FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.sort_order,pi.id LIMIT 1) image_crop_x,
                (SELECT pi.crop_y FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.sort_order,pi.id LIMIT 1) image_crop_y"
            : ",50 image_crop_x,50 image_crop_y";

        $st=db()->prepare(
            "SELECT p.*,COALESCE(i.quantity,0) stock,
                    (SELECT pi.image_path FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.sort_order,pi.id LIMIT 1) image_path
                    {$cropSelect}
             FROM products p LEFT JOIN product_inventory i ON i.product_id=p.id
             WHERE p.seller_id=? ORDER BY p.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $st->execute([$sellerId]);return $st->fetchAll();
    }

    public function cropMetadataEnabled(): bool
    {
        return $this->hasCropColumns();
    }

    public function productImagesForSeller(int $productId,int $sellerId): array
    {
        $check=db()->prepare('SELECT 1 FROM products WHERE id=? AND seller_id=? LIMIT 1');
        $check->execute([$productId,$sellerId]);
        if(!$check->fetchColumn()){
            throw new RuntimeException('Produk tidak ditemukan atau bukan milik seller ini.');
        }

        $sql=$this->hasCropColumns()
            ? 'SELECT id,image_path,sort_order,crop_x,crop_y FROM product_images WHERE product_id=? ORDER BY sort_order,id'
            : 'SELECT id,image_path,sort_order FROM product_images WHERE product_id=? ORDER BY sort_order,id';

        $st=db()->prepare($sql);
        $st->execute([$productId]);
        $rows=$st->fetchAll();

        foreach($rows as &$row){
            $row['crop_x']=isset($row['crop_x'])?(int)$row['crop_x']:50;
            $row['crop_y']=isset($row['crop_y'])?(int)$row['crop_y']:50;
        }
        unset($row);

        return $rows;
    }

    public function updateExistingImages(
        int $productId,
        int $sellerId,
        array $deleteIds,
        array $orderedIds,
        int $primaryId,
        array $cropX,
        array $cropY
    ): array {
        $rows=$this->productImagesForSeller($productId,$sellerId);
        $byId=[];
        foreach($rows as $row){
            $byId[(int)$row['id']]=$row;
        }

        $deleteIds=array_values(array_unique(array_filter(
            array_map('intval',$deleteIds),
            static fn(int $id):bool=>$id>0
        )));

        $deletedPaths=[];
        $validDeleteIds=array_values(array_filter(
            $deleteIds,
            static fn(int $id):bool=>isset($byId[$id])
        ));

        if($validDeleteIds){
            $placeholders=implode(',',array_fill(0,count($validDeleteIds),'?'));
            $params=array_merge([$productId],$validDeleteIds);
            $st=db()->prepare("DELETE FROM product_images WHERE product_id=? AND id IN ({$placeholders})");
            $st->execute($params);

            foreach($validDeleteIds as $imageId){
                $deletedPaths[]=(string)$byId[$imageId]['image_path'];
                unset($byId[$imageId]);
            }
        }

        $orderedIds=array_values(array_unique(array_filter(
            array_map('intval',$orderedIds),
            static fn(int $id):bool=>$id>0
        )));

        $finalOrder=[];
        foreach($orderedIds as $imageId){
            if(isset($byId[$imageId]))$finalOrder[]=$imageId;
        }
        foreach(array_keys($byId) as $imageId){
            if(!in_array($imageId,$finalOrder,true))$finalOrder[]=$imageId;
        }

        if($primaryId>0&&in_array($primaryId,$finalOrder,true)){
            $finalOrder=array_values(array_filter(
                $finalOrder,
                static fn(int $id):bool=>$id!==$primaryId
            ));
            array_unshift($finalOrder,$primaryId);
        }

        $cropEnabled=$this->hasCropColumns();

        foreach($finalOrder as $sort=>$imageId){
            $x=max(0,min(100,(int)($cropX[$imageId]??50)));
            $y=max(0,min(100,(int)($cropY[$imageId]??50)));

            if($cropEnabled){
                db()->prepare('UPDATE product_images SET sort_order=?,crop_x=?,crop_y=? WHERE id=? AND product_id=?')
                    ->execute([$sort,$x,$y,$imageId,$productId]);
            }else{
                db()->prepare('UPDATE product_images SET sort_order=? WHERE id=? AND product_id=?')
                    ->execute([$sort,$imageId,$productId]);
            }
        }

        return $deletedPaths;
    }

    public function makeImagePrimary(int $productId,int $sellerId,int $imageId): void
    {
        $rows=$this->productImagesForSeller($productId,$sellerId);
        $ids=array_map(static fn(array $row):int=>(int)$row['id'],$rows);

        if(!in_array($imageId,$ids,true))return;

        $ordered=array_values(array_filter(
            $ids,
            static fn(int $id):bool=>$id!==$imageId
        ));
        array_unshift($ordered,$imageId);

        foreach($ordered as $sort=>$id){
            db()->prepare('UPDATE product_images SET sort_order=? WHERE id=? AND product_id=?')
                ->execute([$sort,$id,$productId]);
        }
    }

    public function create(array $data): int
    {
        $base=strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/','-',$data['name'])??'produk'));
        $slug=trim($base,'-').'-'.bin2hex(random_bytes(4));$pdo=db();$pdo->beginTransaction();
        try{
            $st=$pdo->prepare('INSERT INTO products(seller_id,name,slug,category,description,price,condition_label,city,status) VALUES(?,?,?,?,?,?,?,?,?)');
            $st->execute([$data['seller_id'],$data['name'],$slug,$data['category'],$data['description'],$data['price'],$data['condition_label'],$data['city'],$data['status']]);
            $id=(int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO product_inventory(product_id,quantity) VALUES(?,?)')->execute([$id,$data['stock']]);
            $pdo->commit();return $id;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function update(int $id,int $sellerId,array $data): bool
    {
        $st=db()->prepare('UPDATE products SET name=?,category=?,description=?,price=?,condition_label=?,city=?,status=? WHERE id=? AND seller_id=?');
        $st->execute([$data['name'],$data['category'],$data['description'],$data['price'],$data['condition_label'],$data['city'],$data['status'],$id,$sellerId]);return true;
    }

    public function updateAdmin(int $id,array $data): void
    {
        db()->prepare('UPDATE products SET name=?,category=?,description=?,price=?,condition_label=?,city=?,status=? WHERE id=?')
            ->execute([$data['name'],$data['category'],$data['description'],$data['price'],$data['condition_label'],$data['city'],$data['status'],$id]);
    }

    public function setStockAdmin(int $productId,int $quantity): void
    {
        db()->prepare('INSERT INTO product_inventory(product_id,quantity) VALUES(?,?) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity),updated_at=CURRENT_TIMESTAMP')
            ->execute([$productId,$quantity]);
        if($quantity>0)db()->prepare("UPDATE products SET status=IF(status='sold','available',status) WHERE id=? AND status='sold'")->execute([$productId]);
        elseif($quantity===0)db()->prepare("UPDATE products SET status=IF(status='available','sold',status) WHERE id=? AND status='available'")->execute([$productId]);
    }

    public function setStock(int $productId,int $sellerId,int $quantity): void
    {
        db()->prepare('INSERT INTO product_inventory(product_id,quantity) SELECT id,? FROM products WHERE id=? AND seller_id=? ON DUPLICATE KEY UPDATE quantity=VALUES(quantity),updated_at=CURRENT_TIMESTAMP')
            ->execute([$quantity,$productId,$sellerId]);
        if($quantity>0)db()->prepare("UPDATE products SET status=IF(status='sold','available',status) WHERE id=? AND seller_id=? AND status='sold'")->execute([$productId,$sellerId]);
        elseif($quantity===0)db()->prepare("UPDATE products SET status=IF(status='available','sold',status) WHERE id=? AND seller_id=? AND status='available'")->execute([$productId,$sellerId]);
    }

    public function archive(int $id,int $sellerId): void{db()->prepare("UPDATE products SET status='archived' WHERE id=? AND seller_id=?")->execute([$id,$sellerId]);}
    public function restore(int $id,int $sellerId): void{db()->prepare("UPDATE products SET status='available' WHERE id=? AND seller_id=? AND status='archived'")->execute([$id,$sellerId]);}
    public function archiveAdmin(int $id): void{db()->prepare("UPDATE products SET status='archived' WHERE id=?")->execute([$id]);}
    public function restoreAdmin(int $id): void{db()->prepare("UPDATE products SET status='available' WHERE id=? AND status='archived'")->execute([$id]);}

    private function adminFilterSql(array $f): array
    {
        $where=['1=1'];$params=[];
        $q=trim((string)($f['q']??''));
        if($q!==''){$where[]='(p.name LIKE ? OR p.category LIKE ? OR p.city LIKE ? OR u.full_name LIKE ? OR u.username LIKE ? OR u.shop_name LIKE ?)';$n='%'.$q.'%';array_push($params,$n,$n,$n,$n,$n,$n);}
        if(!empty($f['seller_id'])){$where[]='p.seller_id=?';$params[]=(int)$f['seller_id'];}
        if(!empty($f['status'])){$where[]='p.status=?';$params[]=(string)$f['status'];}
        if(!empty($f['category'])){$where[]='p.category=?';$params[]=(string)$f['category'];}
        return ['WHERE '.implode(' AND ',$where),$params];
    }

    public function adminCount(array $filters=[]): int
    {
        [$where,$params]=$this->adminFilterSql($filters);
        $st=db()->prepare("SELECT COUNT(*) FROM products p JOIN users u ON u.id=p.seller_id {$where}");
        $st->execute($params);return (int)$st->fetchColumn();
    }

    public function adminPage(array $filters,int $limit,int $offset): array
    {
        $limit=max(1,min($limit,100));$offset=max(0,$offset);
        [$where,$params]=$this->adminFilterSql($filters);
        $st=db()->prepare(
            "SELECT p.*,u.id seller_user_id,u.full_name seller_full_name,u.username seller_username,
                    COALESCE(NULLIF(u.shop_name,''),u.full_name) seller_name,
                    COALESCE(i.quantity,0) stock
             FROM products p JOIN users u ON u.id=p.seller_id
             LEFT JOIN product_inventory i ON i.product_id=p.id
             {$where}
             ORDER BY p.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $st->execute($params);return $st->fetchAll();
    }

    public function adminAll(): array{return $this->adminPage([],100000,0);}
}
