<?php
declare(strict_types=1);

class ProductRepository
{
    public function featured(int $limit = 6): array
    {
        $limit = max(1, min($limit, 24));
        $stmt = db()->query(
            "SELECT p.*,
                    COALESCE(NULLIF(u.shop_name, ''), u.full_name) seller_name,
                    COALESCE(i.quantity, 0) stock,
                    (SELECT pi.image_path
                     FROM product_images pi
                     WHERE pi.product_id = p.id
                     ORDER BY pi.sort_order, pi.id
                     LIMIT 1) image_path
             FROM products p
             JOIN users u ON u.id = p.seller_id AND u.status = 'active'
             LEFT JOIN product_inventory i ON i.product_id = p.id
             WHERE p.status = 'available'
               AND COALESCE(i.quantity, 0) > 0
             ORDER BY p.created_at DESC
             LIMIT {$limit}"
        );
        return $stmt->fetchAll();
    }

    public function search(?string $q, ?string $category, ?string $city, int $limit = 60, string $sort = 'latest'): array
    {
        $sortSql = match ($sort) {
            'price_low' => 'p.price ASC, p.created_at DESC',
            'price_high' => 'p.price DESC, p.created_at DESC',
            'rating' => 'avg_rating DESC, p.created_at DESC',
            default => 'p.created_at DESC',
        };

        $sql = "SELECT p.*,
                       COALESCE(NULLIF(u.shop_name, ''), u.full_name) seller_name,
                       u.username seller_username,
                       COALESCE(i.quantity, 0) stock,
                       COALESCE((SELECT AVG(r.rating) FROM reviews r WHERE r.product_id = p.id), 0) avg_rating,
                       COALESCE((SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id), 0) review_count,
                       (SELECT pi.image_path
                        FROM product_images pi
                        WHERE pi.product_id = p.id
                        ORDER BY pi.sort_order, pi.id
                        LIMIT 1) image_path
                FROM products p
                JOIN users u ON u.id = p.seller_id AND u.status = 'active'
                LEFT JOIN product_inventory i ON i.product_id = p.id
                WHERE p.status = 'available'
                  AND COALESCE(i.quantity, 0) > 0";
        $params = [];

        if ($q !== null && $q !== '') {
            $sql .= ' AND (p.name LIKE ? OR p.description LIKE ? OR p.category LIKE ? OR p.city LIKE ? OR u.shop_name LIKE ?)';
            $needle = '%' . $q . '%';
            array_push($params, $needle, $needle, $needle, $needle, $needle);
        }

        if ($category !== null && $category !== '') {
            $sql .= ' AND p.category = ?';
            $params[] = $category;
        }

        if ($city !== null && $city !== '') {
            $sql .= ' AND p.city LIKE ?';
            $params[] = '%' . $city . '%';
        }

        $sql .= ' ORDER BY ' . $sortSql . ' LIMIT ' . max(1, min($limit, 100));
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id, bool $publicOnly = true): ?array
    {
        $visibility = $publicOnly ? " AND p.status <> 'archived' AND u.status = 'active'" : '';
        $stmt = db()->prepare(
            "SELECT p.*,
                    COALESCE(NULLIF(u.shop_name, ''), u.full_name) seller_name,
                    u.username seller_username,
                    u.whatsapp seller_whatsapp,
                    COALESCE(i.quantity, 0) stock,
                    (SELECT AVG(r.rating) FROM reviews r WHERE r.product_id = p.id) avg_rating,
                    (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id) review_count
             FROM products p
             JOIN users u ON u.id = p.seller_id
             LEFT JOIN product_inventory i ON i.product_id = p.id
             WHERE p.id = ?{$visibility}
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if (!$product) {
            return null;
        }

        $stmt = db()->prepare(
            'SELECT id, image_path, sort_order
             FROM product_images
             WHERE product_id = ?
             ORDER BY sort_order, id'
        );
        $stmt->execute([$id]);
        $product['images'] = $stmt->fetchAll();
        return $product;
    }

    public function bySeller(int $sellerId): array
    {
        $stmt = db()->prepare(
            "SELECT p.*,
                    COALESCE(i.quantity, 0) stock,
                    (SELECT pi.image_path
                     FROM product_images pi
                     WHERE pi.product_id = p.id
                     ORDER BY pi.sort_order, pi.id
                     LIMIT 1) image_path
             FROM products p
             LEFT JOIN product_inventory i ON i.product_id = p.id
             WHERE p.seller_id = ?
             ORDER BY p.id DESC"
        );
        $stmt->execute([$sellerId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $data['name']) ?? 'produk'));
        $slug = trim($base, '-') . '-' . bin2hex(random_bytes(4));
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO products
                    (seller_id, name, slug, category, description, price, condition_label, city, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['seller_id'],
                $data['name'],
                $slug,
                $data['category'],
                $data['description'],
                $data['price'],
                $data['condition_label'],
                $data['city'],
                $data['status'],
            ]);
            $id = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO product_inventory(product_id, quantity) VALUES(?, ?)');
            $stmt->execute([$id, $data['stock']]);

            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function update(int $id, int $sellerId, array $data): bool
    {
        $stmt = db()->prepare(
            'UPDATE products
             SET name=?, category=?, description=?, price=?, condition_label=?, city=?, status=?
             WHERE id=? AND seller_id=?'
        );
        $stmt->execute([
            $data['name'], $data['category'], $data['description'], $data['price'],
            $data['condition_label'], $data['city'], $data['status'], $id, $sellerId,
        ]);
        return true;
    }

    public function updateAdmin(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE products
             SET name=?, category=?, description=?, price=?, condition_label=?, city=?, status=?
             WHERE id=?'
        );
        $stmt->execute([
            $data['name'], $data['category'], $data['description'], $data['price'],
            $data['condition_label'], $data['city'], $data['status'], $id,
        ]);
    }

    public function setStockAdmin(int $productId, int $quantity): void
    {
        $stmt = db()->prepare(
            'INSERT INTO product_inventory(product_id, quantity)
             VALUES(?, ?)
             ON DUPLICATE KEY UPDATE quantity=VALUES(quantity), updated_at=CURRENT_TIMESTAMP'
        );
        $stmt->execute([$productId, $quantity]);

        if ($quantity > 0) {
            db()->prepare("UPDATE products SET status=IF(status='sold','available',status) WHERE id=? AND status='sold'")
                ->execute([$productId]);
        } elseif ($quantity === 0) {
            db()->prepare("UPDATE products SET status=IF(status='available','sold',status) WHERE id=? AND status='available'")
                ->execute([$productId]);
        }
    }

    public function setStock(int $productId, int $sellerId, int $quantity): void
    {
        $stmt = db()->prepare(
            'INSERT INTO product_inventory(product_id, quantity)
             SELECT id, ? FROM products WHERE id=? AND seller_id=?
             ON DUPLICATE KEY UPDATE quantity=VALUES(quantity), updated_at=CURRENT_TIMESTAMP'
        );
        $stmt->execute([$quantity, $productId, $sellerId]);

        if ($quantity > 0) {
            db()->prepare("UPDATE products SET status=IF(status='sold','available',status) WHERE id=? AND seller_id=? AND status='sold'")
                ->execute([$productId, $sellerId]);
        } elseif ($quantity === 0) {
            db()->prepare("UPDATE products SET status=IF(status='available','sold',status) WHERE id=? AND seller_id=? AND status='available'")
                ->execute([$productId, $sellerId]);
        }
    }

    public function archive(int $id, int $sellerId): void
    {
        db()->prepare("UPDATE products SET status='archived' WHERE id=? AND seller_id=?")
            ->execute([$id, $sellerId]);
    }

    public function restore(int $id, int $sellerId): void
    {
        db()->prepare("UPDATE products SET status='available' WHERE id=? AND seller_id=? AND status='archived'")
            ->execute([$id, $sellerId]);
    }

    public function archiveAdmin(int $id): void
    {
        db()->prepare("UPDATE products SET status='archived' WHERE id=?")
            ->execute([$id]);
    }

    public function restoreAdmin(int $id): void
    {
        db()->prepare("UPDATE products SET status='available' WHERE id=? AND status='archived'")
            ->execute([$id]);
    }

    public function adminAll(): array
    {
        return db()->query(
            "SELECT p.*, u.full_name seller_name, COALESCE(i.quantity, 0) stock
             FROM products p
             JOIN users u ON u.id=p.seller_id
             LEFT JOIN product_inventory i ON i.product_id=p.id
             ORDER BY p.id DESC"
        )->fetchAll();
    }
}
