-- KosCycle Optimization V7
-- OPTIONAL: jalankan hanya jika database sudah memiliki chat_conversations/chat_messages.
-- Script ini menggabungkan duplikat chat marketplace lama agar satu customer + seller + produk hanya punya satu thread.
-- BACKUP DATABASE terlebih dahulu.

START TRANSACTION;

CREATE TEMPORARY TABLE kc_chat_keeper AS
SELECT customer_id, seller_id, product_id, MAX(id) AS keep_id
FROM chat_conversations
WHERE type='marketplace'
GROUP BY customer_id, seller_id, product_id
HAVING COUNT(*) > 1;

UPDATE chat_messages m
JOIN chat_conversations c ON c.id=m.conversation_id
JOIN kc_chat_keeper k
  ON k.customer_id=c.customer_id
 AND k.seller_id=c.seller_id
 AND k.product_id=c.product_id
SET m.conversation_id=k.keep_id
WHERE c.type='marketplace' AND c.id<>k.keep_id;

DELETE c
FROM chat_conversations c
JOIN kc_chat_keeper k
  ON k.customer_id=c.customer_id
 AND k.seller_id=c.seller_id
 AND k.product_id=c.product_id
WHERE c.type='marketplace' AND c.id<>k.keep_id;

UPDATE chat_conversations c
SET c.last_message_at=(
    SELECT MAX(m.created_at) FROM chat_messages m WHERE m.conversation_id=c.id
)
WHERE c.type='marketplace';

DROP TEMPORARY TABLE kc_chat_keeper;

COMMIT;

-- Jangan menambah UNIQUE INDEX jika database lama belum dibersihkan.
-- Setelah script di atas sukses, index berikut boleh dibuat sekali:
-- ALTER TABLE chat_conversations
-- ADD UNIQUE KEY uq_marketplace_room (type, customer_id, seller_id, product_id);
