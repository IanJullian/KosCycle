<?php
declare(strict_types=1);

// Accept both the preferred config/.env.php location and the legacy root .env.php.
$configCandidates = [
    __DIR__ . '/.env.php',
    __DIR__ . '/.env',
    dirname(__DIR__) . '/.env.php',
    dirname(__DIR__) . '/.env',
];
$localConfigPath = null;
foreach ($configCandidates as $candidate) {
    if (is_file($candidate)) {
        $localConfigPath = $candidate;
        break;
    }
}
$localConfig = $localConfigPath !== null ? require $localConfigPath : [];
$localConfig = is_array($localConfig) ? $localConfig : [];

define('APP_NAME', (string) ($localConfig['APP_NAME'] ?? 'KosCycle'));
define('APP_ENV', (string) ($localConfig['APP_ENV'] ?? 'local'));

// Normalize deployed values such as "koscycle.page.gd" into a valid absolute URL.
$appUrl = trim((string) ($localConfig['APP_URL'] ?? ''));
if ($appUrl !== '' && !preg_match('~^https?://~i', $appUrl)) {
    $appUrl = 'https://' . ltrim($appUrl, '/');
}

// When APP_URL is omitted, derive it from the current request so hosted assets
// and internal links do not silently fall back to localhost.
if ($appUrl === '' && isset($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $basePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    $appUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . ($basePath === '/' ? '' : $basePath);
}
if ($appUrl === '') {
    $appUrl = 'http://localhost/KosCycle';
}
define('APP_URL', rtrim($appUrl, '/'));

define('DB_HOST', (string) ($localConfig['DB_HOST'] ?? '127.0.0.1'));
define('DB_NAME', (string) ($localConfig['DB_NAME'] ?? 'koscycle'));
define('DB_USER', (string) ($localConfig['DB_USER'] ?? 'root'));
define('DB_PASS', (string) ($localConfig['DB_PASS'] ?? ''));

define('RECAPTCHA_SITE_KEY', (string) ($localConfig['RECAPTCHA_SITE_KEY'] ?? 'GANTI_DENGAN_SITE_KEY'));
define('RECAPTCHA_SECRET_KEY', (string) ($localConfig['RECAPTCHA_SECRET_KEY'] ?? 'GANTI_DENGAN_SECRET_KEY'));

define('OTP_EXPIRY_MINUTES', (int) ($localConfig['OTP_EXPIRY_MINUTES'] ?? 10));
define('OTP_MAX_ATTEMPTS', (int) ($localConfig['OTP_MAX_ATTEMPTS'] ?? 5));
define('OTP_RATE_LIMIT_SECONDS', (int) ($localConfig['OTP_RATE_LIMIT_SECONDS'] ?? 60));

define('MAIL_FROM_EMAIL', (string) ($localConfig['MAIL_FROM_EMAIL'] ?? ''));
define('MAIL_FROM_NAME', (string) ($localConfig['MAIL_FROM_NAME'] ?? 'KosCycle'));
define('SMTP_HOST', (string) ($localConfig['SMTP_HOST'] ?? 'smtp.gmail.com'));
define('SMTP_PORT', (int) ($localConfig['SMTP_PORT'] ?? 587));
define('SMTP_USERNAME', (string) ($localConfig['SMTP_USERNAME'] ?? ''));
define('SMTP_PASSWORD', (string) ($localConfig['SMTP_PASSWORD'] ?? ''));

define('WHATSAPP_OTP_ENABLED', (bool) ($localConfig['WHATSAPP_OTP_ENABLED'] ?? false));
