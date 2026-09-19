<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = false;
    if ($user === false) {
        $stmt = db()->prepare('SELECT id, full_name, username, whatsapp, email, role FROM users WHERE id = ? AND status = "active"');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function require_guest(): void
{
    if (current_user()) {
        redirect(APP_URL . '/?page=account');
    }
}

function require_auth(): void
{
    if (!current_user()) {
        flash('error', 'Silakan login terlebih dahulu untuk melanjutkan.');
        redirect(APP_URL . '/?page=login');
    }
}
