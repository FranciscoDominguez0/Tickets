<?php
require_once '../config.php';
require_once '../includes/helpers.php';

requireLogin('cliente');

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!validateCSRF()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing file']);
    exit;
}

$f = $_FILES['file'];
if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Upload error']);
    exit;
}

$maxSize = 5 * 1024 * 1024; // 5MB
$size = (int)($f['size'] ?? 0);
if ($size <= 0 || $size > $maxSize) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'File too large']);
    exit;
}

$orig = (string)($f['name'] ?? 'image');
$ext = strtolower((string)(pathinfo($orig, PATHINFO_EXTENSION) ?: ''));
$allowed = ['jpg','jpeg','png','gif','webp'];
if ($ext === '' || !in_array($ext, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid file type']);
    exit;
}

$mime = (string)($f['type'] ?? '');
if (function_exists('finfo_open') && !empty($f['tmp_name'])) {
    $fi = @finfo_open(FILEINFO_MIME_TYPE);
    if ($fi) {
        $detected = @finfo_file($fi, $f['tmp_name']);
        @finfo_close($fi);
        if (is_string($detected) && $detected !== '') $mime = $detected;
    }
}

$allowedMime = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
];
if (!empty($mime) && isset($allowedMime[$ext]) && stripos($mime, 'image/') !== 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid MIME type']);
    exit;
}

$dir = __DIR__ . '/uploads/inline-images';
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}

$base = bin2hex(random_bytes(8)) . '_' . time();
$filename = $base . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
$path = $dir . '/' . $filename;

if (!move_uploaded_file($f['tmp_name'], $path)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Unable to save file']);
    exit;
}

$url = rtrim((string)APP_URL, '/') . '/upload/uploads/inline-images/' . rawurlencode($filename);

echo json_encode(['ok' => true, 'url' => $url], JSON_UNESCAPED_UNICODE);
