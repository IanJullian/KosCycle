<?php
declare(strict_types=1);

class ReviewRepository
{
    public function forProduct(int $productId): array
    {
        $stmt = db()->prepare(
            'SELECT r.*, u.full_name, u.username
             FROM reviews r JOIN users u ON u.id = r.buyer_id
             WHERE r.product_id = ?
             ORDER BY r.id DESC'
        );
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    public function canReview(int $buyerId, int $productId): bool
    {
        $stmt = db()->prepare(
            "SELECT 1
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.buyer_id = ? AND oi.product_id = ? AND o.status = 'completed'
             LIMIT 1"
        );
        $stmt->execute([$buyerId, $productId]);
        return (bool) $stmt->fetchColumn();
    }

    public function exists(int $buyerId, int $productId): bool
    {
        $stmt = db()->prepare('SELECT 1 FROM reviews WHERE buyer_id = ? AND product_id = ? LIMIT 1');
        $stmt->execute([$buyerId, $productId]);
        return (bool) $stmt->fetchColumn();
    }

    public function create(int $buyerId, int $productId, int $rating, string $review): void
    {
        $stmt = db()->prepare('INSERT INTO reviews (buyer_id, product_id, rating, review_text) VALUES (?, ?, ?, ?)');
        $stmt->execute([$buyerId, $productId, $rating, $review]);
    }

    public function all(): array
    {
        return db()->query(
            "SELECT r.*, u.full_name AS buyer_name, p.name AS product_name
             FROM reviews r JOIN users u ON u.id = r.buyer_id JOIN products p ON p.id = r.product_id
             ORDER BY r.id DESC"
        )->fetchAll();
    }

    public function delete(int $id): void
    {
        db()->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
    }
}
