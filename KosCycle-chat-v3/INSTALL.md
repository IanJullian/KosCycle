# KosCycle Chat + Buyer/Seller UX Revision v4

## Tambahan database
Jalankan:
- `database.mysql.chat.sql`

## File baru
- `includes/Repositories/ChatRepository.php`
- `pages/chat.php`
- `assets/css/chat.css`
- `assets/css/catalog-polish.css`
- `assets/css/product-detail-polish.css`
- `assets/css/review-polish.css`
- `assets/css/mobile-nav-fixes.css`
- `assets/css/customer-dashboard-polish.css`
- `assets/css/seller-orders-polish.css`

## File yang harus ditimpa
- `pages/customer-dashboard.php`
- `pages/seller-orders.php`
- `pages/marketplace.php`
- `pages/product-detail.php`
- `pages/review.php`
- `pages/admin-dashboard.php`
- `includes/header.php`
- `includes/Repositories/ProductRepository.php`
- `assets/js/app.js`
- `index.php`

## Catatan checkout → seller
Seller sekarang melihat kolom `Catatan dari customer` langsung pada setiap kartu pesanan. Catatan berasal dari `orders.note` yang dikirim saat checkout.

## Status pesanan seller
- requested → Menunggu konfirmasi
- accepted → Diproses
- completed → Selesai
- cancelled → Dibatalkan

Status completed/cancelled tidak lagi menampilkan form update status; diganti state UI yang jelas.

## Buyer dashboard
Kartu rekomendasi dibuat ulang dengan visual produk, kategori, kondisi, kota, seller, harga, dan CTA. Tidak mengubah design system utama KosCycle.

## Responsive hamburger
`assets/css/mobile-nav-fixes.css` + `assets/js/app.js` menjaga Bootstrap collapse tetap berjalan dan tidak mengintersep link di dalam hamburger.
