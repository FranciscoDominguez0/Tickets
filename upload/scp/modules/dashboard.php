<?php
// Módulo: Panel de control (dashboard)

// Estadísticas básicas para tarjetas
$stats = [
    'total_tickets'   => 0,
    'open_tickets'    => 0,
    'assigned_to_me'  => 0
];

// Tickets abiertos
$stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM tickets WHERE status_id = 1');
$stmt->execute();
$result = $stmt->get_result();
$stats['open_tickets'] = (int)$result->fetch_assoc()['count'];

// Tickets asignados al agente
$stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM tickets WHERE staff_id = ?');
$stmt->bind_param('i', $_SESSION['staff_id']);
$stmt->execute();
$result = $stmt->get_result();
$stats['assigned_to_me'] = (int)$result->fetch_assoc()['count'];

// Total de tickets
$stmt = $mysqli->prepare('SELECT COUNT(*) as count FROM tickets');
$stmt->execute();
$result = $stmt->get_result();
$stats['total_tickets'] = (int)$result->fetch_assoc()['count'];

// Listado de tickets abiertos (como la pestaña Open de osTicket)
$sqlTickets = "
    SELECT t.*, 
           u.firstname, u.lastname, u.email,
           IFNULL(CONCAT(s.firstname, ' ', s.lastname), 'Sin asignar') AS staff_name,
           ts.name AS status_name,
           p.name  AS priority_name
    FROM tickets t
    JOIN users u        ON t.user_id = u.id
    LEFT JOIN staff s   ON t.staff_id = s.id
    JOIN ticket_status ts ON t.status_id = ts.id
    JOIN priorities p   ON t.priority_id = p.id
    WHERE t.status_id = 1
    ORDER BY t.created DESC
";

$result = $mysqli->query($sqlTickets);
$tickets = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!-- BIENVENIDA -->
<div class="welcome-card">
    <h1>¡Bienvenido, <?php echo html(explode(' ', $staff['name'])[0]); ?>!</h1>
    <p>Panel de control para gestionar y resolver tickets de clientes.</p>
</div>

<!-- PESTAÑAS SUPERIORES -->
<div class="top-tabs">
    <div class="top-tabs-nav">
        <a href="dashboard.php" class="top-tab-link <?php echo $currentRoute === 'dashboard' ? 'active' : ''; ?>">Panel de control</a>
        <a href="tickets.php" class="top-tab-link <?php echo $currentRoute === 'tickets' ? 'active' : ''; ?>">Solicitudes</a>
        <a href="users.php" class="top-tab-link <?php echo $currentRoute === 'users' ? 'active' : ''; ?>">Usuarios</a>
        <a href="tasks.php" class="top-tab-link <?php echo $currentRoute === 'tasks' ? 'active' : ''; ?>">Tareas</a>
        <a href="canned.php" class="top-tab-link <?php echo $currentRoute === 'canned' ? 'active' : ''; ?>">Base de conocimientos</a>
    </div>
    <div>
        <a href="../open.php" class="btn btn-sm btn-success">Nuevo Ticket</a>
    </div>
</div>

<!-- ESTADÍSTICAS RÁPIDAS -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?php echo $stats['total_tickets']; ?></div>
        <div class="stat-label">Total de Tickets</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $stats['open_tickets']; ?></div>
        <div class="stat-label">Tickets Abiertos</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $stats['assigned_to_me']; ?></div>
        <div class="stat-label">Asignados a Mí</div>
    </div>
</div>

<!-- LISTADO DE TICKETS (similar a la pestaña Open de osTicket) -->
<div style="background: #ffffff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Open tickets</h5>
        <small class="text-muted">Mostrando <?php echo count($tickets); ?> ticket(s) abiertos</small>
    </div>

    <!-- Filtros secundarios -->
    <div class="ticket-filters">
        <button class="btn btn-sm btn-outline-secondary active">Open</button>
        <a href="tickets.php" class="btn btn-sm btn-outline-secondary">My Tickets</a>
        <button class="btn btn-sm btn-outline-secondary" disabled>Closed</button>
        <button class="btn btn-sm btn-outline-secondary" disabled>Buscar</button>
    </div>

    <?php if (empty($tickets)): ?>
        <div class="alert alert-info mt-3 mb-0">No hay tickets abiertos actualmente.</div>
    <?php else: ?>
        <div class="table-responsive mt-2">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" /></th>
                        <th>Ticket</th>
                        <th>Última actualización</th>
                        <th>Asunto</th>
                        <th>De</th>
                        <th>Prioridad</th>
                        <th>Asignado a</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td><input type="checkbox" /></td>
                            <td>
                                <strong><?php echo html($ticket['ticket_number']); ?></strong>
                            </td>
                            <td>
                                <small><?php echo formatDate($ticket['created']); ?></small>
                            </td>
                            <td>
                                <a href="../../agente/ver-ticket.php?id=<?php echo (int)$ticket['id']; ?>">
                                    <?php echo html(substr($ticket['subject'], 0, 60)); ?>
                                </a>
                            </td>
                            <td>
                                <small><?php echo html($ticket['firstname'] . ' ' . $ticket['lastname']); ?></small>
                            </td>
                            <td>
                                <?php
                                    $priorityColor = '#f39c12';
                                    if ($ticket['priority_name'] === 'Urgente') {
                                        $priorityColor = '#e74c3c';
                                    } elseif ($ticket['priority_name'] === 'Alta') {
                                        $priorityColor = '#e67e22';
                                    }
                                ?>
                                <span class="badge-priority" style="background: <?php echo $priorityColor; ?>">
                                    <?php echo html($ticket['priority_name']); ?>
                                </span>
                            </td>
                            <td>
                                <small><?php echo html($ticket['staff_name']); ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

