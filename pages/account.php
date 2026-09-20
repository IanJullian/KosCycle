<?php
require_once __DIR__ . '/../includes/auth.php';
require_auth();
$user = current_user();
redirect(dashboard_url((string) $user['role']));
