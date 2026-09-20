<?php
declare(strict_types=1);

$localConfigPath = is_file(__DIR__ . '/.env.php')
	? __DIR__ . '/.env.php'
	: __DIR__ . '/.env';
$localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
$localConfig = is_array($localConfig) ? $localConfig : [];

define('APP_NAME', (string) ($localConfig['APP_NAME'] ?? 'KosCycle'));
define('APP_ENV', (string) ($localConfig['APP_ENV'] ?? 'local'));
define('APP_URL', rtrim((string) ($localConfig['APP_URL'] ?? 'http://localhost/KosCycle'), '/'));

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
