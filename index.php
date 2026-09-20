<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$action = $_GET['action'] ?? null;
if ($action === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
        http_response_code(405);
        exit('Metode logout tidak valid.');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
    redirect(APP_URL . '/');
}

$page = $_GET['page'] ?? 'home';
$allowedPages = ['home','login','register','forgot-password','verify-otp','reset-password','account','profile','customer-dashboard','marketplace','product-detail','cart','checkout','orders','review','seller-dashboard','seller-products','seller-product-form','seller-orders','seller-sales','admin-dashboard','admin-users','admin-user-form','admin-user-edit','admin-categories','admin-products','admin-product-form','admin-product-edit','admin-orders','admin-reviews','admin-reports'];
if (!in_array($page, $allowedPages, true)) $page = 'home';
require __DIR__ . '/pages/' . $page . '.php';
