-- KosCycle V8 - metadata crop foto produk
-- Aman dijalankan ulang: ALTER hanya dilakukan bila kolom belum tersedia.
-- Backup database tetap disarankan sebelum perubahan schema.

SET @current_db = DATABASE();

SET @has_crop_x = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @current_db
      AND TABLE_NAME = 'product_images'
      AND COLUMN_NAME = 'crop_x'
);

SET @sql_crop_x = IF(
    @has_crop_x = 0,
    'ALTER TABLE product_images ADD COLUMN crop_x TINYINT UNSIGNED NOT NULL DEFAULT 50 AFTER sort_order',
    'SELECT 1'
);

PREPARE stmt_crop_x FROM @sql_crop_x;
EXECUTE stmt_crop_x;
DEALLOCATE PREPARE stmt_crop_x;

SET @has_crop_y = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @current_db
      AND TABLE_NAME = 'product_images'
      AND COLUMN_NAME = 'crop_y'
);

SET @sql_crop_y = IF(
    @has_crop_y = 0,
    'ALTER TABLE product_images ADD COLUMN crop_y TINYINT UNSIGNED NOT NULL DEFAULT 50 AFTER crop_x',
    'SELECT 1'
);

PREPARE stmt_crop_y FROM @sql_crop_y;
EXECUTE stmt_crop_y;
DEALLOCATE PREPARE stmt_crop_y;
