<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_role(['accounting']);

$file = basename((string)($_GET['file'] ?? ''));
if ($file === '' || preg_match('/\.\./', $file)) {
    http_response_code(400);
    echo 'Invalid file';
    exit;
}

$path = reports_storage_dir() . DIRECTORY_SEPARATOR . $file;
if (!is_file($path)) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if ($ext === 'txt') {
    header('Content-Type: text/plain; charset=UTF-8');
} else {
    header('Content-Type: text/csv; charset=UTF-8');
}
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . (string)filesize($path));
readfile($path);
exit;
