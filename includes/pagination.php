<?php
declare(strict_types=1);

function pager_meta(int $total, int $page, int $perPage): array
{
    $perPage = max(1, min($perPage, 100));
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    return [
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => $pages,
        'offset' => ($page - 1) * $perPage,
    ];
}

function pager_url(int $page, array $extra = []): string
{
    $query = $_GET;
    $query['p'] = max(1, $page);
    foreach ($extra as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    return APP_URL . '/?' . http_build_query($query);
}

function pager_render(array $meta): string
{
    $pages = (int) ($meta['pages'] ?? 1);
    $page = (int) ($meta['page'] ?? 1);
    if ($pages <= 1) return '';

    $start = max(1, $page - 2);
    $end = min($pages, $page + 2);

    $html = '<nav class="kc-pagination" aria-label="Navigasi halaman">';
    $html .= '<a class="kc-page-link ' . ($page <= 1 ? 'is-disabled' : '') . '" href="' . e(pager_url($page - 1)) . '"><i class="bi bi-chevron-left"></i></a>';

    if ($start > 1) {
        $html .= '<a class="kc-page-link" href="' . e(pager_url(1)) . '">1</a>';
        if ($start > 2) $html .= '<span class="kc-page-gap">…</span>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $html .= '<a class="kc-page-link ' . ($i === $page ? 'is-active' : '') . '" href="' . e(pager_url($i)) . '">' . $i . '</a>';
    }

    if ($end < $pages) {
        if ($end < $pages - 1) $html .= '<span class="kc-page-gap">…</span>';
        $html .= '<a class="kc-page-link" href="' . e(pager_url($pages)) . '">' . $pages . '</a>';
    }

    $html .= '<a class="kc-page-link ' . ($page >= $pages ? 'is-disabled' : '') . '" href="' . e(pager_url($page + 1)) . '"><i class="bi bi-chevron-right"></i></a>';
    $html .= '</nav>';
    return $html;
}
