<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

function deny_access(): never
{
    http_response_code(403);
    $pageTitle = 'Akses Ditolak';
    require __DIR__ . '/header.php';
    echo '<section class="auth-section"><div class="container"><div class="glass-card p-5 text-center"><span class="eyebrow">403</span><h1 class="mt-2">Akses ditolak.</h1><p class="text-muted">Kamu tidak memiliki izin untuk membuka halaman ini.</p><a class="btn btn-primary" href="' . e(dashboard_url(current_role())) . '">Kembali ke dashboard</a></div></div></section>';
    require __DIR__ . '/footer.php';
    exit;
}

function require_role(string $role): void
{
    require_auth();
    if (current_role() !== $role) {
        deny_access();
    }
}

function require_roles(array $roles): void
{
    require_auth();
    if (!in_array(current_role(), $roles, true)) {
        deny_access();
    }
}
