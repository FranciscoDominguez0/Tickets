<?php
/**
 * PERFIL USUARIO
 * Editar información del usuario
 */

require_once '../config.php';
require_once '../includes/helpers.php';

// Validar que sea cliente
requireLogin('cliente');

$user = getCurrentUser();
$error = '';
$success = '';

// Obtener datos completos del usuario
$stmt = $mysqli->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();

if ($_POST) {
    if (!validateCSRF()) {
        $error = 'Token de seguridad inválido';
    } else {
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname = trim($_POST['lastname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($firstname) || empty($lastname) || empty($email)) {
            $error = 'Nombre, apellido y email son requeridos';
        } else {
            $stmt = $mysqli->prepare('UPDATE users SET firstname = ?, lastname = ?, email = ?, company = ?, phone = ? WHERE id = ?');
            $stmt->bind_param('sssssi', $firstname, $lastname, $email, $company, $phone, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $_SESSION['user_name'] = $firstname . ' ' . $lastname;
                $_SESSION['user_email'] = $email;
                $success = 'Perfil actualizado exitosamente';
                $userData['firstname'] = $firstname;
                $userData['lastname'] = $lastname;
                $userData['email'] = $email;
                $userData['company'] = $company;
                $userData['phone'] = $phone;
            } else {
                $error = 'Error al actualizar: ' . $mysqli->error;
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
    <title>Mi Perfil - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 56px;
        }
        .container-main {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .profile-card {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark" style="position: fixed; top: 0; left: 0; width: 100%; z-index: 1030;">
        <div class="container-fluid">
            <span class="navbar-brand"><?php echo APP_NAME; ?></span>
            <div>
                <a href="tickets.php" class="btn btn-outline-light btn-sm">Mis Tickets</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container-main">
        <div class="profile-card">
            <h2 class="mb-4">Mi Perfil</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="firstname" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="firstname" name="firstname" 
                               value="<?php echo html($userData['firstname'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="lastname" class="form-label">Apellido</label>
                        <input type="text" class="form-control" id="lastname" name="lastname" 
                               value="<?php echo html($userData['lastname'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo html($userData['email'] ?? ''); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="company" class="form-label">Empresa</label>
                    <input type="text" class="form-control" id="company" name="company" 
                           value="<?php echo html($userData['company'] ?? ''); ?>">
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Teléfono</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           value="<?php echo html($userData['phone'] ?? ''); ?>">
                </div>

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                <a href="tickets.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</body>
</html>
