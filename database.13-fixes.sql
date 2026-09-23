-- KosCycle 13 Fixes V4 - database hotfix aman
-- Jalankan pada database KosCycle yang SUDAH memiliki schema utama database.mysql.
-- Script ini tidak DROP tabel dan tidak menghapus data existing.

-- Pastikan inventory ada untuk seluruh produk existing.
INSERT IGNORE INTO product_inventory (product_id, quantity)
SELECT id, 0 FROM products;

-- Schema live chat / pengaduan. IF NOT EXISTS mencegah error "table already exists".
CREATE TABLE IF NOT EXISTS chat_conversations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type ENUM('marketplace','support') NOT NULL,
  customer_id BIGINT UNSIGNED NULL,
  seller_id BIGINT UNSIGNED NULL,
  product_id BIGINT UNSIGNED NULL,
  subject VARCHAR(160) NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  created_by BIGINT UNSIGNED NOT NULL,
  last_message_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_chat_conv_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_chat_conv_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_chat_conv_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_chat_conv_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_chat_conv_customer (customer_id, status),
  INDEX idx_chat_conv_seller (seller_id, status),
  INDEX idx_chat_conv_product (product_id),
  INDEX idx_chat_conv_type_status (type, status, last_message_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS chat_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT UNSIGNED NOT NULL,
  sender_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  read_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_chat_msg_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_chat_msg_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_chat_msg_conversation (conversation_id, id),
  INDEX idx_chat_msg_unread (conversation_id, sender_id, read_at)
) ENGINE=InnoDB;

-- Pemeriksaan cepat setelah import:
SELECT 'product_inventory' AS table_name, COUNT(*) AS row_count FROM product_inventory
UNION ALL
SELECT 'chat_conversations', COUNT(*) FROM chat_conversations
UNION ALL
SELECT 'chat_messages', COUNT(*) FROM chat_messages;
