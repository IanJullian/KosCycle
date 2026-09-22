from pathlib import Path
import re

path = Path('pages/product-detail.php')
text = path.read_text(encoding='utf-8')
marker = "<section class=\"section-padding page-section\"><div class=\"container\"><div class=\"page-toolbar\"><?=back_link('marketplace','Kembali ke marketplace')?>"
starts = [m.start() for m in re.finditer(re.escape(marker), text)]
if not starts:
    print('Tidak ada blok duplikat yang cocok; tidak ada perubahan.')
    raise SystemExit(0)
# Remove the first matching legacy duplicate block through its old footer include.
start = starts[0]
footer = "<?php require __DIR__ . '/../includes/footer.php'; ?>"
end = text.find(footer, start)
if end == -1:
    raise SystemExit('Footer blok duplikat tidak ditemukan; file tidak diubah.')
end += len(footer)
path.write_text(text[:start].rstrip() + "\n" + text[end:].lstrip(), encoding='utf-8')
print('Blok detail produk duplikat berhasil dihapus.')
