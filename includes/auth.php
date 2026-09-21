<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ui.php';

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $loaded = false;
    static $user = null;

    if (!$loaded) {
        $loaded = true;
        $stmt = db()->prepare(
            'SELECT id, full_name, username, shop_name, whatsapp, email, role, status, created_at
             FROM users WHERE id = ? AND status = "active" LIMIT 1'
        );
        $stmt->execute([(int) $_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
        if (!$user) {
            unset($_SESSION['user_id']);
        }
    }

    return $user;
}

function require_guest(): void
{
    $user = current_user();
    if ($user) {
        redirect(dashboard_url((string) $user['role']));
    }
}

function require_auth(): void
{
    if (!current_user()) {
        flash('error', 'Silakan login terlebih dahulu untuk melanjutkan.');
        redirect(page_url('login'));
    }
}
