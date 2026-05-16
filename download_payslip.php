<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') {
    http_response_code(400);
    echo t('download_link_expired');
    exit;
}

$file = get_payslip_file_by_token($token);
if (!$file) {
    http_response_code(404);
    echo t('download_link_expired');
    exit;
}

if (strtotime((string)$file['expires_at']) < time()) {
    http_response_code(410);
    echo t('download_link_expired');
    exit;
}

$path = (string)$file['file_path'];
if (!is_file($path)) {
    http_response_code(404);
    echo t('download_link_expired');
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename((string)$file['file_name']) . '"');
header('Content-Length: ' . (string)filesize($path));

readfile($path);
exit;
