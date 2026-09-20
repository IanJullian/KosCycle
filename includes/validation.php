<?php
declare(strict_types=1);

function validate_product_input(array $data): array
{
    $errors = [];

    if (mb_strlen($data['name']) < 3 || mb_strlen($data['name']) > 160) {
        $errors[] = 'Nama produk harus 3-160 karakter.';
    }

    if (!is_numeric($data['price']) || (int) $data['price'] < 0) {
        $errors[] = 'Harga produk tidak valid.';
    }

    if (mb_strlen($data['category']) < 2 || mb_strlen($data['category']) > 80) {
        $errors[] = 'Kategori produk tidak valid.';
    }

    if (mb_strlen($data['description']) > 10000) {
        $errors[] = 'Deskripsi terlalu panjang.';
    }

    if (mb_strlen($data['condition_label']) < 2 || mb_strlen($data['condition_label']) > 80) {
        $errors[] = 'Kondisi produk tidak valid.';
    }

    if (mb_strlen($data['city']) < 2 || mb_strlen($data['city']) > 80) {
        $errors[] = 'Kota tidak valid.';
    }

    if (!is_numeric($data['stock']) || (int) $data['stock'] < 0) {
        $errors[] = 'Stok tidak boleh negatif.';
    }

    return $errors;
}
