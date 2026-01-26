<?php
/**
 * LOGIN AGENTE
 * Formulario de autenticación para agentes/staff
 * 
 * SQL: SELECT id, username, email, firstname, lastname, password FROM staff WHERE username = ? AND is_active = 1
 */

require_once '../config.php';

// Si ya está logueado, redirigir
if (isset($_SESSION['staff_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_POST) {
        // Validar CSRF
        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            $error = 'Token de seguridad inválido';
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                $error = 'Usuario y contraseña son requeridos';
            } else {
                $staff = Auth::loginStaff($username, $password);
                if ($staff) {
                    $_SESSION['user_login_time'] = time();
                    $success = 'Login exitoso, redirigiendo...';
                    echo '<script>
                        setTimeout(function() {
                            window.location.href = "index.php";
                        }, 1500);
                    </script>';
                } else {
                    $error = 'Usuario o contraseña incorrectos';
                }
            }
        }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Agente - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../publico/css/login.css">
</head>
<body>
    <div class="login-container">
        <!-- HEADER -->
        <div class="login-header">
            <div class="login-icon"></div>
            <h1><?php echo APP_NAME; ?></h1>
            <p>Panel de Agentes</p>
        </div>

        <!-- TABS -->
        <div class="login-tabs">
            <button class="login-tab" onclick="window.location.href='../cliente/login.php'">Cliente</button>
            <button class="login-tab active">Agente</button>
        </div>

        <!-- FORMULARIO -->
        <form method="post" class="login-form">
            <!-- Alertas -->
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Usuario -->
            <div class="form-group">
                <label for="username">Usuario</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    placeholder="tu_usuario"
                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                    required
                >
                <small>Usa: <strong>admin</strong> (usuario de prueba)</small>
            </div>

            <!-- Contraseña -->
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="••••••••"
                    required
                >
                <small>Usa: <strong>admin123</strong> (contraseña de prueba)</small>
            </div>

            <!-- Recordar -->
            <div class="form-remember">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Recuérdame en este dispositivo</label>
            </div>

            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <!-- Botón Login -->
            <button type="submit" class="btn-login">Iniciar Sesión</button>
        </form>

        <!-- FOOTER -->
        <div class="login-footer">
            ¿Problemas de acceso? Contacta a tu administrador
        </div>
    </div>

    <script>
        // Prevenir submit duplicado
        document.querySelector('form').addEventListener('submit', function(e) {
            const btn = this.querySelector('.btn-login');
            if (btn.disabled) {
                e.preventDefault();
                return false;
            }
            btn.disabled = true;
            btn.classList.add('loading');
            btn.textContent = 'Verificando...';
        });
    </script>
</body>
</html>
