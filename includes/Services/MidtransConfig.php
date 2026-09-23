<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

if (MIDTRANS_SERVER_KEY === '') {
    throw new RuntimeException('Midtrans Server Key belum diisi.');
}

if (MIDTRANS_CLIENT_KEY === '') {
    throw new RuntimeException('Midtrans Client Key belum diisi.');
}

\Midtrans\Config::$serverKey = MIDTRANS_SERVER_KEY;
\Midtrans\Config::$isProduction = MIDTRANS_IS_PRODUCTION;
\Midtrans\Config::$isSanitized = true;
\Midtrans\Config::$is3ds = true;
