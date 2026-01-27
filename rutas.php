<?php
/**
 * RUTAS DEL SISTEMA
 * Muestra todas las rutas disponibles y verifica el estado del servidor
 */

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Rutas del Sistema de Tickets</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .section {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #007bff;
        }
        .route {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .route a {
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
        }
        .route a:hover {
            text-decoration: underline;
        }
        .route code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .status.ok {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔗 Rutas del Sistema de Tickets</h1>";

// Verificar extensión mysqli
$mysqli_status = extension_loaded('mysqli') ? 'ok' : 'error';
$mysqli_text = extension_loaded('mysqli') ? '✅ mysqli está habilitada' : '❌ mysqli NO está habilitada';

echo "<div class='section'>
            <h2>Estado del Sistema</h2>
            <div class='status {$mysqli_status}'>
                {$mysqli_text}
            </div>";

if (!extension_loaded('mysqli')) {
    echo "<div class='status error'>
                <strong>⚠️ Acción requerida:</strong><br>
                1. Abre: <code>C:\\php\\php.ini</code><br>
                2. Busca: <code>;extension=mysqli</code><br>
                3. Quita el punto y coma: <code>extension=mysqli</code><br>
                4. Guarda el archivo<br>
                5. <strong>REINICIA tu servidor web</strong> (Apache/XAMPP/WAMP)
            </div>";
}

echo "</div>";

// Obtener la URL base
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $protocol . '://' . $host . '/sistema-tickets';

echo "<div class='section'>
            <h2>📍 Rutas de Acceso</h2>
            
            <div class='route'>
                <strong>🏠 Página Principal:</strong><br>
                <a href='{$base_url}/index.php' target='_blank'>{$base_url}/index.php</a><br>
                <span class='info'>Redirige al login de cliente</span>
            </div>
            
            <div class='route'>
                <strong>👤 Login Cliente:</strong><br>
                <a href='{$base_url}/cliente/login.php' target='_blank'>{$base_url}/cliente/login.php</a><br>
                <span class='info'>Para usuarios/clientes</span>
            </div>
            
            <div class='route'>
                <strong>👤 Login Cliente (Alternativo):</strong><br>
                <a href='{$base_url}/upload/login.php' target='_blank'>{$base_url}/upload/login.php</a><br>
                <span class='info'>Ruta alternativa de login</span>
            </div>
            
            <div class='route'>
                <strong>👨‍💼 Login Agente:</strong><br>
                <a href='{$base_url}/agente/login.php' target='_blank'>{$base_url}/agente/login.php</a><br>
                <span class='info'>Para agentes/staff del sistema</span>
            </div>
            
            <div class='route'>
                <strong>🔍 Verificar PHP:</strong><br>
                <a href='{$base_url}/verificar-php.php' target='_blank'>{$base_url}/verificar-php.php</a><br>
                <span class='info'>Verifica extensiones y configuración de PHP</span>
            </div>
        </div>";

echo "<div class='section'>
            <h2>📋 Información del Servidor</h2>
            <div class='route'>
                <strong>URL Base:</strong> <code>{$base_url}</code><br>
                <strong>Directorio:</strong> <code>" . __DIR__ . "</code><br>
                <strong>PHP Version:</strong> <code>" . PHP_VERSION . "</code><br>
                <strong>Servidor:</strong> <code>" . ($_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido') . "</code>
            </div>
        </div>";

echo "<div class='section'>
            <h2>⚙️ Cómo Iniciar el Servidor</h2>
            <div class='route'>
                <strong>Si usas XAMPP:</strong><br>
                1. Abre el Panel de Control de XAMPP<br>
                2. Haz clic en 'Start' junto a Apache<br>
                3. Asegúrate de que MySQL también esté iniciado<br>
                4. Abre tu navegador en: <a href='{$base_url}/index.php'>{$base_url}/index.php</a>
            </div>
            
            <div class='route'>
                <strong>Si usas WAMP:</strong><br>
                1. Haz clic derecho en el icono de WAMP<br>
                2. Selecciona 'Iniciar todos los servicios'<br>
                3. Abre tu navegador en: <a href='{$base_url}/index.php'>{$base_url}/index.php</a>
            </div>
            
            <div class='route'>
                <strong>Si usas otro servidor:</strong><br>
                Asegúrate de que Apache/Nginx esté corriendo y apuntando a:<br>
                <code>" . __DIR__ . "</code>
            </div>
        </div>";

echo "</div>
</body>
</html>";
?>
