<?php
/**
 * Configuration File
 * Ticket Management System
 */

if (!function_exists('mysqli_connect')) {
    die('Configuration Error: PHP mysqli extension is required. Please check: ' . php_ini_loaded_file());
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '12345678');
define('DB_NAME', 'tickets_db');

// Application Configuration
define('APP_NAME', 'Sistema de Tickets');
define('TIMEZONE', 'America/Bogota');

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
define('ATTACHMENTS_DIR', __DIR__ . '/upload/uploads/attachments');

// Security Configuration
define('SECRET_KEY', 'cambia-esto-en-produccion-con-algo-largo-y-aleatorio-2025');
define('CSRF_TIMEOUT', 3600);
define('SESSION_LIFETIME', 86400);

// Initialization
date_default_timezone_set(TIMEZONE);

if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    
    session_set_cookie_params([
        'lifetime' => defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 86400,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database Connection
try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($mysqli->connect_error) {
        throw new Exception('Connection failed: ' . $mysqli->connect_error);
    }
    $mysqli->set_charset('utf8mb4');
} catch (Exception $e) {
    die('Database Error: ' . $e->getMessage());
}

// Autoloader
spl_autoload_register(function($class) {
    $file = __DIR__ . '/includes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Session Timeout Validation
if (isset($_SESSION['user_login_time'])) {
    if (time() - $_SESSION['user_login_time'] > SESSION_LIFETIME) {
        session_destroy();
        header('Location: login.php?msg=session_expired');
        exit;
    } else {
        $_SESSION['user_login_time'] = time();
    }
}
