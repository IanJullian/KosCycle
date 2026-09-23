# KosCycle — Integrasi Midtrans Sandbox

Paket ini menambahkan pembayaran simulasi Midtrans Snap ke KosCycle.

## File yang diperbarui/ditambahkan

- `composer.json`
- `.gitignore`
- `config/config.php`
- `config/.env.php.example`
- `includes/Services/MidtransConfig.php`
- `includes/Services/PaymentService.php`
- `includes/Repositories/OrderRepository.php`
- `pages/payment.php`
- `pages/orders.php`
- `pages/seller-orders.php`
- `tools/midtrans/notification.php`
- `database.mysql`
- `index.php`

## 1. Buat konfigurasi rahasia

Salin:

`config/.env.php.example`

menjadi:

`config/.env.php`

Isi database dan 3 data Midtrans:

```php
'MIDTRANS_MERCHANT_ID' => '...',
'MIDTRANS_CLIENT_KEY' => '...',
'MIDTRANS_SERVER_KEY' => '...',
'MIDTRANS_IS_PRODUCTION' => false,
```

Jangan commit `config/.env.php`.

## 2. Jalankan Composer

Jika hosting memiliki SSH + Composer:

```bash
composer update midtrans/midtrans-php --with-all-dependencies
```

Untuk deployment berikutnya:

```bash
composer install --no-dev --optimize-autoloader
```

Alternatif jika hosting tidak menyediakan Composer: jalankan Composer di komputer sendiri lalu upload folder `vendor/` hasil install bersama project.

## 3. Database

Jalankan bagian `CREATE TABLE IF NOT EXISTS payments (...)` dari `database.mysql` di phpMyAdmin/database hosting yang dipakai KosCycle.

Tidak perlu migration framework.

## 4. Tambahkan halaman payment

`index.php` sudah mendapatkan page:

`payment`

Halaman pembayaran:

`?page=payment&id=ORDER_ID`

Tombolnya muncul di detail pesanan setelah seller mengubah order menjadi `accepted`.

## 5. Notification URL

Setelah website online, isi Payment Notification URL di Midtrans dengan:

`https://DOMAIN-KOSCYCLE-KAMU/tools/midtrans/notification.php`

Gunakan HTTPS untuk hosting production.

## 6. Alur

Customer checkout
→ order `requested`
→ seller `accepted`
→ buyer klik `Bayar dengan Midtrans`
→ Snap Sandbox
→ Midtrans notification
→ `payments.payment_status` menjadi `paid`, `pending`, `failed`, dan seterusnya.

Status `orders.status` tidak otomatis diubah menjadi `completed` ketika pembayaran sukses. Seller tetap menyelesaikan order lewat alur KosCycle.

## 7. Verifikasi webhook

Endpoint memeriksa:

`SHA512(order_id + status_code + gross_amount + ServerKey)`

dan mencocokkan `merchant_id` jika tersedia.

## 8. Catatan keamanan

Jangan menaruh Server Key di JavaScript.
Jangan commit `config/.env.php`.
Jika credential lama pernah ter-push ke repository public, anggap credential tersebut sudah terekspos dan rotasi.

## 9. Sandbox

Jangan gunakan kartu/data pembayaran sungguhan. Gunakan data uji yang disediakan Midtrans Sandbox.
