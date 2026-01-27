<?php
/**
 * GESTIONAR USUARIOS
 * CRUD de usuarios del sistema
 */

require_once '../config.php';
require_once '../includes/helpers.php';
require_once '../includes/Auth.php';

requireLogin('agente');

// Obtener todos los usuarios
$stmt = $mysqli->prepare(
    'SELECT id, email, firstname, lastname, company, status, created, last_login
     FROM users
     ORDER BY created DESC'
);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Usuarios - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }
        
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        
        .btn-small {
            padding: 4px 8px;
            font-size: 0.85rem;
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

    <div class="container mt-4">
        <h2>Gestionar Usuarios</h2>
        <p class="text-muted">Total: <strong><?php echo count($users); ?></strong> usuarios registrados</p>

        <div class="table-container">
            <?php if (empty($users)): ?>
                <div class="alert alert-info">No hay usuarios registrados</div>
            <?php else: ?>
                <table class="table table-hover table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Email</th>
                            <th>Nombre</th>
                            <th>Empresa</th>
                            <th>Estado</th>
                            <th>Registrado</th>
                            <th>Último Login</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo html($user['email']); ?></td>
                                <td><?php echo html($user['firstname'] . ' ' . $user['lastname']); ?></td>
                                <td><?php echo html($user['company'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge" style="background: <?php echo $user['status'] == 'active' ? '#27ae60' : '#e74c3c'; ?>; color: white;">
                                        <?php echo html($user['status']); ?>
                                    </span>
                                </td>
                                <td><small><?php echo formatDate($user['created']); ?></small></td>
                                <td><small><?php echo formatDate($user['last_login']); ?></small></td>
                                <td>
                                    <a href="editar-usuario.php?id=<?php echo $user['id']; ?>" class="btn btn-warning btn-small">Editar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
