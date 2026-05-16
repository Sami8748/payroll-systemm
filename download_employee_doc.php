<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_role(['hr']);

$docId = (int)($_GET['id'] ?? 0);
if ($docId <= 0) {
    http_response_code(400);
    echo 'Invalid request';
    exit;
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT d.stored_name, d.original_name
     FROM employee_documents d
     JOIN employees e ON e.id = d.employee_id
     WHERE d.id = :id AND e.position != "Manager"'
);
$stmt->execute(['id' => $docId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    echo 'Document not found';
    exit;
}

$storedName = basename((string)$doc['stored_name']);
$filePath = employee_docs_storage_dir() . DIRECTORY_SEPARATOR . $storedName;

if (!is_file($filePath)) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

$ext = strtolower(pathinfo($storedName, PATHINFO_EXTENSION));
$contentType = match ($ext) {
    'pdf'  => 'application/pdf',
    'jpg', 'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    default => 'application/octet-stream',
};

header('Content-Type: ' . $contentType);
header('Content-Disposition: inline; filename="' . addslashes((string)$doc['original_name']) . '"');
header('Content-Length: ' . (string)filesize($filePath));
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
