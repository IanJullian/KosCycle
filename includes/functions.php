<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
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

function page_url(string $page, array $params = []): string
{
    $query = array_merge(['page' => $page], $params);
    return APP_URL . '/?' . http_build_query($query);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function verify_csrf(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals((string) $_SESSION['csrf_token'], (string) $_POST['csrf_token']);
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return is_string($value) ? $value : null;
}

function old(string $key): string
{
    return e((string) ($_SESSION['old'][$key] ?? ''));
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
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function recaptcha_configured(): bool
{
    return RECAPTCHA_SITE_KEY !== ''
        && RECAPTCHA_SECRET_KEY !== ''
    && RECAPTCHA_SITE_KEY !== 'GANTI_DENGAN_SITE_KEY'
    && RECAPTCHA_SECRET_KEY !== 'GANTI_DENGAN_SECRET_KEY';
}

function recaptcha_valid(?string $response, string $expectedAction): bool
{
    if (!recaptcha_configured()) {
        return false;
    }

    if (!$response) {
        return false;
    }

    $payload = http_build_query([
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $response,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $result = false;
    if (function_exists('curl_init')) {
        $curl = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ]);
        $result = curl_exec($curl);
        curl_close($curl);
    }

    if ($result === false) {
        $result = @file_get_contents(
            'https://www.google.com/recaptcha/api/siteverify',
            false,
            stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $payload,
                    'timeout' => 10,
                ],
            ])
        );
    }

    if ($result === false) {
        return false;
    }

    $json = json_decode($result, true);
    return is_array($json)
        && ($json['success'] ?? false) === true
        && ($json['action'] ?? '') === $expectedAction
        && (float) ($json['score'] ?? 0) >= 0.5;
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

    if (SMTP_PASSWORD === '' || SMTP_USERNAME === '' || MAIL_FROM_EMAIL === '') {
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
