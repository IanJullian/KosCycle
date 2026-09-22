# KosCycle v6 — Chat + Review Order UX Fix

Patch ini ditujukan untuk branch/main KosCycle setelah merge terbaru.

## Yang diperbaiki

1. **Seller > Pesanan**
   - Setelah order `completed`, setiap item langsung menampilkan apakah customer sudah memberi ulasan.
   - Rating 1–5, komentar, dan waktu ulasan tampil di kartu pesanan yang sama.
   - Jika belum ada ulasan, seller melihat status menunggu ulasan.

2. **Chat read receipt**
   - Pesan yang dikirim sendiri mendapat 1 centang saat tersimpan di server.
   - Menjadi 2 centang saat penerima membuka/membaca percakapan.
   - Masing-masing pihak melihat centang pada pesan yang DIA kirim sendiri, seperti pola WhatsApp.
   - Status read memakai kolom `read_at` yang sudah ada; tidak perlu migration tambahan.

3. **Chat tanpa refresh lompat ke atas**
   - Pengiriman pesan dilakukan AJAX sehingga halaman tidak reload.
   - Polling hanya mengambil pesan baru + status read receipt.
   - Jika user sedang membaca pesan lama, posisi scroll dipertahankan.
   - Jika user berada dekat bawah, pesan baru otomatis masuk dan scroll tetap di bawah.

4. **Buyer > Pesanan**
   - Status `Sudah diulas` dan rating tampil langsung pada detail order.
   - Tombol `Ulas` hanya tampil jika order selesai dan produk belum diulas.

## File

- `includes/Repositories/ChatRepository.php`
- `includes/Repositories/OrderRepository.php`
- `pages/chat.php`
- `pages/seller-orders.php`
- `pages/orders.php`
- `assets/css/chat-receipts-polish.css`
- `assets/css/order-review-polish.css`

## Catatan

Schema chat yang sekarang sudah memiliki `chat_messages.read_at`, jadi patch ini tidak menambah tabel/kolom baru.
