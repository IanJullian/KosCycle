KosCycle - Revisi Chat + Pesanan Buyer
======================================

Basis revisi:
- Repo: IanJullian/KosCycle
- Branch: main
- Basis source: commit 5c54482a7714cbdec3b5dbf8298531ee4b1ab1ea

Perubahan:
1. Chat menampilkan indikator centang pada kedua sisi pesan.
   - Pesan sendiri: 1 centang = tersimpan/terkirim, 2 centang = sudah dibaca penerima.
   - Pesan lawan: tampil 2 centang saat pesan sudah dibaca oleh akun yang sedang melihat chat.
2. Polling chat tetap berjalan setiap 3 detik dan tidak memaksa scroll kembali ke atas saat membaca pesan lama.
3. APP_URL dinormalisasi supaya nilai seperti "koscycle.page.gd" tetap menghasilkan URL asset yang valid (https://...).
4. Halaman pesanan buyer dan CSS pesanan disertakan untuk memastikan UI kartu/detail/ulasan tetap ter-style.

Cara pasang:
1. Backup folder KosCycle saat ini.
2. Extract ZIP ini ke folder project KosCycle dan pilih Replace/Merge.
3. JANGAN mengganti file .env.php yang sudah kamu punya. File itu sengaja tidak disertakan karena berisi credential.
4. Pastikan database chat sudah punya tabel chat_conversations dan chat_messages dengan kolom chat_messages.read_at. Bila belum, jalankan database.mysql.chat.sql.
5. Setelah upload ke hosting, hard-refresh browser (Ctrl+F5).

Catatan APP_URL:
- Local: http://localhost/KosCycle tetap didukung.
- Hosting: boleh "koscycle.page.gd" atau "https://koscycle.page.gd" karena config sekarang menormalkan keduanya.

Keamanan:
- Jangan commit .env.php ke repo publik.
- Credential database/SMTP/reCAPTCHA yang pernah tersimpan di repo publik sebaiknya di-rotate.


HOTFIX HUBUNGI ADMIN
- config/config.php sekarang membaca .env.php di config/ maupun root.
- APP_URL otomatis dinormalisasi ke http/https.
- Jika APP_URL kosong, URL aplikasi diambil dari host request.
- assets/js/app.js sekarang tidak membiarkan preloader menggantung tanpa batas.
- Tombol Hubungi admin tetap membuka ?page=chat&new=support.


PATCH LANJUTAN 23-09-2026
- Tombol Hubungi admin tidak lagi membuat thread support baru bila masih ada thread support terbuka milik user.
- Klik Hubungi admin langsung mengarah ke chat admin yang sudah ada. Saat form benar-benar belum punya thread aktif, pengaduan pertama membuat thread baru seperti biasa.
- Navigasi sekarang memberi status halaman aktif, menyorot parent dropdown admin, serta memiliki hover/active interaction yang membedakan customer, seller, dan admin tanpa mengubah struktur role-based menu.
- File tambahan/revisi utama: pages/chat.php, assets/js/app.js, assets/css/chat-receipts-polish.css.
