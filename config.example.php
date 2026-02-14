<?php
/**
 * CONFIGURACIÓN DEL SISTEMA DE TICKETS (EJEMPLO)
 *
 * Copia este archivo a config.php y ajusta valores.
 * NO subas config.php al repositorio.
 */

if (!extension_loaded('mysqli')) {
    die('Error: La extensión mysqli de PHP no está habilitada.');
}

// ============================================================================
// BASE DE DATOS
// ============================================================================
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tickets_db');

// ============================================================================
// APLICACIÓN
// ============================================================================
define('APP_NAME', 'Sistema de Tickets');

$__appUrl = 'http://localhost/sistema-tickets';
if (!empty($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)$_SERVER['HTTP_HOST'];

    $docRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
    $docRootReal = $docRoot !== '' ? realpath($docRoot) : false;
    $projectReal = realpath(__DIR__);

    if ($docRootReal && $projectReal) {
        $docRootReal = str_replace('\\', '/', $docRootReal);
        $projectReal = str_replace('\\', '/', $projectReal);

        if (stripos($projectReal, $docRootReal) === 0) {
            $rel = substr($projectReal, strlen($docRootReal));
            $rel = '/' . ltrim((string)$rel, '/');
            $__appUrl = $scheme . '://' . $host . rtrim($rel, '/');
        } else {
            $__appUrl = $scheme . '://' . $host;
        }
    } else {
        $__appUrl = $scheme . '://' . $host;
    }
}

define('APP_URL', $__appUrl);
define('TIMEZONE', 'America/Mexico_City');

// ============================================================================
// CORREO
// ============================================================================
define('MAIL_FROM', 'no-reply@tudominio.com');
define('MAIL_FROM_NAME', APP_NAME);
define('ADMIN_NOTIFY_EMAIL', 'admin@tudominio.com');
define('SEND_CLIENT_UPDATE_EMAIL', false);

define('SMTP_HOST', 'smtp.tudominio.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'usuario-smtp');
define('SMTP_PASS', '');
define('SMTP_SECURE', 'ssl');

// ============================================================================
// SEGURIDAD
// ============================================================================
define('SECRET_KEY', 'CAMBIA-ESTO-POR-ALGO-LARGO-Y-ALEATORIO');
define('CSRF_TIMEOUT', 3600);
define('SESSION_LIFETIME', 86400);

// ============================================================================
// INICIALIZAR
// ============================================================================
date_default_timezone_set(TIMEZONE);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($mysqli->connect_error) {
        throw new Exception('Error de conexión: ' . $mysqli->connect_error);
    }
    $mysqli->set_charset('utf8mb4');
} catch (Exception $e) {
    die('Error de base de datos: ' . $e->getMessage());
}

spl_autoload_register(function($class) {
    $file = __DIR__ . '/includes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
