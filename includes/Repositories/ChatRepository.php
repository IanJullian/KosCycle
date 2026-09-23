<?php
declare(strict_types=1);

class ChatRepository
{
    private function conversationForUser(int $conversationId, int $userId, string $role): ?array
    {
        $sql = "SELECT
                    c.id,c.type,c.customer_id,c.seller_id,c.product_id,c.subject,c.status,
                    c.created_by,c.last_message_at,c.created_at,c.updated_at,
                    p.name AS product_name,
                    COALESCE(NULLIF(s.shop_name,''),s.full_name) AS seller_name,
                    cu.full_name AS customer_name,
                    (SELECT m0.body FROM chat_messages m0 WHERE m0.conversation_id=c.id ORDER BY m0.id ASC LIMIT 1) AS first_message
                FROM chat_conversations c
                LEFT JOIN products p ON p.id=c.product_id
                LEFT JOIN users s ON s.id=c.seller_id
                LEFT JOIN users cu ON cu.id=c.customer_id
                WHERE c.id=?";
        $params = [$conversationId];

        if ($role === 'admin') {
            $sql .= " AND c.type='support'";
        } else {
            $sql .= " AND (
                (c.type='marketplace' AND (c.customer_id=? OR c.seller_id=?))
                OR
                (c.type='support' AND (c.customer_id=? OR c.seller_id=?))
            )";
            array_push($params, $userId, $userId, $userId, $userId);
        }

        $sql .= ' LIMIT 1';
        $st = db()->prepare($sql);
        $st->execute($params);
        $c = $st->fetch() ?: null;
        if (!$c) return null;

        if ($role === 'admin') {
            $c['counterpart_name'] = $c['customer_name'] ?: ($c['seller_name'] ?: 'Pengguna');
        } elseif ($c['type'] === 'support') {
            $c['counterpart_name'] = 'Admin KosCycle';
        } elseif ((int)$c['customer_id'] === $userId) {
            $c['counterpart_name'] = $c['seller_name'] ?: 'Seller';
        } else {
            $c['counterpart_name'] = $c['customer_name'] ?: 'Customer';
        }
        return $c;
    }

    public function unreadCount(int $userId, string $role): int
    {
        if ($role === 'admin') {
            $st = db()->prepare(
                "SELECT COUNT(*)
                 FROM chat_messages m
                 JOIN chat_conversations c ON c.id=m.conversation_id
                 WHERE c.type='support' AND c.status='open'
                   AND m.sender_id<>? AND m.read_at IS NULL"
            );
            $st->execute([$userId]);
            return (int)$st->fetchColumn();
        }

        $st = db()->prepare(
            "SELECT COUNT(*)
             FROM chat_messages m
             JOIN chat_conversations c ON c.id=m.conversation_id
             WHERE c.status='open'
               AND (
                    (c.type='marketplace' AND (c.customer_id=? OR c.seller_id=?))
                    OR
                    (c.type='support' AND (c.customer_id=? OR c.seller_id=?))
               )
               AND m.sender_id<>?
               AND m.read_at IS NULL"
        );
        $st->execute([$userId,$userId,$userId,$userId,$userId]);
        return (int)$st->fetchColumn();
    }

    public function markAllReadForUser(int $userId, string $role): void
    {
        if ($role === 'admin') {
            $st = db()->prepare(
                "UPDATE chat_messages m
                 JOIN chat_conversations c ON c.id=m.conversation_id
                 SET m.read_at=COALESCE(m.read_at,NOW())
                 WHERE c.type='support' AND c.status='open'
                   AND m.sender_id<>? AND m.read_at IS NULL"
            );
            $st->execute([$userId]);
            return;
        }

        $st = db()->prepare(
            "UPDATE chat_messages m
             JOIN chat_conversations c ON c.id=m.conversation_id
             SET m.read_at=COALESCE(m.read_at,NOW())
             WHERE c.status='open'
               AND (
                    (c.type='marketplace' AND (c.customer_id=? OR c.seller_id=?))
                    OR
                    (c.type='support' AND (c.customer_id=? OR c.seller_id=?))
               )
               AND m.sender_id<>?
               AND m.read_at IS NULL"
        );
        $st->execute([$userId,$userId,$userId,$userId,$userId]);
    }

    public function listForUser(int $userId, string $role): array
    {
        if ($role === 'admin') {
            $st = db()->prepare(
                "SELECT c.id,c.type,c.status,c.subject,c.product_id,c.last_message_at,c.created_at,
                        COALESCE(cu.full_name,su.full_name,'Pengguna') counterpart_name,
                        p.name product_name,
                        (SELECT m.body FROM chat_messages m WHERE m.conversation_id=c.id ORDER BY m.id DESC LIMIT 1) last_message,
                        (SELECT COUNT(*) FROM chat_messages m2 WHERE m2.conversation_id=c.id AND m2.sender_id<>? AND m2.read_at IS NULL) unread_count
                 FROM chat_conversations c
                 LEFT JOIN users cu ON cu.id=c.customer_id
                 LEFT JOIN users su ON su.id=c.seller_id
                 LEFT JOIN products p ON p.id=c.product_id
                 WHERE c.type='support'
                 ORDER BY COALESCE(c.last_message_at,c.created_at) DESC,c.id DESC"
            );
            $st->execute([$userId]);
            return $st->fetchAll();
        }

        // Marketplace legacy duplicates are hidden: only the newest thread for
        // the same customer + seller + product is listed.
        $st = db()->prepare(
            "SELECT c.id,c.type,c.status,c.subject,c.product_id,c.last_message_at,c.created_at,
                    CASE
                      WHEN c.type='support' THEN 'Admin KosCycle'
                      WHEN c.customer_id=? THEN COALESCE(NULLIF(s.shop_name,''),s.full_name)
                      ELSE cu.full_name
                    END counterpart_name,
                    p.name product_name,
                    (SELECT m.body FROM chat_messages m WHERE m.conversation_id=c.id ORDER BY m.id DESC LIMIT 1) last_message,
                    (SELECT COUNT(*) FROM chat_messages m2 WHERE m2.conversation_id=c.id AND m2.sender_id<>? AND m2.read_at IS NULL) unread_count
             FROM chat_conversations c
             LEFT JOIN users s ON s.id=c.seller_id
             LEFT JOIN users cu ON cu.id=c.customer_id
             LEFT JOIN products p ON p.id=c.product_id
             WHERE (
                 c.type='support'
                 OR c.id=(
                    SELECT MAX(c2.id)
                    FROM chat_conversations c2
                    WHERE c2.type='marketplace'
                      AND c2.customer_id=c.customer_id
                      AND c2.seller_id=c.seller_id
                      AND c2.product_id=c.product_id
                 )
             )
             AND (
                 (c.type='marketplace' AND (c.customer_id=? OR c.seller_id=?))
                 OR
                 (c.type='support' AND (c.customer_id=? OR c.seller_id=?))
             )
             ORDER BY COALESCE(c.last_message_at,c.created_at) DESC,c.id DESC"
        );
        $st->execute([$userId,$userId,$userId,$userId,$userId,$userId]);
        return $st->fetchAll();
    }

    public function find(int $conversationId, int $userId, string $role): ?array
    {
        return $this->conversationForUser($conversationId,$userId,$role);
    }

    public function messages(int $conversationId, int $userId, string $role, int $afterId=0): array
    {
        if (!$this->conversationForUser($conversationId,$userId,$role)) {
            throw new RuntimeException('Percakapan tidak ditemukan atau tidak dapat diakses.');
        }
        $sql = "SELECT m.id,m.conversation_id,m.sender_id,m.body,m.created_at,m.read_at,
                       u.full_name sender_name,u.role sender_role
                FROM chat_messages m
                JOIN users u ON u.id=m.sender_id
                WHERE m.conversation_id=?";
        $params = [$conversationId];
        if ($afterId > 0) {
            $sql .= ' AND m.id>?';
            $params[] = $afterId;
        }
        $sql .= ' ORDER BY m.id ASC LIMIT 100';
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public function findMessage(int $messageId,int $conversationId,int $userId,string $role): ?array
    {
        if (!$this->conversationForUser($conversationId,$userId,$role)) {
            throw new RuntimeException('Percakapan tidak ditemukan atau tidak dapat diakses.');
        }
        $st = db()->prepare(
            "SELECT m.id,m.conversation_id,m.sender_id,m.body,m.created_at,m.read_at,
                    u.full_name sender_name,u.role sender_role
             FROM chat_messages m
             JOIN users u ON u.id=m.sender_id
             WHERE m.id=? AND m.conversation_id=? LIMIT 1"
        );
        $st->execute([$messageId,$conversationId]);
        return $st->fetch() ?: null;
    }

    public function markRead(int $conversationId,int $userId,string $role): void
    {
        if (!$this->conversationForUser($conversationId,$userId,$role)) {
            throw new RuntimeException('Percakapan tidak ditemukan atau tidak dapat diakses.');
        }
        db()->prepare(
            "UPDATE chat_messages
             SET read_at=COALESCE(read_at,NOW())
             WHERE conversation_id=? AND sender_id<>? AND read_at IS NULL"
        )->execute([$conversationId,$userId]);
    }

    public function readUpto(int $conversationId,int $userId,string $role): int
    {
        if (!$this->conversationForUser($conversationId,$userId,$role)) {
            throw new RuntimeException('Percakapan tidak ditemukan atau tidak dapat diakses.');
        }
        $st = db()->prepare(
            "SELECT COALESCE(MAX(id),0)
             FROM chat_messages
             WHERE conversation_id=? AND sender_id=? AND read_at IS NOT NULL"
        );
        $st->execute([$conversationId,$userId]);
        return (int)$st->fetchColumn();
    }

    public function startMarketplace(int $userId,string $role,int $productId): int
    {
        if ($role !== 'customer') throw new RuntimeException('Chat seller hanya dapat dimulai oleh customer.');

        $st = db()->prepare(
            "SELECT p.id,p.seller_id,s.role seller_role,s.status seller_status
             FROM products p
             JOIN users s ON s.id=p.seller_id
             WHERE p.id=? LIMIT 1"
        );
        $st->execute([$productId]);
        $product = $st->fetch();

        if (!$product || $product['seller_role'] !== 'seller' || $product['seller_status'] !== 'active') {
            throw new RuntimeException('Seller atau produk tidak ditemukan.');
        }
        if ((int)$product['seller_id'] === $userId) {
            throw new RuntimeException('Kamu tidak dapat memulai chat dengan akun sendiri.');
        }

        // Exactly one reusable marketplace room per customer + seller + product.
        $st = db()->prepare(
            "SELECT id,status
             FROM chat_conversations
             WHERE type='marketplace' AND customer_id=? AND seller_id=? AND product_id=?
             ORDER BY id DESC LIMIT 1"
        );
        $st->execute([$userId,(int)$product['seller_id'],$productId]);
        $existing = $st->fetch();

        if ($existing) {
            if ($existing['status'] !== 'open') {
                db()->prepare("UPDATE chat_conversations SET status='open',updated_at=CURRENT_TIMESTAMP WHERE id=?")
                    ->execute([(int)$existing['id']]);
            }
            return (int)$existing['id'];
        }

        $st = db()->prepare(
            "INSERT INTO chat_conversations(type,customer_id,seller_id,product_id,status,created_by)
             VALUES('marketplace',?,?,?,'open',?)"
        );
        $st->execute([$userId,(int)$product['seller_id'],$productId,$userId]);
        return (int)db()->lastInsertId();
    }

    public function startSupport(int $userId,string $role,string $subject,string $body): int
    {
        if (!in_array($role,['customer','seller'],true)) {
            throw new RuntimeException('Hanya customer atau seller yang dapat membuat pengaduan.');
        }

        $subject = trim($subject);
        $body = trim($body);
        if ($subject === '' || mb_strlen($subject) > 160) {
            throw new RuntimeException('Subjek pengaduan wajib diisi dan maksimal 160 karakter.');
        }
        if ($body === '' || mb_strlen($body) > 2000) {
            throw new RuntimeException('Isi pengaduan wajib diisi dan maksimal 2000 karakter.');
        }

        $customerId = $role === 'customer' ? $userId : null;
        $sellerId = $role === 'seller' ? $userId : null;

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                "INSERT INTO chat_conversations(type,customer_id,seller_id,subject,status,created_by,last_message_at)
                 VALUES('support',?,?,?,'open',?,NOW())"
            );
            $st->execute([$customerId,$sellerId,$subject,$userId]);
            $conversationId = (int)$pdo->lastInsertId();

            $msg = $pdo->prepare(
                'INSERT INTO chat_messages(conversation_id,sender_id,body) VALUES(?,?,?)'
            );
            $msg->execute([$conversationId,$userId,$body]);

            $pdo->commit();
            return $conversationId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function sendMessage(int $conversationId,int $userId,string $role,string $body): int
    {
        $conversation = $this->conversationForUser($conversationId,$userId,$role);
        if (!$conversation) throw new RuntimeException('Percakapan tidak ditemukan atau tidak dapat diakses.');
        if ($conversation['status'] !== 'open') throw new RuntimeException('Percakapan sudah ditutup.');

        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 2000) {
            throw new RuntimeException('Pesan wajib diisi dan maksimal 2000 karakter.');
        }
        if ($role === 'admin' && $conversation['type'] !== 'support') {
            throw new RuntimeException('Admin hanya menangani pengaduan.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_id,body) VALUES(?,?,?)');
            $st->execute([$conversationId,$userId,$body]);
            $messageId = (int)$pdo->lastInsertId();

            $pdo->prepare(
                'UPDATE chat_conversations SET last_message_at=NOW(),updated_at=CURRENT_TIMESTAMP WHERE id=?'
            )->execute([$conversationId]);

            $pdo->commit();
            return $messageId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function closeConversation(int $conversationId,int $userId,string $role): void
    {
        $conversation = $this->conversationForUser($conversationId,$userId,$role);
        if (!$conversation) throw new RuntimeException('Percakapan tidak ditemukan atau tidak dapat diakses.');
        if ($conversation['type'] !== 'support') throw new RuntimeException('Chat marketplace tidak dapat ditutup dari fitur pengaduan.');
        if ($role !== 'admin') throw new RuntimeException('Hanya admin yang dapat menutup pengaduan.');

        db()->prepare(
            "UPDATE chat_conversations SET status='closed',updated_at=CURRENT_TIMESTAMP WHERE id=? AND type='support'"
        )->execute([$conversationId]);
    }
}
