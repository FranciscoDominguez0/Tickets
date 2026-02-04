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

$filter = $_GET['filter'] ?? 'open';
if (!in_array($filter, ['open', 'closed', 'all'], true)) $filter = 'open';
$q = trim($_GET['q'] ?? '');
$where = 't.user_id = ?';
if ($filter === 'open') {
    $where .= ' AND t.closed IS NULL';
} elseif ($filter === 'closed') {
    $where .= ' AND t.closed IS NOT NULL';
}

// Obtener tickets del usuario
$tickets = [];
$sql = '
    SELECT t.id, t.ticket_number, t.subject, t.created, t.closed,
           ts.name as status_name, ts.color as status_color,
           p.name as priority_name, p.color as priority_color
    FROM tickets t
    LEFT JOIN ticket_status ts ON t.status_id = ts.id
    LEFT JOIN priorities p ON t.priority_id = p.id
    WHERE ' . $where;
if ($q !== '') {
    $sql .= ' AND (t.ticket_number LIKE ? OR t.subject LIKE ?)';
}
$sql .= ' ORDER BY COALESCE(t.updated, t.created) DESC, t.created DESC';

$stmt = $mysqli->prepare($sql);
$uid = (int) ($_SESSION['user_id'] ?? 0);
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt->bind_param('iss', $uid, $like, $like);
} else {
    $stmt->bind_param('i', $uid);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tickets[] = $row;
}

$countOpen = 0;
$countClosed = 0;
$stmtC = $mysqli->prepare('SELECT SUM(closed IS NULL) AS c_open, SUM(closed IS NOT NULL) AS c_closed FROM tickets WHERE user_id = ?');
$stmtC->bind_param('i', $uid);
$stmtC->execute();
if ($r = $stmtC->get_result()->fetch_assoc()) {
    $countOpen = (int) ($r['c_open'] ?? 0);
    $countClosed = (int) ($r['c_closed'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Tickets - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #f1f5f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container-main {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #0f172a, #1d4ed8);
            padding: 26px 28px;
            border-radius: 16px;
            margin-bottom: 18px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.25);
            color: #fff;
        }
        .page-header .sub { color: rgba(255,255,255,0.85); }

        .panel {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .tabs {
            display: flex;
            gap: 0;
            padding: 0 18px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .tabs a {
            padding: 14px 16px;
            text-decoration: none;
            font-weight: 700;
            color: #64748b;
            border-bottom: 3px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .tabs a.active { color: #2563eb; border-bottom-color: #2563eb; background: #fff; border-radius: 10px 10px 0 0; }
        .tabs .count { background: #e2e8f0; color: #0f172a; padding: 2px 8px; border-radius: 999px; font-size: 0.8rem; }

        .panel-head {
            padding: 16px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .search {
            min-width: 260px;
            max-width: 420px;
            width: 100%;
        }

        .tickets-table { padding: 0 18px 18px; }
        .tickets-table .table { margin-bottom: 0; }
        .tickets-table .table thead th { font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .tickets-table .table tbody tr:hover { background: #f8fafc; }
        .badge-soft { display: inline-block; padding: 6px 10px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; }
        .mono { font-variant-numeric: tabular-nums; }
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
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div>
                    <h2 class="mb-1">Mis Tickets</h2>
                    <div class="sub">Bienvenido, <?php echo html($user['name']); ?> (<?php echo html($user['email']); ?>)</div>
                </div>
                <div>
                    <a href="open.php" class="btn btn-light btn-sm"><i class="bi bi-plus-circle"></i> Abrir ticket</a>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="tabs">
                <a class="<?php echo $filter === 'open' ? 'active' : ''; ?>" href="tickets.php?filter=open<?php echo $q !== '' ? '&q=' . urlencode($q) : ''; ?>">
                    <i class="bi bi-folder2-open"></i> Abiertos <span class="count"><?php echo (int)$countOpen; ?></span>
                </a>
                <a class="<?php echo $filter === 'closed' ? 'active' : ''; ?>" href="tickets.php?filter=closed<?php echo $q !== '' ? '&q=' . urlencode($q) : ''; ?>">
                    <i class="bi bi-check2-circle"></i> Cerrados <span class="count"><?php echo (int)$countClosed; ?></span>
                </a>
                <a class="<?php echo $filter === 'all' ? 'active' : ''; ?>" href="tickets.php?filter=all<?php echo $q !== '' ? '&q=' . urlencode($q) : ''; ?>">
                    <i class="bi bi-inboxes"></i> Todos <span class="count"><?php echo (int)($countOpen + $countClosed); ?></span>
                </a>
            </div>

            <div class="panel-head">
                <div class="text-muted">Gestiona tus solicitudes y revisa respuestas del equipo.</div>
                <form method="get" class="search">
                    <input type="hidden" name="filter" value="<?php echo html($filter); ?>">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" name="q" value="<?php echo html($q); ?>" placeholder="Buscar por número o asunto">
                        <button class="btn btn-primary" type="submit">Buscar</button>
                    </div>
                </form>
            </div>

            <div class="tickets-table">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Asunto</th>
                            <th>Estado</th>
                            <th>Prioridad</th>
                            <th>Fecha</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tickets)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted mb-3">No hay tickets para este filtro.</div>
                                    <a href="open.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Abrir ticket</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <td class="mono"><strong><?php echo html($ticket['ticket_number']); ?></strong></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo html($ticket['subject']); ?></div>
                                        <?php if (!empty($ticket['closed'])): ?>
                                            <div class="text-muted" style="font-size: 0.85rem;">Cerrado: <?php echo date('d/m/Y H:i', strtotime($ticket['closed'])); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-soft" style="background-color: <?php echo html($ticket['status_color']); ?>; color: #fff;">
                                            <?php echo html($ticket['status_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-soft" style="background-color: <?php echo html($ticket['priority_color'] ?? '#64748b'); ?>; color: #fff;">
                                            <?php echo html($ticket['priority_name']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted"><?php echo date('d/m/Y H:i', strtotime($ticket['created'])); ?></td>
                                    <td class="text-end">
                                        <a href="view-ticket.php?id=<?php echo (int)$ticket['id']; ?>" class="btn btn-sm btn-primary"><i class="bi bi-eye"></i> Ver</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
