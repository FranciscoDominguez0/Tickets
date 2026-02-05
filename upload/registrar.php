<?php
/**
 * REGISTRO DE CLIENTE
 * Formulario para que nuevos clientes se registren
 */

require_once '../config.php';
require_once '../includes/helpers.php';

// Si ya está logueado, redirigir
if (isset($_SESSION['user_id'])) {
    header('Location: tickets.php');
    exit;
}

$error = '';
$success = '';

if ($_POST) {
    if (!validateCSRF()) {
        $error = 'Token de seguridad inválido';
    } else {
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname = trim($_POST['lastname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $phone = trim($_POST['phone'] ?? '');

        // Validaciones
        if (!$firstname || !$lastname || !$email || !$password) {
            $error = 'Nombre, apellido, email y contraseña son requeridos';
        } elseif (!isValidEmail($email)) {
            $error = 'Email no válido';
        } elseif (strlen($password) < 6) {
            $error = 'Contraseña debe tener al menos 6 caracteres';
        } elseif ($password !== $password_confirm) {
            $error = 'Las contraseñas no coinciden';
        } else {
            // Verificar si email existe
            $stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = 'Este email ya está registrado';
            } else {
                // Hash de contraseña
                $password_hash = password_hash($password, PASSWORD_BCRYPT);

                // Insertar usuario
                $stmt = $mysqli->prepare(
                    'INSERT INTO users (firstname, lastname, email, password, company, phone, status, created)
                     VALUES (?, ?, ?, ?, ?, ?, "active", NOW())'
                );
                $company = '';
                $stmt->bind_param('ssssss', $firstname, $lastname, $email, $password_hash, $company, $phone);

                if ($stmt->execute()) {
                    $success = 'Registro exitoso! Redirigiendo al login...';
                    echo '<script>
                        setTimeout(function() {
                            window.location.href = "login.php";
                        }, 2000);
                    </script>';
                } else {
                    $error = 'Error al registrarse: ' . $mysqli->error;
                }
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
    <title>Registrarse - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../publico/css/login.css">
</head>
<?php
$bgMode = (string)getAppSetting('login.background_mode', 'default');
$loginBg = $bgMode === 'custom' ? (string)getBrandAssetUrl('login.background', '') : '';
$bodyStyle = $loginBg !== ''
    ? ('background-image: linear-gradient(135deg, rgba(240, 244, 248, 0.92) 0%, rgba(226, 232, 240, 0.92) 100%), url(' . html($loginBg) . '); background-size: cover, cover; background-position: center, center; background-repeat: no-repeat, no-repeat;')
    : '';
?>
<body style="<?php echo $bodyStyle; ?>">
    <div class="support-center-wrapper">
        <!-- HEADER SUPERIOR -->
        <div class="support-header">
            <div class="support-header-left">
                <?php $brandLogo = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png'); ?>
                <img src="<?php echo html($brandLogo); ?>" alt="VIGITEC PANAMA" class="vigitec-logo">
            </div>
            <div class="support-header-right">
                <span class="guest-user">Usuario Invitado</span>
                <span class="header-separator">|</span>
                <a href="login.php" class="header-login-link">Inicia Sesión</a>
            </div>
        </div>

        <!-- NAVEGACIÓN -->
        <div class="support-nav">
            <button class="nav-item active">Inicio Centro de Soporte</button>
        </div>

        <!-- CONTENIDO PRINCIPAL -->
        <div class="support-content" style="max-width: 1200px;">
            <div class="welcome-section">
                <h2 class="welcome-title">Cree una cuenta en <?php echo APP_NAME; ?></h2>
                <p class="welcome-text">Complete el formulario para registrarse. Si ya tiene cuenta, puede iniciar sesión.</p>
            </div>

            <!-- PANEL DE REGISTRO -->
            <div class="login-panel" style="grid-template-columns: 1fr; width: 100%; max-width: 1100px; margin-left: auto; margin-right: auto;">
                <div class="login-panel-left" style="width: 100%;">
                    <form method="post" class="login-form">
                        <!-- Alertas -->
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="firstname">Nombre</label>
                            <input 
                                type="text" 
                                id="firstname" 
                                name="firstname" 
                                placeholder="Tu nombre"
                                value="<?php echo html($_POST['firstname'] ?? ''); ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="lastname">Apellido</label>
                            <input 
                                type="text" 
                                id="lastname" 
                                name="lastname" 
                                placeholder="Tu apellido"
                                value="<?php echo html($_POST['lastname'] ?? ''); ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="email">Correo electrónico</label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                placeholder="tu@email.com"
                                value="<?php echo html($_POST['email'] ?? ''); ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="phone">Teléfono (opcional)</label>
                            <input 
                                type="tel" 
                                id="phone" 
                                name="phone" 
                                placeholder="+507 6621-8585"
                                value="<?php echo html($_POST['phone'] ?? ''); ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="password">Contraseña</label>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                placeholder="Contraseña"
                                required
                            >
                            <small>Mínimo 6 caracteres</small>
                        </div>

                        <div class="form-group">
                            <label for="password_confirm">Confirmar Contraseña</label>
                            <input 
                                type="password" 
                                id="password_confirm" 
                                name="password_confirm" 
                                placeholder="Confirmar contraseña"
                                required
                            >
                        </div>

                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <button type="submit" class="btn-login">Crear cuenta</button>
                    </form>
                </div>
            </div>

        </div>

        <!-- FOOTER -->
        <div class="support-footer">
            <p class="copyright">
                Derechos de autor © <?php echo date('Y'); ?> Vigitec Panama - <?php echo APP_NAME; ?> - Todos los derechos reservados.
            </p>
        </div>
    </div>

    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const btn = this.querySelector('.btn-login');
            if (btn.disabled) {
                e.preventDefault();
                return false;
            }
            btn.disabled = true;
            btn.classList.add('loading');
            btn.textContent = 'Registrando...';
        });
    </script>
</body>
</html>
