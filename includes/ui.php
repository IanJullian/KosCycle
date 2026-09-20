<?php
declare(strict_types=1);

if (!function_exists('page_url')) {
    function page_url(string $page, array $params = []): string
    {
        return APP_URL . '/?' . http_build_query(array_merge(['page' => $page], $params));
    }
}

if (!function_exists('dashboard_page_for_role')) {
    function dashboard_page_for_role(?string $role): string
    {
        return match ($role) {
            'admin' => 'admin-dashboard',
            'seller' => 'seller-dashboard',
            default => 'customer-dashboard',
        };
    }
}

if (!function_exists('dashboard_url_for_role')) {
    function dashboard_url_for_role(?string $role): string
    {
        return page_url(dashboard_page_for_role($role));
    }
}

if (!function_exists('dashboard_url')) {
    function dashboard_url(?string $role = null): string
    {
        $resolvedRole = $role;
        if ($resolvedRole === null && function_exists('current_user')) {
            $user = current_user();
            $resolvedRole = $user['role'] ?? null;
        }
        return dashboard_url_for_role($resolvedRole);
    }
}

if (!function_exists('current_role')) {
    function current_role(): ?string
    {
        $user = function_exists('current_user') ? current_user() : null;
        return $user['role'] ?? null;
    }
}

if (!function_exists('back_link')) {
    function back_link(string $page, string $label = 'Kembali'): string
    {
        return '<a class="back-link" href="' . e(page_url($page)) . '"><i class="bi bi-arrow-left me-2"></i>' . e($label) . '</a>';
    }
}
