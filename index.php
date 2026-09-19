<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$action = $_GET['action'] ?? null;
if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    redirect(APP_URL . '/');
}

$page = $_GET['page'] ?? 'home';
$allowedPages = ['home', 'login', 'register', 'forgot-password', 'verify-otp', 'reset-password', 'account'];
if (!in_array($page, $allowedPages, true)) {
    $page = 'home';
}
require __DIR__ . '/pages/' . $page . '.php';
