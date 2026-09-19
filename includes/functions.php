<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token']);
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function old(string $key): string
{
    return e($_SESSION['old'][$key] ?? '');
}

function set_old(array $data): void
{
    $_SESSION['old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function recaptcha_valid(?string $response): bool
{
    if (RECAPTCHA_SECRET_KEY === 'GANTI_DENGAN_SECRET_KEY') {
        return true;
    }
    if (!$response) {
        return false;
    }
    $payload = http_build_query(['secret' => RECAPTCHA_SECRET_KEY, 'response' => $response]);
    $result = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, stream_context_create([
        'http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $payload],
    ]));
    return $result !== false && (json_decode($result, true)['success'] ?? false) === true;
}

function format_price(int $price): string
{
    return 'Rp ' . number_format($price, 0, ',', '.');
}

function normalize_phone(string $phone): string
{
    $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';
    return str_starts_with($digits, '0') ? '62' . substr($digits, 1) : $digits;
}

function deliver_otp(string $channel, string $destination, string $otp): bool
{
    if ($channel !== 'email') {
        return false;
    }
    if (SMTP_PASSWORD === '') {
        return false;
    }

    try {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = SMTP_HOST;
        $mailer->SMTPAuth = true;
        $mailer->Username = SMTP_USERNAME;
        $mailer->Password = SMTP_PASSWORD;
        $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Port = SMTP_PORT;
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mailer->addAddress($destination);
        $mailer->Subject = APP_NAME . ' - Kode OTP';
        $mailer->Body = "Kode OTP Anda: {$otp}\nBerlaku selama " . OTP_EXPIRY_MINUTES . ' menit.';
        return $mailer->send();
    } catch (\Throwable) {
        return false;
    }
}
