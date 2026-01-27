<?php
/**
 * CONFIGURACIÓN DEL SISTEMA DE TICKETS
 */

// Verificar que la extensión mysqli esté habilitada
if (!extension_loaded('mysqli')) {
    die('❌ Error: La extensión mysqli de PHP no está habilitada.<br><br>' .
        'Para habilitarla en Windows:<br>' .
        '1. Abre el archivo php.ini<br>' .
        '2. Busca la línea: ;extension=mysqli<br>' .
        '3. Quita el punto y coma (;) al inicio: extension=mysqli<br>' .
        '4. Guarda el archivo y reinicia tu servidor web (Apache/Nginx)<br><br>' .
        'Ubicación común del php.ini: ' . php_ini_loaded_file());
}

// ============================================================================
// BASE DE DATOS
// ============================================================================
define('DB_HOST', 'localhost');
define('DB_PORT', '3309');
define('DB_USER', 'root');
define('DB_PASS', '12345678');
define('DB_NAME', 'tickets_db');

// ============================================================================
// APLICACIÓN
// ============================================================================
define('APP_NAME', 'Sistema de Tickets');
define('APP_URL', 'http://localhost/sistema-tickets');
define('TIMEZONE', 'America/Mexico_City');

// ============================================================================
// SEGURIDAD
// ============================================================================
define('SECRET_KEY', 'cambia-esto-en-produccion-con-algo-largo-y-aleatorio-2025');
define('CSRF_TIMEOUT', 3600); // 1 hora
define('SESSION_LIFETIME', 86400); // 24 horas

// ============================================================================
// INICIALIZAR
// ============================================================================
date_default_timezone_set(TIMEZONE);

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF token en sesión
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Conexión a base de datos
try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($mysqli->connect_error) {
        throw new Exception('Error de conexión: ' . $mysqli->connect_error);
    }
    $mysqli->set_charset('utf8mb4');
} catch (Exception $e) {
    die('❌ Error de base de datos: ' . $e->getMessage());
}

// Autoload de clases
spl_autoload_register(function($class) {
    $file = __DIR__ . '/includes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Validar sesión expirada
if (isset($_SESSION['user_login_time'])) {
    if (time() - $_SESSION['user_login_time'] > SESSION_LIFETIME) {
        session_destroy();
        header('Location: login.php?msg=session_expired');
        exit;
    } else {
        $_SESSION['user_login_time'] = time();
    }
}
?>
