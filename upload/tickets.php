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
            background: #f6f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 56px;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(700px circle at 12% 0%, rgba(245, 158, 11, 0.08), transparent 52%),
                radial-gradient(900px circle at 88% 10%, rgba(99, 102, 241, 0.10), transparent 55%),
                repeating-linear-gradient(135deg, rgba(15, 23, 42, 0.02) 0px, rgba(15, 23, 42, 0.02) 1px, transparent 1px, transparent 14px);
            z-index: -1;
        }

        .topbar {
            background: linear-gradient(135deg, #0b1220, #111827);
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }
        .topbar .navbar-brand { font-weight: 900; letter-spacing: 0.02em; }
        .topbar .profile-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            text-decoration: none;
        }
        .topbar .profile-brand .brand-logo-wrap {
            height: 36px;
            padding: 6px 10px;
            border-radius: 14px;
            background: rgba(255,255,255,0.92);
            border: 1px solid rgba(15, 23, 42, 0.12);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.14);
        }
        .topbar .profile-brand .brand-logo {
            height: 22px;
            width: auto;
            max-width: 160px;
            object-fit: contain;
            display: block;
        }
        .topbar .user-menu-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border-radius: 999px;
            font-weight: 800;
        }
        .topbar .user-menu-btn .uavatar {
            width: 30px;
            height: 30px;
            border-radius: 12px;
            background: rgba(255,255,255,0.92);
            color: #0b1220;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 1000;
            letter-spacing: 0.08em;
            flex: 0 0 auto;
        }
        .topbar .profile-brand .avatar {
            width: 36px;
            height: 36px;
            border-radius: 14px;
            background: rgba(255,255,255,0.92);
            color: #0b1220;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 1000;
            letter-spacing: 0.08em;
            flex: 0 0 auto;
        }
        .topbar .profile-brand .name {
            font-weight: 900;
            font-size: 0.98rem;
            line-height: 1.1;
        }
        .topbar .btn { border-radius: 999px; font-weight: 700; }

        .container-main {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .shell {
            max-width: 980px;
            margin: 0 auto;
        }

        .panel-soft {
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(10px);
            border-radius: 22px;
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .panel-soft {
            background-image:
                radial-gradient(900px circle at 0% 0%, rgba(37, 99, 235, 0.06), transparent 52%),
                radial-gradient(700px circle at 100% 0%, rgba(245, 158, 11, 0.06), transparent 55%);
        }

        .page-header {
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            padding: 22px 22px;
            border-radius: 16px;
            margin-bottom: 18px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
            color: #0f172a;
            border: 1px solid #e2e8f0;
            border-left: 6px solid #2563eb;
        }
        .page-header .sub { color: #64748b; font-weight: 700; }

        .panel {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .panel {
            transition: box-shadow .15s ease, border-color .15s ease;
        }
        .panel:hover {
            box-shadow: 0 14px 36px rgba(15, 23, 42, 0.10);
            border-color: #cbd5e1;
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
        .tabs a:hover { color: #0f172a; background: rgba(15,23,42,0.03); }
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
        .tickets-table .table tbody tr { transition: background .12s ease; }
        .badge-soft { display: inline-block; padding: 6px 10px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; }
        .mono { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body>
    <?php
        $navUserName = trim((string)($user['name'] ?? ''));
        $companyName = trim((string)getAppSetting('company.name', ''));
        $companyLogoUrl = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png');
        $navInitials = '';
        $parts = preg_split('/\s+/', trim($navUserName));
        if (!empty($parts[0])) $navInitials .= (function_exists('mb_substr') ? mb_substr($parts[0], 0, 1) : substr($parts[0], 0, 1));
        if (!empty($parts[1])) $navInitials .= (function_exists('mb_substr') ? mb_substr($parts[1], 0, 1) : substr($parts[1], 0, 1));
        $navInitials = strtoupper($navInitials ?: 'U');
        if ($navUserName === '') $navUserName = 'Mi Perfil';
    ?>
    <nav class="navbar navbar-dark topbar" style="position: fixed; top: 0; left: 0; width: 100%; z-index: 1030;">
        <div class="container-fluid">
            <a class="navbar-brand profile-brand" href="tickets.php">
                <span class="brand-logo-wrap" aria-hidden="true">
                    <img class="brand-logo" src="<?php echo html($companyLogoUrl); ?>" alt="<?php echo html($companyName !== '' ? $companyName : 'Logo'); ?>">
                </span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle user-menu-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="uavatar" aria-hidden="true"><?php echo html($navInitials); ?></span>
                        <span class="d-none d-sm-inline"><?php echo html($navUserName); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="tickets.php"><i class="bi bi-inboxes"></i> Mis Tickets</a></li>
                        <li><a class="dropdown-item" href="open.php"><i class="bi bi-plus-circle"></i> Crear Ticket</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Mi perfil</a></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-main">
        <div class="shell">
            <main class="panel-soft" style="padding: 18px;">
                <div class="page-header" style="margin-top: 0;">
                    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                        <div>
                            <h2 class="mb-1">Mis Tickets</h2>
                            <div class="sub">Gestiona tus solicitudes y revisa respuestas del equipo.</div>
                        </div>
                        <div>
                            <a href="open.php" class="btn btn-light btn-sm" style="border-radius: 999px; font-weight: 800;"><i class="bi bi-plus-circle"></i> Abrir ticket</a>
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
                        <div class="text-muted">Filtros y búsqueda</div>
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
            </main>
        </div>
    </div>
    <footer style="text-align: center; padding: 20px 0; background-color: #f8f9fa; border-top: 1px solid #dee2e6; margin-top: 40px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">
            Derechos de autor &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(getAppSetting('company.name', 'Vigitec Panama')); ?> - Sistema de Tickets - Todos los derechos reservados.
        </p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
