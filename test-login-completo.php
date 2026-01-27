<?php
/**
 * TEST COMPLETO DE LOGIN
 * Verifica conexión, usuario y login paso a paso
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Test Login Completo</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; }
        .section { background: #f5f5f5; padding: 15px; margin: 10px 0; border-left: 4px solid #007bff; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
        pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #007bff; color: white; }
    </style>
</head>
<body>
    <h1>🔍 Test Completo de Login</h1>";

// 1. Verificar configuración
echo "<div class='section'>
        <h2>1. Configuración</h2>";

require_once 'config.php';

echo "<table>";
echo "<tr><th>Parámetro</th><th>Valor</th></tr>";
echo "<tr><td>DB_HOST</td><td>" . DB_HOST . "</td></tr>";
echo "<tr><td>DB_PORT</td><td><strong>" . DB_PORT . "</strong></td></tr>";
echo "<tr><td>DB_USER</td><td>" . DB_USER . "</td></tr>";
echo "<tr><td>DB_NAME</td><td>" . DB_NAME . "</td></tr>";
echo "</table>";

echo "</div>";

// 2. Verificar conexión
echo "<div class='section'>
        <h2>2. Conexión a Base de Datos</h2>";

if (isset($mysqli)) {
    echo "<p class='success'>✅ Conexión establecida</p>";
    
    // Probar query simple
    $result = $mysqli->query("SELECT 1");
    if ($result) {
        echo "<p class='success'>✅ Query de prueba exitosa</p>";
    } else {
        echo "<p class='error'>❌ Error en query: " . $mysqli->error . "</p>";
    }
    
    // Verificar base de datos
    $result = $mysqli->query("SELECT DATABASE()");
    if ($result) {
        $row = $result->fetch_row();
        echo "<p><strong>Base de datos conectada:</strong> " . $row[0] . "</p>";
    }
} else {
    echo "<p class='error'>❌ No hay conexión a la base de datos</p>";
    echo "</body></html>";
    exit;
}

echo "</div>";

// 3. Verificar usuario admin
echo "<div class='section'>
        <h2>3. Verificar Usuario 'admin'</h2>";

$stmt = $mysqli->prepare("SELECT id, username, email, firstname, lastname, password, is_active, role, dept_id FROM staff WHERE username = ?");
$username = 'admin';
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$staff = $result->fetch_assoc();

if ($staff) {
    echo "<p class='success'>✅ Usuario encontrado</p>";
    echo "<table>";
    echo "<tr><th>Campo</th><th>Valor</th></tr>";
    echo "<tr><td>ID</td><td>" . htmlspecialchars($staff['id']) . "</td></tr>";
    echo "<tr><td>Username</td><td>" . htmlspecialchars($staff['username']) . "</td></tr>";
    echo "<tr><td>Email</td><td>" . htmlspecialchars($staff['email']) . "</td></tr>";
    echo "<tr><td>Nombre</td><td>" . htmlspecialchars($staff['firstname'] . ' ' . $staff['lastname']) . "</td></tr>";
    echo "<tr><td>Rol</td><td>" . htmlspecialchars($staff['role']) . "</td></tr>";
    echo "<tr><td>Dept ID</td><td>" . htmlspecialchars($staff['dept_id']) . "</td></tr>";
    echo "<tr><td>Activo</td><td>" . ($staff['is_active'] ? '✅ Sí (1)' : '❌ No (0)') . "</td></tr>";
    echo "<tr><td>Hash Password</td><td><code>" . htmlspecialchars(substr($staff['password'], 0, 60)) . "...</code></td></tr>";
    echo "</table>";
    
    if (!$staff['is_active']) {
        echo "<p class='error'>⚠️ PROBLEMA: El usuario NO está activo (is_active = 0)</p>";
        echo "<p>Ejecutando: UPDATE staff SET is_active = 1 WHERE username = 'admin'</p>";
        $mysqli->query("UPDATE staff SET is_active = 1 WHERE username = 'admin'");
        echo "<p class='success'>✅ Usuario activado</p>";
        
        // Volver a consultar
        $stmt = $mysqli->prepare("SELECT is_active FROM staff WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $check = $result->fetch_assoc();
        echo "<p>Verificación: is_active = " . $check['is_active'] . "</p>";
    }
} else {
    echo "<p class='error'>❌ Usuario 'admin' NO encontrado</p>";
    echo "<p class='warning'>💡 Necesitas crear el usuario ejecutando crear_usuarios_prueba.sql</p>";
}

echo "</div>";

// 4. Verificar contraseña
echo "<div class='section'>
        <h2>4. Verificar Contraseña</h2>";

if (isset($staff) && $staff) {
    $password_test = 'admin123';
    $hash_from_db = $staff['password'];
    
    echo "<p><strong>Contraseña a verificar:</strong> <code>{$password_test}</code></p>";
    echo "<p><strong>Hash en BD:</strong> <code>" . htmlspecialchars($hash_from_db) . "</code></p>";
    
    $verify_result = password_verify($password_test, $hash_from_db);
    
    if ($verify_result) {
        echo "<p class='success'>✅ La contraseña 'admin123' coincide con el hash</p>";
    } else {
        echo "<p class='error'>❌ La contraseña 'admin123' NO coincide con el hash</p>";
        echo "<p class='warning'>Generando nuevo hash...</p>";
        
        $new_hash = password_hash($password_test, PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt = $mysqli->prepare("UPDATE staff SET password = ? WHERE username = 'admin'");
        $stmt->bind_param('s', $new_hash);
        $stmt->execute();
        
        echo "<p class='success'>✅ Hash actualizado</p>";
        echo "<p><strong>Nuevo hash:</strong> <code>" . htmlspecialchars($new_hash) . "</code></p>";
        
        // Verificar nuevamente
        $verify_result = password_verify($password_test, $new_hash);
        if ($verify_result) {
            echo "<p class='success'>✅ Verificación con nuevo hash exitosa</p>";
        }
    }
} else {
    echo "<p class='error'>❌ No se puede verificar: usuario no encontrado</p>";
}

echo "</div>";

// 5. Probar loginStaff directamente
echo "<div class='section'>
        <h2>5. Probar Función loginStaff()</h2>";

if (isset($mysqli)) {
    $username_test = 'admin';
    $password_test = 'admin123';
    
    echo "<p><strong>Probando login con:</strong></p>";
    echo "<ul>";
    echo "<li>Usuario: <code>{$username_test}</code></li>";
    echo "<li>Contraseña: <code>{$password_test}</code></li>";
    echo "</ul>";
    
    // Limpiar sesión antes de probar
    session_unset();
    
    try {
        $result = Auth::loginStaff($username_test, $password_test);
        
        if ($result) {
            echo "<p class='success'>✅ Login exitoso</p>";
            echo "<pre>";
            print_r($result);
            echo "</pre>";
            
            // Verificar sesión
            echo "<h3>Datos en sesión:</h3>";
            echo "<pre>";
            print_r($_SESSION);
            echo "</pre>";
            
            if (isset($_SESSION['staff_id'])) {
                echo "<p class='success'>✅ Sesión creada correctamente</p>";
                echo "<p><strong>staff_id:</strong> " . $_SESSION['staff_id'] . "</p>";
                echo "<p><strong>user_type:</strong> " . $_SESSION['user_type'] . "</p>";
                echo "<p><strong>staff_name:</strong> " . $_SESSION['staff_name'] . "</p>";
                echo "<p><a href='agente/index.php' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>Ir al Dashboard</a></p>";
            } else {
                echo "<p class='error'>❌ La sesión NO se creó correctamente</p>";
            }
        } else {
            echo "<p class='error'>❌ Login fallido</p>";
            
            // Depuración detallada
            echo "<h3>Depuración paso a paso:</h3>";
            
            // Paso 1: Buscar usuario
            $stmt = $mysqli->prepare('SELECT id, username, password, is_active FROM staff WHERE username = ?');
            $stmt->bind_param('s', $username_test);
            $stmt->execute();
            $result = $stmt->get_result();
            $debug_staff = $result->fetch_assoc();
            
            if (!$debug_staff) {
                echo "<p class='error'>❌ Paso 1: Usuario no encontrado</p>";
            } else {
                echo "<p class='success'>✅ Paso 1: Usuario encontrado</p>";
                echo "<p>is_active = " . $debug_staff['is_active'] . "</p>";
                
                if (!$debug_staff['is_active']) {
                    echo "<p class='error'>❌ Paso 2: Usuario NO está activo</p>";
                } else {
                    echo "<p class='success'>✅ Paso 2: Usuario está activo</p>";
                    
                    $verify_debug = password_verify($password_test, $debug_staff['password']);
                    if (!$verify_debug) {
                        echo "<p class='error'>❌ Paso 3: Contraseña NO coincide</p>";
                    } else {
                        echo "<p class='success'>✅ Paso 3: Contraseña coincide</p>";
                        echo "<p class='error'>⚠️ Algo más está fallando en loginStaff()</p>";
                    }
                }
            }
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error al ejecutar loginStaff(): " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
} else {
    echo "<p class='error'>❌ No se puede probar: no hay conexión a la base de datos</p>";
}

echo "</div>";

// 6. Verificar puerto de conexión
echo "<div class='section'>
        <h2>6. Verificar Puerto de Conexión</h2>";

if (isset($mysqli)) {
    $host_info = $mysqli->host_info;
    echo "<p><strong>Información de conexión:</strong> " . htmlspecialchars($host_info) . "</p>";
    
    // Intentar conexión directa con el puerto
    echo "<p>Probando conexión directa al puerto " . DB_PORT . "...</p>";
    $test_conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($test_conn->connect_error) {
        echo "<p class='error'>❌ Error de conexión: " . $test_conn->connect_error . "</p>";
    } else {
        echo "<p class='success'>✅ Conexión directa exitosa al puerto " . DB_PORT . "</p>";
        $test_conn->close();
    }
}

echo "</div>";

// 7. SQL para corregir
echo "<div class='section'>
        <h2>7. SQL para Corregir</h2>";

echo "<p>Si el usuario no está activo o la contraseña es incorrecta, ejecuta:</p>";
echo "<pre>USE tickets_db;

-- Activar usuario
UPDATE staff SET is_active = 1 WHERE username = 'admin';

-- Verificar
SELECT id, username, email, is_active FROM staff WHERE username = 'admin';</pre>";

echo "</div>";

echo "</body>
</html>";
?>
