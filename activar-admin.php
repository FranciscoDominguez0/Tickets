<?php
/**
 * ACTIVAR USUARIO ADMIN
 * Activa el usuario admin y corrige la contraseña si es necesario
 */

require_once 'config.php';

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Activar Admin</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { color: #28a745; font-weight: bold; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; font-weight: bold; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0; }
        .info { color: #004085; padding: 15px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px; margin: 10px 0; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔧 Activar Usuario Admin</h1>";

if (!isset($mysqli)) {
    echo "<div class='error'>❌ No hay conexión a la base de datos</div>";
    echo "</body></html>";
    exit;
}

// Verificar usuario admin
$stmt = $mysqli->prepare("SELECT id, username, email, is_active, password FROM staff WHERE username = ?");
$username = 'admin';
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$staff = $result->fetch_assoc();

if (!$staff) {
    echo "<div class='error'>❌ Usuario 'admin' NO existe</div>";
    echo "<div class='info'>Ejecuta el script SQL para crear el usuario primero.</div>";
} else {
    echo "<div class='info'>";
    echo "<strong>Usuario encontrado:</strong> " . htmlspecialchars($staff['username']) . "<br>";
    echo "<strong>Email:</strong> " . htmlspecialchars($staff['email']) . "<br>";
    echo "<strong>Estado actual:</strong> " . ($staff['is_active'] ? '✅ Activo' : '❌ Inactivo') . "<br>";
    echo "</div>";
    
    // Activar usuario si no está activo
    if (!$staff['is_active']) {
        $mysqli->query("UPDATE staff SET is_active = 1 WHERE username = 'admin'");
        echo "<div class='success'>✅ Usuario activado exitosamente</div>";
    } else {
        echo "<div class='success'>✅ Usuario ya está activo</div>";
    }
    
    // Verificar y corregir contraseña
    $password_test = 'admin123';
    $verify_result = password_verify($password_test, $staff['password']);
    
    if (!$verify_result) {
        echo "<div class='error'>❌ La contraseña no coincide. Corrigiendo...</div>";
        $new_hash = password_hash($password_test, PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt = $mysqli->prepare("UPDATE staff SET password = ? WHERE username = 'admin'");
        $stmt->bind_param('s', $new_hash);
        $stmt->execute();
        echo "<div class='success'>✅ Contraseña actualizada</div>";
    } else {
        echo "<div class='success'>✅ Contraseña correcta</div>";
    }
    
    // Probar login
    echo "<h2>Probar Login</h2>";
    $result = Auth::loginStaff('admin', 'admin123');
    if ($result) {
        echo "<div class='success'>✅ Login exitoso</div>";
        echo "<div class='info'>";
        echo "<strong>Credenciales:</strong><br>";
        echo "Usuario: <code>admin</code><br>";
        echo "Contraseña: <code>admin123</code><br>";
        echo "</div>";
        echo "<p><a href='agente/index.php' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>Ir al Dashboard</a></p>";
    } else {
        echo "<div class='error'>❌ Login aún falla. Verifica los datos.</div>";
    }
}

echo "</body>
</html>";
?>
