<?php
/**
 * LOGIN CLIENTE
 * Formulario de autenticación para usuarios
 * 
 * SQL: SELECT id, email, firstname, lastname, password FROM users WHERE email = ? AND status = "active"
 */

require_once '../config.php';

// Si ya está logueado, redirigir
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_POST) {
    // Validar CSRF
    if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = '❌ Token de seguridad inválido';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = '❌ Email y contraseña son requeridos';
        } else {
            $user = Auth::loginUser($email, $password);
            if ($user) {
                $_SESSION['user_login_time'] = time();
                $success = '✅ Login exitoso, redirigiendo...';
                echo '<script>
                    setTimeout(function() {
                        window.location.href = "index.php";
                    }, 1500);
                </script>';
            } else {
                $error = '❌ Email o contraseña incorrectos';
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
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../publico/css/login.css">
</head>
<body>
    <div class="login-container">
        <!-- HEADER -->
        <div class="login-header">
            <div class="login-icon">📋</div>
            <h1><?php echo APP_NAME; ?></h1>
            <p>Portal de Clientes</p>
        </div>

        <!-- TABS -->
        <div class="login-tabs">
            <button class="login-tab active">👤 Cliente</button>
            <button class="login-tab" onclick="window.location.href='../agente/login.php'">🛠️ Agente</button>
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

            <!-- Email -->
            <div class="form-group">
                <label for="email">📧 Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="tu@email.com"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    required
                >
            </div>

            <!-- Contraseña -->
            <div class="form-group">
                <label for="password">🔐 Contraseña</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="••••••••"
                    required
                >
            </div>

            <!-- Recordar -->
            <div class="form-remember">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Recuérdame en este dispositivo</label>
            </div>

            <!-- Olvidé contraseña -->
            <div class="form-forgot">
                <a href="recuperar.php">¿Olvidaste tu contraseña?</a>
            </div>

            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <!-- Botón Login -->
            <button type="submit" class="btn-login">Iniciar Sesión</button>
        </form>

        <!-- FOOTER -->
        <div class="login-footer">
            ¿No tienes cuenta? <a href="registrar.php">Registrate aquí</a>
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
