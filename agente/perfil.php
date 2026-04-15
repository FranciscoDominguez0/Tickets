<?php
/**
 * PERFIL AGENTE
 * Editar información personal
 */

require_once '../config.php';
require_once '../includes/helpers.php';
require_once '../includes/Auth.php';

requireLogin('agente');

$staff_id = $_SESSION['staff_id'];
$error = '';
$success = '';

// Obtener datos del agente
// Check if staff_departments table exists
$hasStaffDepartmentsTable = false;
if (isset($mysqli) && $mysqli) {
    try {
        $rt = $mysqli->query("SHOW TABLES LIKE 'staff_departments'");
        $hasStaffDepartmentsTable = ($rt && $rt->num_rows > 0);
    } catch (Throwable $e) {
        $hasStaffDepartmentsTable = false;
    }
}

if ($hasStaffDepartmentsTable) {
    // New model: get departments from staff_departments
    $stmt = $mysqli->prepare(
        'SELECT s.id, s.username, s.email, s.firstname, s.lastname, s.role, s.is_active,
                GROUP_CONCAT(DISTINCT d.name ORDER BY d.name SEPARATOR ", ") AS dept_name
         FROM staff s
         LEFT JOIN staff_departments sd ON sd.staff_id = s.id
         LEFT JOIN departments d ON d.id = sd.dept_id
         WHERE s.id = ?
         GROUP BY s.id, s.username, s.email, s.firstname, s.lastname, s.role, s.is_active'
    );
} else {
    // Legacy model
    $stmt = $mysqli->prepare(
        'SELECT id, username, email, firstname, lastname, dept_id, role, is_active
         FROM staff WHERE id = ?'
    );
}
$stmt->bind_param('i', $staff_id);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();

if ($_POST) {
    if (!validateCSRF()) {
        $error = '❌ Token de seguridad inválido';
    } else {
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname = trim($_POST['lastname'] ?? '');

        if (!$firstname || !$lastname) {
            $error = '❌ Nombre y apellido son requeridos';
        } else {
            $stmt = $mysqli->prepare(
                'UPDATE staff SET firstname = ?, lastname = ? WHERE id = ?'
            );
            $stmt->bind_param('ssi', $firstname, $lastname, $staff_id);

            if ($stmt->execute()) {
                $success = '✅ Perfil actualizado correctamente';
                $_SESSION['staff_name'] = $firstname . ' ' . $lastname;
            } else {
                $error = '❌ Error al actualizar el perfil';
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 30px auto;
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand">🛠️ <?php echo APP_NAME; ?></span>
            <div class="d-flex align-items-center gap-3">
                <a href="dashboard.php" class="btn btn-sm btn-outline-light">← Volver</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="form-container">
        <h2 style="margin-bottom: 30px;">Mi Perfil</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label class="form-label">👤 Nombre</label>
                <input type="text" name="firstname" class="form-control" value="<?php echo html($staff['firstname']); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">👤 Apellido</label>
                <input type="text" name="lastname" class="form-control" value="<?php echo html($staff['lastname']); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">📧 Email</label>
                <input type="email" class="form-control" value="<?php echo html($staff['email']); ?>" disabled>
                <small class="text-muted">No se puede cambiar el email</small>
            </div>

            <div class="mb-3">
                <label class="form-label">👤 Usuario</label>
                <input type="text" class="form-control" value="<?php echo html($staff['username']); ?>" disabled>
                <small class="text-muted">No se puede cambiar el usuario</small>
            </div>

            <div class="mb-3">
                <label class="form-label">🎯 Rol</label>
                <input type="text" class="form-control" value="<?php echo html($staff['role']); ?>" disabled>
                <small class="text-muted">Contacta al administrador para cambiar tu rol</small>
            </div>

            <?php csrfField(); ?>

            <button type="submit" class="btn btn-primary w-100">Guardar Cambios</button>
            <a href="dashboard.php" class="btn btn-secondary w-100 mt-2">Cancelar</a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
