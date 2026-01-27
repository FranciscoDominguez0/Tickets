<?php
/**
 * VERIFICAR Y CORREGIR USUARIO ADMIN
 */

require_once 'config.php';

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Verificar Admin</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        .success { color: #28a745; font-weight: bold; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; font-weight: bold; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0; }
        .info { color: #004085; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px; margin: 10px 0; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
        pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Verificar y Corregir Usuario Admin</h1>";

// Verificar conexión
if (!isset($mysqli)) {
    echo "<div class='error'>❌ No hay conexión a la base de datos</div>";
    echo "</body></html>";
    exit;
}

// Verificar si existe el usuario admin
$stmt = $mysqli->prepare("SELECT id, username, email, firstname, lastname, password, is_active, role FROM staff WHERE username = ?");
$username = 'admin';
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$staff = $result->fetch_assoc();

if (!$staff) {
    echo "<div class='error'>❌ Usuario 'admin' NO existe en la base de datos</div>";
    echo "<div class='info'>Creando usuario admin...</div>";
    
    // Crear departamento si no existe
    $mysqli->query("INSERT INTO departments (id, name, description, is_active) VALUES (1, 'Soporte Técnico', 'Departamento de soporte', 1) ON DUPLICATE KEY UPDATE name=name");
    
    // Crear usuario admin
    $password_hash = '$2y$10$YIjlrJyeatqIz.Yy5C6He.BBVCoQmkdUVewO0E8/LewKJvLF6NO2'; // admin123
    $stmt = $mysqli->prepare("INSERT INTO staff (username, password, email, firstname, lastname, dept_id, role, is_active, created) VALUES (?, ?, ?, ?, ?, 1, 'admin', 1, NOW())");
    $stmt->bind_param('sssss', $username, $password_hash, $email, $firstname, $lastname);
    $email = 'admin@company.com';
    $firstname = 'Admin';
    $lastname = 'System';
    $stmt->execute();
    
    echo "<div class='success'>✅ Usuario admin creado exitosamente</div>";
    
    // Volver a consultar
    $stmt = $mysqli->prepare("SELECT id, username, email, firstname, lastname, password, is_active, role FROM staff WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $staff = $result->fetch_assoc();
}

if ($staff) {
    echo "<div class='success'>✅ Usuario encontrado</div>";
    echo "<div class='info'>";
    echo "<strong>ID:</strong> " . htmlspecialchars($staff['id']) . "<br>";
    echo "<strong>Username:</strong> " . htmlspecialchars($staff['username']) . "<br>";
    echo "<strong>Email:</strong> " . htmlspecialchars($staff['email']) . "<br>";
    echo "<strong>Nombre:</strong> " . htmlspecialchars($staff['firstname'] . ' ' . $staff['lastname']) . "<br>";
    echo "<strong>Rol:</strong> " . htmlspecialchars($staff['role']) . "<br>";
    echo "<strong>Activo:</strong> " . ($staff['is_active'] ? '✅ Sí' : '❌ No') . "<br>";
    echo "</div>";
    
    // Verificar si está activo
    if (!$staff['is_active']) {
        echo "<div class='error'>⚠️ El usuario NO está activo. Activando...</div>";
        $mysqli->query("UPDATE staff SET is_active = 1 WHERE username = 'admin'");
        echo "<div class='success'>✅ Usuario activado</div>";
    }
    
    // Verificar contraseña
    $password_test = 'admin123';
    $hash_from_db = $staff['password'];
    $verify_result = password_verify($password_test, $hash_from_db);
    
    if ($verify_result) {
        echo "<div class='success'>✅ La contraseña 'admin123' es correcta</div>";
    } else {
        echo "<div class='error'>❌ La contraseña 'admin123' NO coincide con el hash</div>";
        echo "<div class='info'>Corrigiendo hash de contraseña...</div>";
        
        // Generar nuevo hash correcto
        $new_hash = password_hash($password_test, PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt = $mysqli->prepare("UPDATE staff SET password = ? WHERE username = 'admin'");
        $stmt->bind_param('s', $new_hash);
        $stmt->execute();
        
        echo "<div class='success'>✅ Hash de contraseña actualizado</div>";
        echo "<div class='info'><strong>Nuevo hash:</strong> <code>" . htmlspecialchars($new_hash) . "</code></div>";
    }
    
    // Probar login
    echo "<h2>Probar Login</h2>";
    try {
        $result = Auth::loginStaff('admin', 'admin123');
        if ($result) {
            echo "<div class='success'>✅ Login exitoso con las credenciales:</div>";
            echo "<div class='info'>";
            echo "<strong>Usuario:</strong> admin<br>";
            echo "<strong>Contraseña:</strong> admin123<br>";
            echo "</div>";
            echo "<div class='success'>✅ Sesión creada correctamente</div>";
            echo "<p><a href='agente/index.php' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>Ir al Dashboard</a></p>";
        } else {
            echo "<div class='error'>❌ Login aún falla. Verifica que el usuario esté activo.</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

echo "</body>
</html>";
?>
