<?php
/**
 * REGISTRO DE CLIENTE
 * Formulario para que nuevos clientes se registren
 */

require_once '../config.php';
require_once '../includes/helpers.php';

// Si ya está logueado, redirigir
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
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
        $company = trim($_POST['company'] ?? '');
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
<body>
    <div class="login-container">
        <!-- HEADER -->
        <div class="login-header">
            <div class="login-icon"></div>
            <h1>Registro de Cliente</h1>
            <p><?php echo APP_NAME; ?></p>
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

            <!-- Nombre -->
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

            <!-- Apellido -->
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

            <!-- Email -->
            <div class="form-group">
                <label for="email">Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="tu@email.com"
                    value="<?php echo html($_POST['email'] ?? ''); ?>"
                    required
                >
            </div>

            <!-- Empresa (opcional) -->
            <div class="form-group">
                <label for="company">Empresa (opcional)</label>
                <input 
                    type="text" 
                    id="company" 
                    name="company" 
                    placeholder="Tu empresa"
                    value="<?php echo html($_POST['company'] ?? ''); ?>"
                >
            </div>

            <!-- Teléfono (opcional) -->
            <div class="form-group">
                <label for="phone">Teléfono (opcional)</label>
                <input 
                    type="tel" 
                    id="phone" 
                    name="phone" 
                    placeholder="+1 (555) 000-0000"
                    value="<?php echo html($_POST['phone'] ?? ''); ?>"
                >
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
                <small>Mínimo 6 caracteres</small>
            </div>

            <!-- Confirmar Contraseña -->
            <div class="form-group">
                <label for="password_confirm">Confirmar Contraseña</label>
                <input 
                    type="password" 
                    id="password_confirm" 
                    name="password_confirm" 
                    placeholder="••••••••"
                    required
                >
            </div>

            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <!-- Botón -->
            <button type="submit" class="btn-login">Registrarse</button>
        </form>

        <!-- FOOTER -->
        <div class="login-footer">
            ¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a>
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
