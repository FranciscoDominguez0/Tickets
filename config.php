<?php
/**
 * Configuration File — Sistema de Tickets
 */

if (!function_exists('mysqli_connect')) {
    die('Error: PHP mysqli extension is required.');
}

// ── Base de datos
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '12345678');
define('DB_NAME', 'tickets_db');

// ── Aplicación
define('APP_NAME',    'Sistema de Tickets');
define('TIMEZONE',    'America/Bogota');

// APP_DEBUG: true = stack trace en pantalla (solo desarrollo) | false = página amigable + log
define('APP_DEBUG', false);

// ── Notificaciones de error: email del admin para alertas de errores críticos
define('ERROR_NOTIFY_EMAIL', 'dominguezf225@gmail.com');

// ── SMTP de emergencia (usado cuando la DB está caída)
// Gmail: crea una contraseña de aplicación en https://myaccount.google.com/apppasswords
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_SECURE',    'tls');
define('SMTP_USER',      'dominguezf225@gmail.com');
define('SMTP_PASS',      '');          // ← contraseña de aplicación de Gmail
define('MAIL_FROM',      'dominguezf225@gmail.com');
define('MAIL_FROM_NAME', APP_NAME);

// ── Seguridad
define('SECRET_KEY',      'cambia-esto-en-produccion-con-algo-largo-y-aleatorio-2025');
define('CSRF_TIMEOUT',    3600);
define('SESSION_LIFETIME', 86400);

// ── Detección HTTPS (directo o detrás de Nginx Proxy Manager)
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

// ── APP_URL (calculado dinámicamente desde DOCUMENT_ROOT)
$__appUrl = 'http://localhost/sistema-tickets';
if (!empty($_SERVER['HTTP_HOST'])) {
    $scheme      = $isSecure ? 'https' : 'http';
    $host        = (string)$_SERVER['HTTP_HOST'];
    $docRootReal = ($r = (string)($_SERVER['DOCUMENT_ROOT'] ?? '')) !== '' ? realpath($r) : false;
    $projectReal = realpath(__DIR__);

    if ($docRootReal && $projectReal) {
        $docRootReal = str_replace('\\', '/', $docRootReal);
        $projectReal = str_replace('\\', '/', $projectReal);
        if (stripos($projectReal, $docRootReal) === 0) {
            $rel      = '/' . ltrim(substr($projectReal, strlen($docRootReal)), '/');
            $__appUrl = $scheme . '://' . $host . rtrim($rel, '/');
        } else {
            $__appUrl = $scheme . '://' . $host;
        }
    } else {
        $__appUrl = $scheme . '://' . $host;
    }
}
define('APP_URL',         $__appUrl);
define('ATTACHMENTS_DIR', __DIR__ . '/upload/uploads/attachments');

date_default_timezone_set(TIMEZONE);

// ── Error Handler (antes de sesión y DB para capturar todo)
require_once __DIR__ . '/includes/Logger.php';
require_once __DIR__ . '/includes/ErrorHandler.php';
ErrorHandler::register();

// Bloquear PATH_INFO (/script.php/ruta) → devuelve 404 amigable
if (!empty($_SERVER['PATH_INFO']) && php_sapi_name() !== 'cli') {
    $code = ErrorHandler::generateErrorCode();
    ErrorHandler::logError($code, [
        'type'    => 'HTTP404',
        'errno'   => 404,
        'message' => 'PATH_INFO no permitido: ' . $_SERVER['PATH_INFO'],
        'file'    => $_SERVER['SCRIPT_NAME'] ?? '',
        'line'    => 0,
    ]);
    ErrorHandler::renderErrorPage(404, $code);
}

// ── Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Base de datos
try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($mysqli->connect_error) {
        throw new RuntimeException('Database connection failed: ' . $mysqli->connect_error, 503);
    }
    $mysqli->set_charset('utf8mb4');
} catch (Exception $e) {
    ErrorHandler::handleException($e);
}

// ── Autoloader (includes/)
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/includes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ── Validar timeout de sesión
if (isset($_SESSION['user_login_time'])) {
    if (time() - $_SESSION['user_login_time'] > SESSION_LIFETIME) {
        session_destroy();
        header('Location: login.php?msg=session_expired');
        exit;
    } else {
        $_SESSION['user_login_time'] = time();
    }
}
