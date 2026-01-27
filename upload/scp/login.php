<?php
/**
 * LOGIN AGENTE
 * Formulario de autenticación para agentes/staff
 * 
 * SQL: SELECT id, username, email, firstname, lastname, password FROM staff WHERE username = ? AND is_active = 1
 */

require_once '../../config.php';

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
                    header('Location: index.php');
                    exit;
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
    <link rel="stylesheet" href="../../publico/css/agent-login.css">
</head>
<body class="agent-login">
    <!-- CONTENEDOR PRINCIPAL -->
    <div class="agent-login-container">
        <!-- PANEL DE LOGIN (GLASSMORPHISM) -->
        <div class="agent-login-panel">
            <h1 class="agent-login-title">LOGIN</h1>

            <form method="post" class="agent-login-form">
                <!-- Alertas -->
                <?php if ($error): ?>
                    <div class="agent-alert agent-alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="agent-alert agent-alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Usuario -->
                <div class="agent-form-group">
                    <label for="username">Usuario</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="Usuario"
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                        required
                    >
                    <svg class="agent-input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>

                <!-- Contraseña -->
                <div class="agent-form-group">
                    <label for="password">Contraseña</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Contraseña"
                        required
                    >
                    <svg class="agent-input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>

                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <!-- Botón Login -->
                <button type="submit" class="agent-btn-login">Inicia sesión</button>
            </form>
        </div>
    </div>

    <script>
        // Prevenir submit duplicado
        document.querySelector('form').addEventListener('submit', function(e) {
            const btn = this.querySelector('.agent-btn-login');
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
