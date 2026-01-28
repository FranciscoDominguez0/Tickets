<?php
/**
 * TODOS LOS TICKETS
 * Listar todos los tickets del sistema
 */

require_once '../config.php';
require_once '../includes/helpers.php';
require_once '../includes/Auth.php';

requireLogin('agente');

$staffId = $_SESSION['staff_id'];

// Filtro actual
$filter = $_GET['filter'] ?? 'open';

// Contadores rápidos para las "pills" del dashboard
$counts = [
    'open'          => 0,
    'assigned_me'   => 0,
    'assigned_others' => 0,
    'unassigned'    => 0,
];

$sqlCounts = "
    SELECT
        SUM(status_id = 1)                                      AS open,
        SUM(status_id = 1 AND staff_id = ?)                     AS assigned_me,
        SUM(status_id = 1 AND staff_id IS NOT NULL AND staff_id <> ?) AS assigned_others,
        SUM(staff_id IS NULL OR staff_id = 0)                   AS unassigned
    FROM tickets
";
$stmt = $mysqli->prepare($sqlCounts);
$stmt->bind_param('ii', $staffId, $staffId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if ($row) {
    $counts['open']           = (int)$row['open'];
    $counts['assigned_me']    = (int)$row['assigned_me'];
    $counts['assigned_others']= (int)$row['assigned_others'];
    $counts['unassigned']     = (int)$row['unassigned'];
}

// Construir query principal según el filtro
$baseSql = "
    SELECT t.*, u.firstname, u.lastname, u.email, 
           IFNULL(CONCAT(s.firstname, ' ', s.lastname), 'Sin asignar') as staff_name,
           ts.name as status_name, p.name as priority_name
    FROM tickets t
    JOIN users u       ON t.user_id = u.id
    LEFT JOIN staff s  ON t.staff_id = s.id
    JOIN ticket_status ts ON t.status_id = ts.id
    JOIN priorities p  ON t.priority_id = p.id
    WHERE 1 = 1
";

$where = '';
$types = '';
$params = [];

switch ($filter) {
    case 'assigned_me':
        $where .= " AND t.status_id = 1 AND t.staff_id = ?";
        $types .= 'i';
        $params[] = $staffId;
        break;
    case 'assigned_others':
        $where .= " AND t.status_id = 1 AND t.staff_id IS NOT NULL AND t.staff_id <> ?";
        $types .= 'i';
        $params[] = $staffId;
        break;
    case 'unassigned':
        $where .= " AND (t.staff_id IS NULL OR t.staff_id = 0)";
        break;
    case 'all':
        // sin filtros adicionales
        break;
    case 'open':
    default:
        $where .= " AND t.status_id = 1";
        break;
}

$order = " ORDER BY t.created DESC";

$sql = $baseSql . $where . $order;
$stmt = $mysqli->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets - <?php echo APP_NAME; ?></title>
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
        
        .badge-status {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
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

    <div class="container-fluid mt-4">
        <h2>Tickets</h2>
        <p class="text-muted mb-3">Total: <strong><?php echo count($tickets); ?></strong> tickets</p>

        <!-- Filtros tipo dashboard -->
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="?filter=open" class="btn btn-sm <?php echo $filter === 'open' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                Open tickets <span class="badge bg-light text-primary ms-1"><?php echo $counts['open']; ?></span>
            </a>
            <a href="?filter=assigned_me" class="btn btn-sm <?php echo $filter === 'assigned_me' ? 'btn-success' : 'btn-outline-success'; ?>">
                Assigned to me <span class="badge bg-light text-success ms-1"><?php echo $counts['assigned_me']; ?></span>
            </a>
            <a href="?filter=assigned_others" class="btn btn-sm <?php echo $filter === 'assigned_others' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                Assigned to others <span class="badge bg-light text-warning ms-1"><?php echo $counts['assigned_others']; ?></span>
            </a>
            <a href="?filter=unassigned" class="btn btn-sm <?php echo $filter === 'unassigned' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">
                Unassigned <span class="badge bg-light text-secondary ms-1"><?php echo $counts['unassigned']; ?></span>
            </a>
            <a href="?filter=all" class="btn btn-sm <?php echo $filter === 'all' ? 'btn-dark' : 'btn-outline-dark'; ?>">
                All
            </a>
        </div>

        <div class="table-container">
            <?php if (empty($tickets)): ?>
                <div class="alert alert-info">No hay tickets en el sistema</div>
            <?php else: ?>
                <table class="table table-hover table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Número</th>
                            <th>Asunto</th>
                            <th>Usuario</th>
                            <th>Asignado a</th>
                            <th>Estado</th>
                            <th>Prioridad</th>
                            <th>Creado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td><strong><?php echo html($ticket['ticket_number']); ?></strong></td>
                                <td><?php echo html(substr($ticket['subject'], 0, 40)); ?></td>
                                <td>
                                    <small><?php echo html($ticket['firstname'] . ' ' . $ticket['lastname']); ?></small>
                                </td>
                                <td>
                                    <small><?php echo html($ticket['staff_name']); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-status" style="background: #3498db; color: white;">
                                        <?php echo html($ticket['status_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge" style="background: <?php echo $ticket['priority_name'] == 'Urgente' ? '#e74c3c' : '#f39c12'; ?>; color: white;">
                                        <?php echo html($ticket['priority_name']); ?>
                                    </span>
                                </td>
                                <td><small><?php echo formatDate($ticket['created']); ?></small></td>
                                <td>
                                    <a href="ver-ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-primary btn-small">Ver</a>
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
