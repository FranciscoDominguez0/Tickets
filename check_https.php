<?php
// Solo accesible desde localhost
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    // En producción, permitir acceso solo si eres admin — quitar después de revisar
}

$headers = [
    'HTTPS'                => $_SERVER['HTTPS'] ?? '(no está)',
    'SERVER_PORT'          => $_SERVER['SERVER_PORT'] ?? '(no está)',
    'HTTP_X_FORWARDED_PROTO' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '(no está) ← NPM debería enviar esto',
    'HTTP_X_FORWARDED_FOR'   => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '(no está)',
    'HTTP_X_REAL_IP'         => $_SERVER['HTTP_X_REAL_IP'] ?? '(no está)',
    'HTTP_HOST'              => $_SERVER['HTTP_HOST'] ?? '(no está)',
    'REQUEST_SCHEME'         => $_SERVER['REQUEST_SCHEME'] ?? '(no está)',
];

// Limpiar después de revisar
header('Content-Type: text/plain; charset=utf-8');
echo "=== Headers desde Nginx Proxy Manager ===\n\n";
foreach ($headers as $k => $v) {
    printf("%-32s %s\n", $k . ':', $v);
}
echo "\n";
echo '$isSecure = ';
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
echo $isSecure ? 'TRUE ✓' : 'FALSE ✗ (NPM no está enviando X-Forwarded-Proto)';
echo "\n\nELIMINA ESTE ARCHIVO DESPUÉS DE REVISAR.\n";
