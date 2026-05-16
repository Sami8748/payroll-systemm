<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = current_user();
if ($user) {
    audit_log($user['id'], 'logout', 'User logged out');
}

logout();
header('Location: index.php');
exit;
