KosCycle Chat CSS Guaranteed
============================

Patch khusus saat chat/pengaduan tampil seperti HTML mentah.

Diagnosis: app.css/Bootstrap masih jalan, tetapi class khusus chat tidak ter-style. Itu berarti chat.css tidak sampai ke browser.

Patch ini TIDAK mengganti desain chat. Ia tetap memakai chat.css yang sama, lalu salinan CSS yang sama juga ditanam langsung di pages/chat.php sebagai fallback. Karena itu, walaupun APP_URL salah, chat.css 404, atau cache lama masih ada, halaman chat tetap harus rapi.

Pasang hanya:
- pages/chat.php
- assets/css/chat.css
- assets/css/chat-receipts-polish.css

Lalu Ctrl+F5.

Cek manual URL:
<APP_URL>/assets/css/chat.css

Jika URL itu 404, external CSS memang gagal.

Jika setelah patch ini chat MASIH polos/non-CSS, berarti browser tidak menjalankan pages/chat.php yang baru: biasanya file di-upload ke folder/project yang berbeda dari document root yang sedang dibuka.
