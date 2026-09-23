KosCycle Mobile Navigation V9
=============================

Masalah:
Pada mobile, link "Masuk" menghilang dari hamburger karena header memakai:
    d-none d-sm-inline

Bootstrap membuat link itu display:none pada layar <576px.

V9 memperbaikinya lewat CSS tanpa perlu mengubah header.php:
- "Masuk" ditampilkan lagi di dalam hamburger.
- "Masuk" dan "Daftar gratis" menjadi dua tombol full-width.
- menu hamburger dibuat lebih solid/tidak transparan terhadap hero.
- menu bisa scroll bila tinggi layar kecil.
- desktop tidak diubah.

Pasang:
Replace:
    assets/css/mobile-nav-fixes.css

Lalu Ctrl+F5.

Target guest mobile:
Beranda
Katalog
----------------
[ Masuk ]
[ Daftar gratis ↗ ]
