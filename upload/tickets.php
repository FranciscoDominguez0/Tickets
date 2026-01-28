<?php
/**
 * VER TICKETS (USUARIO)
 * Lista de tickets del usuario
 */

require_once '../config.php';
require_once '../includes/helpers.php';

// Validar que sea cliente
requireLogin('cliente');

$user = getCurrentUser();

// Obtener tickets del usuario
$tickets = [];
$stmt = $mysqli->prepare('
    SELECT t.id, t.ticket_number, t.subject, t.created, ts.name as status_name, ts.color as status_color,
           p.name as priority_name, p.color as priority_color
    FROM tickets t
    LEFT JOIN ticket_status ts ON t.status_id = ts.id
    LEFT JOIN priorities p ON t.priority_id = p.id
    WHERE t.user_id = ?
    ORDER BY t.created DESC
');
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tickets[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Tickets - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container-main {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .page-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .tickets-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand"><?php echo APP_NAME; ?></span>
            <div>
                <a href="open.php" class="btn btn-outline-light btn-sm">Crear Ticket</a>
                <a href="profile.php" class="btn btn-outline-light btn-sm">Mi Perfil</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container-main">
        <div class="page-header">
            <h2>Mis Tickets</h2>
            <p class="text-muted">Bienvenido, <?php echo html($user['name']); ?></p>
        </div>

        <div class="tickets-table">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Número</th>
                        <th>Asunto</th>
                        <th>Estado</th>
                        <th>Prioridad</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <p class="text-muted">No tienes tickets aún.</p>
                                <a href="open.php" class="btn btn-primary">Crear Primer Ticket</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td><strong><?php echo html($ticket['ticket_number']); ?></strong></td>
                                <td><?php echo html($ticket['subject']); ?></td>
                                <td>
                                    <span class="badge" style="background-color: <?php echo html($ticket['status_color']); ?>">
                                        <?php echo html($ticket['status_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo html($ticket['priority_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($ticket['created'])); ?></td>
                                <td>
                                    <a href="view-ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-primary">Ver</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
