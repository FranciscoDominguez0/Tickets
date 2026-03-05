<?php
/**
 * empresas_actividad.php
 * Ruta: /scp/superadmin/empresas_actividad.php
 *
 * Reporte: actividad por empresa
 */

require_once '../../../config.php';
require_once '../../../includes/helpers.php';

ob_start();

global $mysqli;

$hasEmpresas = false;
$hasTickets = false;
$hasUsers = false;
$hasStaff = false;

$selectedId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$q = trim((string)($_GET['q'] ?? ''));

$ticketLimit = isset($_GET['tlimit']) && is_numeric($_GET['tlimit']) ? (int)$_GET['tlimit'] : 5;
$allowedLimits = [5 => true, 10 => true, 30 => true, 50 => true, 100 => true];
if (!isset($allowedLimits[$ticketLimit])) $ticketLimit = 5;

$ticketFrom = trim((string)($_GET['t_from'] ?? ''));
$ticketTo = trim((string)($_GET['t_to'] ?? ''));
if ($ticketFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ticketFrom)) $ticketFrom = '';
if ($ticketTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ticketTo)) $ticketTo = '';

$dirProvided = array_key_exists('dir', $_GET);
$directoryView = strtolower(trim((string)($_GET['dir'] ?? 'users')));
if (!in_array($directoryView, ['users', 'staff', 'both'], true)) {
    $directoryView = 'users';
}

if (isset($mysqli) && $mysqli) {
    try {
        $hasEmpresas = ($mysqli->query('SELECT 1 FROM empresas LIMIT 1') !== false);
    } catch (Throwable $e) {
        $hasEmpresas = false;
    }
}

$dbName = '';
$tableCols = [
    'users' => [],
    'staff' => [],
    'tickets' => [],
];

if (isset($mysqli) && $mysqli) {
    try {
        $dbName = (string)($mysqli->query('SELECT DATABASE() db')->fetch_assoc()['db'] ?? '');
        if ($dbName !== '') {
            $escDb = $mysqli->real_escape_string($dbName);
            $resCols = $mysqli->query(
                "SELECT TABLE_NAME, COLUMN_NAME\n"
                . "FROM INFORMATION_SCHEMA.COLUMNS\n"
                . "WHERE TABLE_SCHEMA = '{$escDb}'\n"
                . "  AND TABLE_NAME IN ('users','staff','tickets')"
            );
            if ($resCols) {
                while ($c = $resCols->fetch_assoc()) {
                    $t = (string)($c['TABLE_NAME'] ?? '');
                    $cn = (string)($c['COLUMN_NAME'] ?? '');
                    if ($t !== '' && $cn !== '' && isset($tableCols[$t])) {
                        $tableCols[$t][$cn] = true;
                    }
                }
            }
        }
    } catch (Throwable $e) {
    }

    try {
        $hasTickets = ($mysqli->query('SELECT 1 FROM tickets LIMIT 1') !== false);
    } catch (Throwable $e) {
        $hasTickets = false;
    }
    try {
        $hasUsers = ($mysqli->query('SELECT 1 FROM users LIMIT 1') !== false);
    } catch (Throwable $e) {
        $hasUsers = false;
    }
    try {
        $hasStaff = ($mysqli->query('SELECT 1 FROM staff LIMIT 1') !== false);
    } catch (Throwable $e) {
        $hasStaff = false;
    }
}

$hasUserActiveCol = isset($tableCols['users']['is_active']);
$hasUserCreatedCol = isset($tableCols['users']['created']);
$hasStaffUserCol = isset($tableCols['staff']['username']);
$hasStaffActiveCol = isset($tableCols['staff']['is_active']);
$hasStaffLastLoginCol = isset($tableCols['staff']['last_login']);
$hasTicketClosedCol = isset($tableCols['tickets']['closed']);
$hasTicketCreatedCol = isset($tableCols['tickets']['created']);
$hasTicketUpdatedCol = isset($tableCols['tickets']['updated']);

$rows = [];

if ($hasEmpresas && isset($mysqli) && $mysqli) {
    $sql = "SELECT 
                e.id,
                e.nombre,
                e.estado,
                e.estado_pago,
                e.bloqueada,
                COALESCE(t.total_tickets, 0) AS total_tickets,
                COALESCE(t.tickets_abiertos, 0) AS tickets_abiertos,
                COALESCE(t.tickets_30d, 0) AS tickets_30d,
                COALESCE(u.total_users, 0) AS total_users,
                COALESCE(s.total_staff, 0) AS total_staff,
                COALESCE(s.total_admins, 0) AS total_admins,
                COALESCE(s.total_agents, 0) AS total_agents
            FROM empresas e
            LEFT JOIN (
                SELECT 
                    empresa_id,
                    COUNT(*) AS total_tickets,
                    SUM(closed IS NULL) AS tickets_abiertos,
                    SUM(created >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS tickets_30d
                FROM tickets
                GROUP BY empresa_id
            ) t ON t.empresa_id = e.id
            LEFT JOIN (
                SELECT empresa_id, COUNT(*) AS total_users
                FROM users
                GROUP BY empresa_id
            ) u ON u.empresa_id = e.id
            LEFT JOIN (
                SELECT 
                    empresa_id,
                    COUNT(*) AS total_staff,
                    SUM(LOWER(COALESCE(role,'agent')) = 'admin') AS total_admins,
                    SUM(LOWER(COALESCE(role,'agent')) <> 'admin') AS total_agents
                FROM staff
                GROUP BY empresa_id
            ) s ON s.empresa_id = e.id
            ORDER BY e.id DESC
            LIMIT 200";

    $res = $mysqli->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
}

$totalEmpresas = count($rows);
$totalTickets = 0;
$totalTickets30d = 0;
$totalUsers = 0;
$totalStaff = 0;

foreach ($rows as $r) {
    $totalTickets += (int)($r['total_tickets'] ?? 0);
    $totalTickets30d += (int)($r['tickets_30d'] ?? 0);
    $totalUsers += (int)($r['total_users'] ?? 0);
    $totalStaff += (int)($r['total_staff'] ?? 0);
}

$empresaDetail = null;
$auditUsers = [];
$auditStaffAdmins = [];
$auditStaffAgents = [];
$auditRecentTickets = [];

$auditCounts = [
    'users' => 0,
    'staff_total' => 0,
    'staff_admins' => 0,
    'staff_agents' => 0,
];

if ($selectedId > 0 && $hasEmpresas && isset($mysqli) && $mysqli) {
    $stmtE = $mysqli->prepare('SELECT id, nombre, estado, estado_pago, bloqueada FROM empresas WHERE id = ? LIMIT 1');
    if ($stmtE) {
        $stmtE->bind_param('i', $selectedId);
        if ($stmtE->execute()) {
            $empresaDetail = $stmtE->get_result()->fetch_assoc();
        }
    }

    if ($empresaDetail) {
        $like = '%' . $q . '%';

        // Conteos livianos (para KPIs aunque no se carguen listas completas)
        if ($hasUsers) {
            $stmtUc = $mysqli->prepare('SELECT COUNT(*) c FROM users WHERE empresa_id = ?');
            if ($stmtUc) {
                $stmtUc->bind_param('i', $selectedId);
                if ($stmtUc->execute()) {
                    $auditCounts['users'] = (int)($stmtUc->get_result()->fetch_assoc()['c'] ?? 0);
                }
            }
        }
        if ($hasStaff) {
            $stmtSc = $mysqli->prepare("SELECT COUNT(*) total, SUM(LOWER(COALESCE(role,'agent'))='admin') admins, SUM(LOWER(COALESCE(role,'agent'))<>'admin') agents FROM staff WHERE empresa_id = ?");
            if ($stmtSc) {
                $stmtSc->bind_param('i', $selectedId);
                if ($stmtSc->execute()) {
                    $row = $stmtSc->get_result()->fetch_assoc();
                    $auditCounts['staff_total'] = (int)($row['total'] ?? 0);
                    $auditCounts['staff_admins'] = (int)($row['admins'] ?? 0);
                    $auditCounts['staff_agents'] = (int)($row['agents'] ?? 0);
                }
            }
        }

        // Si no se indicó dir y no hay usuarios pero sí hay staff, mostrar Staff por defecto.
        if (!$dirProvided && (int)($auditCounts['users'] ?? 0) === 0 && (int)($auditCounts['staff_total'] ?? 0) > 0) {
            $directoryView = 'staff';
        }

        if ($hasUsers && ($directoryView === 'users' || $directoryView === 'both')) {
            $uSql = 'SELECT id, firstname, lastname, email';
            if ($hasUserActiveCol) $uSql .= ', is_active';
            if ($hasUserCreatedCol) $uSql .= ', created';
            $uSql .= ' FROM users WHERE empresa_id = ?';
            if ($q !== '') {
                $uSql .= ' AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR CONCAT(firstname, " ", lastname) LIKE ?)';
            }
            $uSql .= ' ORDER BY firstname, lastname LIMIT 500';

            $stmtU = $mysqli->prepare($uSql);
            if ($stmtU) {
                if ($q !== '') {
                    $stmtU->bind_param('issss', $selectedId, $like, $like, $like, $like);
                } else {
                    $stmtU->bind_param('i', $selectedId);
                }
                if ($stmtU->execute()) {
                    $resU = $stmtU->get_result();
                    while ($resU && ($r = $resU->fetch_assoc())) $auditUsers[] = $r;
                }
            }
        }

        if ($hasStaff && ($directoryView === 'staff' || $directoryView === 'both')) {
            $sSql = 'SELECT id, firstname, lastname, email, role';
            if ($hasStaffUserCol) $sSql .= ', username';
            if ($hasStaffActiveCol) $sSql .= ', is_active';
            if ($hasStaffLastLoginCol) $sSql .= ', last_login';
            $sSql .= ' FROM staff WHERE empresa_id = ?';
            if ($q !== '') {
                $sSql .= ' AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR CONCAT(firstname, " ", lastname) LIKE ?';
                if ($hasStaffUserCol) $sSql .= ' OR username LIKE ?';
                $sSql .= ')';
            }
            $sSql .= ' ORDER BY LOWER(COALESCE(role, "agent")) ASC, firstname, lastname LIMIT 500';

            $stmtS = $mysqli->prepare($sSql);
            if ($stmtS) {
                if ($q !== '') {
                    if ($hasStaffUserCol) {
                        $stmtS->bind_param('isssss', $selectedId, $like, $like, $like, $like, $like);
                    } else {
                        $stmtS->bind_param('issss', $selectedId, $like, $like, $like, $like);
                    }
                } else {
                    $stmtS->bind_param('i', $selectedId);
                }
                if ($stmtS->execute()) {
                    $resS = $stmtS->get_result();
                    while ($resS && ($r = $resS->fetch_assoc())) {
                        $role = strtolower((string)($r['role'] ?? 'agent'));
                        if ($role === 'admin') $auditStaffAdmins[] = $r;
                        else $auditStaffAgents[] = $r;
                    }
                }
            }
        }

        if ($hasTickets) {
            $tSql = 'SELECT id, ticket_number, subject, user_id, staff_id, dept_id';
            if ($hasTicketCreatedCol) $tSql .= ', created';
            if ($hasTicketUpdatedCol) $tSql .= ', updated';
            if ($hasTicketClosedCol) $tSql .= ', closed';
            $tSql .= ' FROM tickets WHERE empresa_id = ?';
            if ($q !== '') {
                $tSql .= ' AND (ticket_number LIKE ? OR subject LIKE ?)';
            }

            // Rango de fechas (se aplica al campo más confiable disponible)
            $dateCol = '';
            if ($hasTicketCreatedCol) $dateCol = 'created';
            elseif ($hasTicketUpdatedCol) $dateCol = 'updated';

            if ($dateCol !== '' && $ticketFrom !== '') {
                $tSql .= " AND DATE({$dateCol}) >= ?";
            }
            if ($dateCol !== '' && $ticketTo !== '') {
                $tSql .= " AND DATE({$dateCol}) <= ?";
            }

            if ($hasTicketUpdatedCol) $tSql .= ' ORDER BY updated DESC';
            elseif ($hasTicketCreatedCol) $tSql .= ' ORDER BY created DESC';
            else $tSql .= ' ORDER BY id DESC';
            $tSql .= ' LIMIT ' . (int)$ticketLimit;

            $stmtT = $mysqli->prepare($tSql);
            if ($stmtT) {
                $typesT = 'i';
                $paramsT = [&$typesT, &$selectedId];
                if ($q !== '') {
                    $typesT .= 'ss';
                    $paramsT[] = &$like;
                    $paramsT[] = &$like;
                }
                if ($dateCol !== '' && $ticketFrom !== '') {
                    $typesT .= 's';
                    $paramsT[] = &$ticketFrom;
                }
                if ($dateCol !== '' && $ticketTo !== '') {
                    $typesT .= 's';
                    $paramsT[] = &$ticketTo;
                }
                call_user_func_array([$stmtT, 'bind_param'], $paramsT);
                if ($stmtT->execute()) {
                    $resT = $stmtT->get_result();
                    while ($resT && ($r = $resT->fetch_assoc())) $auditRecentTickets[] = $r;
                }
            }
        }
    }
}
?>

<link rel="stylesheet" href="css/empresas.css">

<?php if ($selectedId <= 0): ?>
<div class="emp-hero mb-1">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="hero-icon"><i class="bi bi-activity"></i></div>
            <div>
                <h1>Empresas · Actividad de tickets</h1>
                <p>Resumen por empresa: tickets creados, usuarios y personal</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3 py-2">
                <i class="bi bi-calendar3 me-1"></i><?php echo date('d M Y'); ?>
            </span>
            <a href="empresas.php" class="btn btn-outline-secondary btn-sm px-3">
                <i class="bi bi-buildings me-1"></i>Volver a empresas
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($selectedId > 0 && $empresaDetail): ?>
    <div class="emp-hero mb-1">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon"><i class="bi bi-shield-check"></i></div>
                <div>
                    <h1>Auditoría de empresa</h1>
                    <p><?php echo html((string)($empresaDetail['nombre'] ?? '')); ?> · ID #<?php echo (int)($empresaDetail['id'] ?? 0); ?></p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="empresas_actividad.php" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="bi bi-arrow-left-circle me-1"></i>Volver
                </a>
                <a href="empresas.php?id=<?php echo (int)($empresaDetail['id'] ?? 0); ?>" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-buildings me-1"></i>Abrir empresa
                </a>
            </div>
        </div>
    </div>

    <div class="card pro-card mb-3">
        <div class="card-body">
            <form method="get">
                <input type="hidden" name="id" value="<?php echo (int)$selectedId; ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-lg-8">
                        <label class="form-label" style="font-size:.8rem">Búsqueda</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" name="q" value="<?php echo html($q); ?>" placeholder="Usuarios, staff, correo o ticket">
                        </div>
                        <div class="form-text">Filtra listas y también “Últimos tickets”.</div>
                    </div>
                    <div class="col-12 col-lg-4 d-flex gap-2">
                        <button class="btn btn-outline-primary w-100" type="submit">Buscar</button>
                        <a class="btn btn-outline-secondary w-100" href="empresas_actividad.php?id=<?php echo (int)$selectedId; ?>">Limpiar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <p class="section-title"><i class="bi bi-speedometer2"></i> Resumen</p>
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card kpi-card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted" style="font-size:.8rem">Usuarios</div>
                    <div class="fs-4 fw-bold"><?php echo (int)($auditCounts['users'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card kpi-card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted" style="font-size:.8rem">Admins</div>
                    <div class="fs-4 fw-bold"><?php echo (int)($auditCounts['staff_admins'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card kpi-card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted" style="font-size:.8rem">Agentes</div>
                    <div class="fs-4 fw-bold"><?php echo (int)($auditCounts['staff_agents'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card kpi-card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted" style="font-size:.8rem">Tickets recientes</div>
                    <div class="fs-4 fw-bold"><?php echo (int)count($auditRecentTickets); ?></div>
                </div>
            </div>
        </div>
    </div>

    <p class="section-title"><i class="bi bi-people"></i> Directorio</p>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <div class="text-muted" style="font-size:.85rem">Selecciona qué directorio cargar para ahorrar recursos.</div>
        <div class="btn-group" role="group" aria-label="Directorio">
            <?php
                $baseAudit = 'empresas_actividad.php?id=' . (int)$selectedId;
                if ($q !== '') $baseAudit .= '&q=' . urlencode($q);
                if ($ticketFrom !== '') $baseAudit .= '&t_from=' . urlencode($ticketFrom);
                if ($ticketTo !== '') $baseAudit .= '&t_to=' . urlencode($ticketTo);
                if ($ticketLimit > 0) $baseAudit .= '&tlimit=' . (int)$ticketLimit;
            ?>
            <a class="btn btn-sm <?php echo $directoryView === 'users' ? 'btn-primary' : 'btn-outline-primary'; ?>" href="<?php echo html($baseAudit . '&dir=users'); ?>">Usuarios</a>
            <a class="btn btn-sm <?php echo $directoryView === 'staff' ? 'btn-primary' : 'btn-outline-primary'; ?>" href="<?php echo html($baseAudit . '&dir=staff'); ?>">Staff</a>
            <a class="btn btn-sm <?php echo $directoryView === 'both' ? 'btn-primary' : 'btn-outline-primary'; ?>" href="<?php echo html($baseAudit . '&dir=both'); ?>">Ambos</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <?php if ($directoryView === 'users' || $directoryView === 'both'): ?>
            <?php if ((int)($auditCounts['users'] ?? 0) <= 0): ?>
                <div class="col-12">
                    <div class="card pro-card shadow-sm">
                        <div class="card-body text-center py-5">
                            <div class="mb-2" style="font-size:2rem;opacity:.35"><i class="bi bi-people"></i></div>
                            <div class="fw-semibold">Esta empresa no tiene usuarios registrados</div>
                            <div class="text-muted" style="font-size:.9rem">Puedes cambiar a <strong>Staff</strong> para ver agentes/admins.</div>
                            <div class="mt-3">
                                <a class="btn btn-outline-primary btn-sm" href="<?php echo html($baseAudit . '&dir=staff'); ?>">Ver Staff</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
        <div class="col-12 col-xl-6">
            <div class="card pro-card shadow-sm h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="card-title-sm">Usuarios (clientes)</span>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:.67rem"><?php echo (int)($auditCounts['users'] ?? 0); ?> total</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table pro-table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <?php if ($hasUserActiveCol): ?><th class="text-end">Estado</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($auditUsers)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">Sin usuarios</td></tr>
                                <?php else: ?>
                                    <?php foreach ($auditUsers as $u):
                                        $name = trim((string)($u['firstname'] ?? '') . ' ' . (string)($u['lastname'] ?? ''));
                                        if ($name === '') $name = '—';
                                    ?>
                                        <tr>
                                            <td class="text-muted"><?php echo (int)($u['id'] ?? 0); ?></td>
                                            <td class="fw-semibold"><?php echo html($name); ?></td>
                                            <td><?php echo html((string)($u['email'] ?? '')); ?></td>
                                            <?php if ($hasUserActiveCol): ?>
                                                <td class="text-end">
                                                    <?php if ((int)($u['is_active'] ?? 0) === 1): ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success">Activo</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary bg-opacity-10 text-secondary">Inactivo</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; ?>
        <?php endif; ?>

        <?php if ($directoryView === 'staff' || $directoryView === 'both'): ?>
        <div class="col-12 col-xl-6">
            <div class="card pro-card shadow-sm h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="card-title-sm">Staff / Auditoría</span>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:.67rem"><?php echo (int)($auditCounts['staff_total'] ?? 0); ?> total</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <?php
                            $staffColspan = 3;
                            if ($hasStaffUserCol) $staffColspan++;
                            if ($hasStaffActiveCol) $staffColspan++;
                            $staffColspan++; // ID col (even if hidden on mobile)
                        ?>
                        <table class="table pro-table table-striped table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="d-none d-md-table-cell text-nowrap">ID</th>
                                    <th class="text-nowrap">Staff</th>
                                    <th class="text-nowrap">Rol</th>
                                    <?php if ($hasStaffUserCol): ?><th class="d-none d-xl-table-cell text-nowrap">Usuario</th><?php endif; ?>
                                    <?php if ($hasStaffActiveCol): ?><th class="text-end d-none d-lg-table-cell text-nowrap">Estado</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($auditStaffAdmins) && empty($auditStaffAgents)): ?>
                                    <tr><td colspan="<?php echo (int)$staffColspan; ?>" class="text-center text-muted py-4">Sin staff</td></tr>
                                <?php else: ?>
                                    <?php foreach (array_merge($auditStaffAdmins, $auditStaffAgents) as $s):
                                        $name = trim((string)($s['firstname'] ?? '') . ' ' . (string)($s['lastname'] ?? ''));
                                        if ($name === '') $name = '—';
                                        $role = strtolower((string)($s['role'] ?? 'agent'));
                                        $isAdmin = ($role === 'admin');
                                    ?>
                                        <tr>
                                            <td class="text-muted d-none d-md-table-cell"><?php echo (int)($s['id'] ?? 0); ?></td>
                                            <td>
                                                <div class="fw-semibold"><?php echo html($name); ?></div>
                                                <div class="text-muted" style="font-size:.8rem"><?php echo html((string)($s['email'] ?? '')); ?></div>
                                            </td>
                                            <td>
                                                <?php if ($isAdmin): ?>
                                                    <span class="badge bg-primary bg-opacity-10 text-primary">Admin</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary">Agente</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($hasStaffUserCol): ?><td class="d-none d-xl-table-cell"><?php echo html((string)($s['username'] ?? '')); ?></td><?php endif; ?>
                                            <?php if ($hasStaffActiveCol): ?>
                                                <td class="text-end d-none d-lg-table-cell">
                                                    <?php if ((int)($s['is_active'] ?? 0) === 1): ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success">Activo</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary bg-opacity-10 text-secondary">Inactivo</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <p class="section-title"><i class="bi bi-ticket-detailed"></i> Tickets</p>
    <div class="card pro-card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span class="card-title-sm">Últimos tickets</span>
            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:.67rem">Hasta <?php echo (int)$ticketLimit; ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table pro-table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Número</th>
                            <th>Asunto</th>
                            <?php if ($hasTicketCreatedCol): ?><th>Creado</th><?php endif; ?>
                            <?php if ($hasTicketUpdatedCol): ?><th>Actualizado</th><?php endif; ?>
                            <?php if ($hasTicketClosedCol): ?><th class="text-end">Estado</th><?php endif; ?>
                            <th class="text-end">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($auditRecentTickets)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Sin tickets</td></tr>
                        <?php else: ?>
                            <?php foreach ($auditRecentTickets as $t):
                                $href = '../tickets.php?id=' . (int)($t['id'] ?? 0);
                                $closed = $hasTicketClosedCol ? (string)($t['closed'] ?? '') : '';
                            ?>
                                <tr>
                                    <td class="text-muted"><?php echo (int)($t['id'] ?? 0); ?></td>
                                    <td class="fw-semibold"><?php echo html((string)($t['ticket_number'] ?? '')); ?></td>
                                    <td><?php echo html((string)($t['subject'] ?? '')); ?></td>
                                    <?php if ($hasTicketCreatedCol): ?><td class="text-muted"><?php echo html((string)($t['created'] ?? '')); ?></td><?php endif; ?>
                                    <?php if ($hasTicketUpdatedCol): ?><td class="text-muted"><?php echo html((string)($t['updated'] ?? '')); ?></td><?php endif; ?>
                                    <?php if ($hasTicketClosedCol): ?>
                                        <td class="text-end">
                                            <?php if ($closed === '' || $closed === null): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success">Abierto</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary">Cerrado</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="text-end">
                                        <a class="btn btn-outline-primary btn-sm" href="<?php echo html($href); ?>">Abrir</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="p-3 border-top">
                <form method="get">
                    <input type="hidden" name="id" value="<?php echo (int)$selectedId; ?>">
                    <input type="hidden" name="q" value="<?php echo html($q); ?>">
                    <div class="row g-2">
                        <div class="col-12 col-md-3">
                            <label class="form-label" style="font-size:.8rem">Desde</label>
                            <input type="date" class="form-control" name="t_from" value="<?php echo html($ticketFrom); ?>">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label" style="font-size:.8rem">Hasta</label>
                            <input type="date" class="form-control" name="t_to" value="<?php echo html($ticketTo); ?>">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label" style="font-size:.8rem">Límite</label>
                            <select class="form-select" name="tlimit">
                                <?php foreach ([5,10,30,50,100] as $n): ?>
                                    <option value="<?php echo (int)$n; ?>" <?php echo ((int)$ticketLimit === (int)$n) ? 'selected' : ''; ?>><?php echo (int)$n; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-3 d-flex align-items-end gap-2">
                            <button class="btn btn-outline-primary w-100" type="submit">Aplicar</button>
                            <a class="btn btn-outline-secondary w-100" href="empresas_actividad.php?id=<?php echo (int)$selectedId; ?><?php echo $q !== '' ? ('&q=' . urlencode($q)) : ''; ?>">Limpiar</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php elseif ($selectedId > 0): ?>
    <div class="alert alert-warning">Empresa no encontrada.</div>
<?php endif; ?>

<?php if ($selectedId <= 0): ?>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted" style="font-size:.8rem">Empresas</div>
                <div class="fs-4 fw-bold"><?php echo (int)$totalEmpresas; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted" style="font-size:.8rem">Tickets (total)</div>
                <div class="fs-4 fw-bold"><?php echo (int)$totalTickets; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted" style="font-size:.8rem">Tickets (últimos 30 días)</div>
                <div class="fs-4 fw-bold"><?php echo (int)$totalTickets30d; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted" style="font-size:.8rem">Staff</div>
                <div class="fs-4 fw-bold"><?php echo (int)$totalStaff; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card pro-card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span class="card-title-sm">Actividad por empresa</span>
        <div class="text-muted" style="font-size:.8rem">Mostrando hasta 200 empresas</div>
    </div>
    <div class="card-body p-0">
        <?php if (!$hasEmpresas): ?>
            <div class="alert alert-info m-3 mb-0">No se pudo acceder a la tabla <strong>empresas</strong>.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table pro-table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Empresa</th>
                            <th class="text-end">Tickets</th>
                            <th class="text-end">Abiertos</th>
                            <th class="text-end">Tickets 30d</th>
                            <th class="text-end">Usuarios</th>
                            <th class="text-end">Staff</th>
                            <th class="text-end">Admins</th>
                            <th class="text-end">Agentes</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-5">Sin datos</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td class="text-muted"><?php echo (int)($r['id'] ?? 0); ?></td>
                                    <td class="fw-semibold"><?php echo html((string)($r['nombre'] ?? '')); ?></td>
                                    <td class="text-end"><?php echo (int)($r['total_tickets'] ?? 0); ?></td>
                                    <td class="text-end"><?php echo (int)($r['tickets_abiertos'] ?? 0); ?></td>
                                    <td class="text-end"><?php echo (int)($r['tickets_30d'] ?? 0); ?></td>
                                    <td class="text-end"><?php echo (int)($r['total_users'] ?? 0); ?></td>
                                    <td class="text-end"><?php echo (int)($r['total_staff'] ?? 0); ?></td>
                                    <td class="text-end"><?php echo (int)($r['total_admins'] ?? 0); ?></td>
                                    <td class="text-end"><?php echo (int)($r['total_agents'] ?? 0); ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-outline-primary btn-sm" href="empresas_actividad.php?id=<?php echo (int)($r['id'] ?? 0); ?>">Auditar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<?php
$content = (string)ob_get_clean();
$currentRoute = 'empresas_actividad';
require __DIR__ . '/layout.php';
