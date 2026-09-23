KosCycle Hotfix V8
===================

Tujuan patch ini
----------------
Patch ini TIDAK mengganti konsep visual chat yang sebelumnya sudah bagus.
V8 justru mengembalikan CSS chat KosCycle yang baik dan hanya menambahkan perbaikan
perilaku yang memang diminta.

Basis:
- Repo: IanJullian/KosCycle
- Branch: main
- V8 dibuat sebagai lanjutan dari Optimization V7.

1. CHAT ADMIN / CUSTOMER / SELLER
----------------------------------
- chat.css versi KosCycle yang baik dipertahankan dan disertakan lagi.
- CSS chat dimuat dari <head> melalui includes/header.php, bukan bergantung pada link
  stylesheet di tengah body.
- Desktop tetap dua kolom: inbox di kiri, percakapan di kanan.
- Mobile tetap memakai pola daftar -> buka percakapan.
- Subjek DAN isi pengaduan awal terlihat ketika pengaduan dibuka.
- Badge unread navbar langsung hilang ketika pusat Chat dibuka.
- Pesan milik pengirim:
    ✓  = terkirim
    ✓✓ = sudah dibaca penerima
  Ini simetris: customer dan seller/admin melihat status baca untuk pesan mereka sendiri.
- Polling chat memakai interval adaptif agar lebih ringan:
    aktif sekitar 2 detik,
    idle sekitar 5 detik,
    tab tidak aktif sekitar 8 detik.
- Satu customer + satu seller + satu produk tetap menggunakan satu room chat marketplace.

2. MOBILE / HAMBURGER / LOGIN
------------------------------
- navbar mobile punya z-index yang jelas.
- hamburger tetap berada di atas konten.
- menu collapse muncul tepat di bawah navbar dan scrollable.
- halaman login diberi top spacing berdasarkan tinggi navbar + safe area,
  sehingga form tidak tertimpa navbar.
- input tetap 16px pada mobile agar browser tidak auto-zoom.

3. FOTO PRODUK SELLER
---------------------
Seller sekarang dapat:
- upload sampai total 5 gambar,
- menghapus foto tersimpan mana pun,
- memilih foto utama,
- mengubah urutan foto tersimpan dengan tombol kiri/kanan,
- mengatur crop 1:1 dengan fokus Horizontal dan Vertikal,
- melihat preview crop secara langsung sebelum menyimpan,
- menghapus foto baru dari preview sebelum submit.

Crop tidak merusak file asli.
Yang disimpan hanya titik fokus crop:
- crop_x: 0..100
- crop_y: 0..100

Buyer melihat titik crop tersebut di:
- Marketplace
- Detail Produk
- rekomendasi Dashboard Customer

Seller juga melihat crop foto utama pada halaman Produk Saya.

4. DATABASE CROP
----------------
Jalankan sekali di phpMyAdmin:

database.image-crop-v8.sql

Script aman dijalankan ulang karena mengecek keberadaan kolom terlebih dahulu.

Jika SQL belum dijalankan:
- halaman tetap tidak 500,
- editor tetap bekerja untuk urutan/hapus/foto utama,
- crop posisi hanya belum dapat tersimpan permanen,
- seller akan melihat warning pada form produk.

5. CARA PASANG
--------------
1. Backup project lokal.
2. Extract KosCycle-Hotfix-V8.zip.
3. Merge isi folder KosCycle-Hotfix-V8 ke root project KosCycle.
4. Jangan hapus:
   - config/.env.php
   - vendor/
   - uploads/
5. Import database.image-crop-v8.sql sekali.
6. Ctrl + F5.

6. TEST WAJIB
-------------
A. Admin chat:
   Login admin -> Chat.
   Inbox harus tetap berbentuk card dua kolom, bukan link mentah memanjang.

B. Mobile:
   Buka DevTools 390px.
   Hamburger harus dapat dibuka dan login tidak tertimpa navbar.

C. Crop:
   Seller -> Produk Saya -> Edit.
   Pilih satu foto -> ubah slider horizontal / vertikal.
   Preview harus bergerak.
   Simpan -> buka produk sebagai buyer -> posisi gambar harus sama.

D. Delete:
   Edit produk -> centang Hapus pada satu foto -> Simpan.
   Foto tersebut harus hilang tetapi foto lain tetap ada.

E. Foto utama:
   Pilih radio Foto utama -> Simpan.
   Gambar tersebut harus menjadi gambar pertama di detail produk.

Keamanan:
- Patch tidak berisi config/.env.php.
- Tidak berisi key database, reCAPTCHA, SMTP, atau Midtrans.
