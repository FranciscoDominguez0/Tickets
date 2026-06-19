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
<style>
    /* ─── Grid de actividad premium ─────────────────────────── */
    .act-grid {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .act-row {
        display: flex;
        align-items: center;
        gap: 14px;
        background: #fff;
        border: 1px solid rgba(0,0,0,0.05);
        border-radius: 14px;
        padding: 14px 18px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        transition: box-shadow 0.18s, transform 0.18s;
        position: relative;
        overflow: hidden;
    }
    .act-row::before {
        content: '';
        position: absolute;
        left: 0; top: 0; bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, #ef4444, #b91c1c);
        border-radius: 14px 0 0 14px;
        opacity: 0;
        transition: opacity 0.2s;
    }
    .act-row:hover {
        box-shadow: 0 8px 24px rgba(239,68,68,0.10);
        transform: translateY(-2px);
    }
    .act-row:hover::before { opacity: 1; }

    /* Avatar de empresa */
    .act-avatar {
        width: 46px; height: 46px; flex-shrink: 0;
        border-radius: 12px;
        background: linear-gradient(135deg, #ef4444, #7f1d1d);
        color: #fff;
        font-size: 1.1rem;
        font-weight: 800;
        display: flex; align-items: center; justify-content: center;
        letter-spacing: -0.03em;
        text-transform: uppercase;
        box-shadow: 0 4px 10px rgba(239,68,68,0.25);
    }

    /* Info empresa */
    .act-name {
        font-weight: 700;
        font-size: .95rem;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 160px;
    }
    .act-id {
        font-size: .7rem;
        color: #94a3b8;
        font-weight: 600;
        letter-spacing: .04em;
    }

    /* Stats */
    .act-stats {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        flex: 1;
        justify-content: flex-end;
    }
    .act-stat {
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 62px;
    }
    .act-stat-val {
        font-size: 1.15rem;
        font-weight: 800;
        line-height: 1;
        letter-spacing: -0.03em;
        color: #1e293b; /* default: slate dark */
    }
    /* Paleta profesional: rojo para KPI crítico, slate para el resto */
    .act-val-primary { color: #dc2626 !important; }          /* Rojo: tickets totales */
    .act-val-warn    { color: #b91c1c !important; }          /* Rojo oscuro: tickets abiertos */
    .act-val-neutral { color: #334155 !important; }          /* Slate: datos secundarios */
    .act-val-muted   { color: #94a3b8 !important; }          /* Gris: valor en cero */

    body.superadmin-dark .act-stat-val { color: #cbd5e1; }
    body.superadmin-dark .act-val-primary { color: #f87171 !important; }
    body.superadmin-dark .act-val-warn    { color: #fca5a5 !important; }
    body.superadmin-dark .act-val-neutral { color: #94a3b8 !important; }
    body.superadmin-dark .act-val-muted   { color: #475569 !important; }
    .act-stat-lbl {
        font-size: .6rem;
        text-transform: uppercase;
        letter-spacing: .08em;
        font-weight: 700;
        color: #94a3b8;
        margin-top: 3px;
    }
    .act-stat-bar {
        height: 3px;
        border-radius: 2px;
        margin-top: 5px;
        width: 100%;
        min-width: 44px;
    }

    /* Botón auditar */
    .act-audit-btn {
        flex-shrink: 0;
        border-radius: 10px;
        font-size: .78rem;
        font-weight: 700;
        padding: 6px 14px;
        letter-spacing: .04em;
        text-decoration: none;
        background: linear-gradient(135deg, #ef4444, #b91c1c);
        color: #fff;
        border: none;
        box-shadow: 0 2px 8px rgba(239,68,68,0.3);
        display: flex;
        align-items: center;
        gap: 5px;
        transition: box-shadow 0.15s, transform 0.15s;
    }
    .act-audit-btn:hover {
        box-shadow: 0 6px 16px rgba(239,68,68,0.45);
        transform: translateY(-1px);
        color: #fff;
    }

    /* Divider entre grupos de stats */
    .act-divider {
        width: 1px;
        height: 32px;
        background: rgba(0,0,0,0.07);
        flex-shrink: 0;
    }

    /* Header de la grid */
    .act-grid-header {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 4px 18px 8px;
        font-size: .62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .1em;
        color: #94a3b8;
    }
    .act-gh-name { min-width: 200px; flex-shrink: 0; }
    .act-gh-stats { display:flex; gap: 10px; flex: 1; justify-content: flex-end; }
    .act-gh-stat { min-width: 62px; text-align: center; }
    .act-gh-action { min-width: 90px; text-align: center; }

    /* Modo oscuro */
    body.superadmin-dark .act-row {
        background: #000000;
        border-color: rgba(255,255,255,0.06);
        box-shadow: 0 2px 10px rgba(0,0,0,0.3);
    }
    body.superadmin-dark .act-row:hover {
        background: #1e1e21;
        box-shadow: 0 8px 24px rgba(0,0,0,0.5);
    }
    body.superadmin-dark .act-name { color: #f1f5f9; }
    body.superadmin-dark .act-divider { background: rgba(255,255,255,0.08); }
    body.superadmin-dark .act-grid-header { color: #64748b; }

    /* Responsive */
    @media (max-width: 768px) {
        .act-stats { gap: 6px; }
        .act-stat { min-width: 48px; }
        .act-stat-val { font-size: .95rem; }
        .act-divider { display: none; }
        .act-gh-stats { gap: 6px; }
        .act-gh-stat { min-width: 48px; }
    }

    /* Pro-table para otras tablas menores */
    .pro-table {
        border-collapse: separate !important;
        border-spacing: 0 6px !important;
        background: transparent !important;
    }
    .pro-table thead th {
        border-bottom: none !important;
        padding-bottom: 4px !important;
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #94a3b8;
        font-weight: 700;
    }
    .pro-table tbody tr {
        background: #fff;
        box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    }
    .pro-table tbody td {
        border: none !important;
        padding: .85rem 1.1rem !important;
        vertical-align: middle;
    }
    .pro-table tbody tr td:first-child { border-radius: 10px 0 0 10px; }
    .pro-table tbody tr td:last-child  { border-radius: 0 10px 10px 0; }
    .pro-table tbody tr:hover { background: #f8fafc !important; }

    body.superadmin-dark .pro-table tbody tr {
        background: #000000 !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.25);
    }
    body.superadmin-dark .pro-table tbody tr:hover { background: #000000 !important; }
    body.superadmin-dark .pro-table tbody td { color: #e4e4e7 !important; }
    body.superadmin-dark .pro-table thead th { color: #64748b !important; }

    /* ─── Contact list (directorio) ───────────────────────── */
    .dir-list { display:flex; flex-direction:column; gap:6px; }
    .dir-item {
        display:flex; align-items:center; gap:12px;
        padding: 10px 14px;
        border-radius: 12px;
        background: #f8fafc;
        border: 1px solid rgba(0,0,0,0.045);
        transition: background 0.15s, box-shadow 0.15s;
    }
    .dir-item:hover { background:#f1f5f9; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .dir-avatar {
        width:38px; height:38px; flex-shrink:0;
        border-radius:10px;
        background: linear-gradient(135deg,#1e293b,#334155);
        color:#fff; font-size:.8rem; font-weight:800;
        display:flex; align-items:center; justify-content:center;
        letter-spacing:-0.02em; text-transform:uppercase;
    }
    .dir-avatar.is-admin { background: linear-gradient(135deg,#dc2626,#7f1d1d); }
    .dir-info { flex:1; min-width:0; }
    .dir-name {
        font-weight:700; font-size:.875rem; color:#0f172a;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .dir-email { font-size:.7rem; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .dir-badge {
        flex-shrink:0; font-size:.62rem; font-weight:700; letter-spacing:.06em;
        padding: 3px 9px; border-radius:20px; text-transform:uppercase;
    }
    .dir-badge.admin { background:rgba(220,38,38,.1); color:#b91c1c; }
    .dir-badge.agent { background:rgba(51,65,85,.08); color:#475569; }
    .dir-badge.user  { background:rgba(16,185,129,.1); color:#059669; }
    .dir-status {
        flex-shrink:0; width:8px; height:8px; border-radius:50%; background:#94a3b8;
    }
    .dir-status.active { background:#22c55e; box-shadow:0 0 0 2px rgba(34,197,94,.25); }

    body.superadmin-dark .dir-item { background:#1e1e21; border-color:rgba(255,255,255,0.06); }
    body.superadmin-dark .dir-item:hover { background:#000000; }
    body.superadmin-dark .dir-name { color:#f1f5f9; }
    body.superadmin-dark .dir-email { color:#64748b; }
    body.superadmin-dark .dir-badge.agent { background:rgba(100,116,139,.15); color:#94a3b8; }
    body.superadmin-dark .dir-avatar { background:linear-gradient(135deg,#334155,#1e293b); }

    /* ─── Ticket list ──────────────────────────────────────── */
    .tx-list { display:flex; flex-direction:column; gap:6px; }
    .tx-item {
        display:flex; align-items:center; gap:12px;
        padding: 10px 16px; border-radius:12px;
        background:#f8fafc; border:1px solid rgba(0,0,0,0.045);
        transition: background 0.15s, box-shadow 0.15s;
    }
    .tx-item:hover { background:#f1f5f9; box-shadow:0 2px 8px rgba(0,0,0,0.07); }
    .tx-num {
        flex-shrink:0; font-size:.7rem; font-weight:800; letter-spacing:.04em;
        padding:4px 9px; border-radius:8px;
        background:rgba(220,38,38,.08); color:#b91c1c;
        border:1px solid rgba(220,38,38,.15); white-space:nowrap; font-family:monospace;
    }
    .tx-subject {
        flex:1; min-width:0; font-size:.88rem; font-weight:600; color:#0f172a;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .tx-date { flex-shrink:0; font-size:.7rem; color:#94a3b8; font-weight:500; white-space:nowrap; }
    .tx-status {
        flex-shrink:0; font-size:.62rem; font-weight:700; letter-spacing:.06em;
        padding:3px 9px; border-radius:20px; text-transform:uppercase; white-space:nowrap;
    }
    .tx-status.open   { background:rgba(34,197,94,.1); color:#15803d; }
    .tx-status.closed { background:rgba(100,116,139,.1); color:#64748b; }
    .tx-open-btn {
        flex-shrink:0; font-size:.7rem; font-weight:700; letter-spacing:.04em;
        padding:4px 12px; border-radius:8px; text-decoration:none;
        background:linear-gradient(135deg,#dc2626,#b91c1c); color:#fff; border:none;
        box-shadow:0 1px 4px rgba(220,38,38,.3);
        transition:box-shadow .15s, transform .15s; white-space:nowrap;
    }
    .tx-open-btn:hover { box-shadow:0 4px 12px rgba(220,38,38,.4); transform:translateY(-1px); color:#fff; }
    .tx-filter-bar {
        display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end;
        padding: 14px 16px; border-top: 1px solid rgba(0,0,0,0.06);
        background: rgba(248,250,252,0.8); border-radius: 0 0 14px 14px;
    }
    body.superadmin-dark .tx-item { background:#1e1e21; border-color:rgba(255,255,255,0.06); }
    body.superadmin-dark .tx-item:hover { background:#000000; }
    body.superadmin-dark .tx-subject { color:#e2e8f0; }
    body.superadmin-dark .tx-num { background:rgba(220,38,38,.12); color:#f87171; border-color:rgba(220,38,38,.2); }
    body.superadmin-dark .tx-status.open   { background:rgba(34,197,94,.12); color:#4ade80; }
    body.superadmin-dark .tx-status.closed { background:rgba(100,116,139,.12); color:#94a3b8; }
    body.superadmin-dark .tx-filter-bar { border-top-color:rgba(255,255,255,0.06); background:rgba(30,30,33,0.6); }
</style>

<?php if ($selectedId <= 0): ?>
<div class="settings-hero mb-3">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon">
                <i class="bi bi-activity text-white"></i>
            </span>
            <div>
                <h1>Empresas · Actividad de tickets</h1>
                <p>Resumen por empresa: tickets creados, usuarios y personal</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3 py-2" style="color: #fff !important; border-color: rgba(255, 255, 255, 0.2) !important;">
                <i class="bi bi-calendar3 me-1"></i><?php echo date('d M Y'); ?>
            </span>
            <a href="empresas.php" class="btn btn-outline-secondary btn-sm px-3" style="color: #fff !important; border-color: rgba(255, 255, 255, 0.3) !important;">
                <i class="bi bi-buildings me-1"></i>Volver a empresas
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($selectedId > 0 && $empresaDetail): ?>
    <div class="settings-hero mb-3">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div class="d-flex align-items-center gap-3">
                <span class="settings-hero-icon">
                    <i class="bi bi-shield-check text-white"></i>
                </span>
                <div>
                    <h1>Auditoría de empresa</h1>
                    <p><?php echo html((string)($empresaDetail['nombre'] ?? '')); ?> · ID #<?php echo (int)($empresaDetail['id'] ?? 0); ?></p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="empresas_actividad.php" class="btn btn-outline-secondary btn-sm px-3" style="color: #fff !important; border-color: rgba(255, 255, 255, 0.3) !important;">
                    <i class="bi bi-arrow-left-circle me-1"></i>Volver
                </a>
                <a href="empresas.php?id=<?php echo (int)($empresaDetail['id'] ?? 0); ?>" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-buildings me-1"></i>Abrir empresa
                </a>
            </div>
        </div>
    </div>

    <p class="section-title"><i class="bi bi-speedometer2"></i> Resumen</p>
    <div class="row g-3 mb-2">
        <?php
            $kpis = [
                [
                    'label' => 'Usuarios',
                    'value' => (int)($auditCounts['users'] ?? 0),
                    'icon' => 'bi-people',
                    'color' => 'danger',
                ],
                [
                    'label' => 'Admins',
                    'value' => (int)($auditCounts['staff_admins'] ?? 0),
                    'icon' => 'bi-shield-lock',
                    'color' => 'danger',
                ],
                [
                    'label' => 'Agentes',
                    'value' => (int)($auditCounts['staff_agents'] ?? 0),
                    'icon' => 'bi-headset',
                    'color' => 'danger',
                ],
                [
                    'label' => 'Tickets recientes',
                    'value' => (int)count($auditRecentTickets),
                    'icon' => 'bi-ticket-perforated',
                    'color' => 'danger',
                ],
            ];
            foreach ($kpis as $k):
        ?>
        <div class="col-6 col-md-3">
            <div class="card kpi-card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="kpi-icon bg-<?php echo $k['color']; ?> bg-opacity-10 text-<?php echo $k['color']; ?>">
                        <i class="bi <?php echo $k['icon']; ?>"></i>
                    </div>
                    <div>
                        <div class="kpi-label"><?php echo html((string)$k['label']); ?></div>
                        <div class="kpi-number"><?php echo (int)$k['value']; ?></div>
                    </div>
                </div>
                <div class="kpi-bar bg-<?php echo $k['color']; ?>"></div>
            </div>
        </div>
        <?php endforeach; ?>
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

    <p class="section-title"><i class="bi bi-people"></i> Directorio</p>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-2">
        <div class="text-muted" style="font-size:.85rem"><i class="bi bi-info-circle me-1"></i>Selecciona qué directorio cargar para ahorrar recursos.</div>
        <?php
            $baseAudit = 'empresas_actividad.php?id=' . (int)$selectedId;
            if ($q !== '') $baseAudit .= '&q=' . urlencode($q);
            if ($ticketFrom !== '') $baseAudit .= '&t_from=' . urlencode($ticketFrom);
            if ($ticketTo !== '') $baseAudit .= '&t_to=' . urlencode($ticketTo);
            if ($ticketLimit > 0) $baseAudit .= '&tlimit=' . (int)$ticketLimit;
        ?>
        <div class="bg-secondary bg-opacity-10 p-1 rounded-pill d-inline-flex border border-secondary border-opacity-10">
            <a class="btn btn-sm rounded-pill px-3 <?php echo $directoryView === 'users' ? 'btn-primary shadow-sm fw-bold' : 'btn-link text-secondary text-decoration-none'; ?>" href="<?php echo html($baseAudit . '&dir=users'); ?>"><i class="bi bi-people me-1"></i>Usuarios</a>
            <a class="btn btn-sm rounded-pill px-3 <?php echo $directoryView === 'staff' ? 'btn-primary shadow-sm fw-bold' : 'btn-link text-secondary text-decoration-none'; ?>" href="<?php echo html($baseAudit . '&dir=staff'); ?>"><i class="bi bi-person-badge me-1"></i>Staff</a>
            <a class="btn btn-sm rounded-pill px-3 <?php echo $directoryView === 'both' ? 'btn-primary shadow-sm fw-bold' : 'btn-link text-secondary text-decoration-none'; ?>" href="<?php echo html($baseAudit . '&dir=both'); ?>"><i class="bi bi-ui-radios-grid me-1"></i>Ambos</a>
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
                    <span class="card-title-sm"><i class="bi bi-people me-1"></i>Usuarios (clientes)</span>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:.67rem"><?php echo (int)($auditCounts['users'] ?? 0); ?> total</span>
                </div>
                <div class="card-body">
                    <?php if (empty($auditUsers)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-person-x fs-2 d-block mb-2 opacity-25"></i>Sin usuarios en este filtro
                        </div>
                    <?php else: ?>
                    <div class="dir-list">
                    <?php foreach ($auditUsers as $u):
                        $name = trim((string)($u['firstname'] ?? '') . ' ' . (string)($u['lastname'] ?? ''));
                        if ($name === '') $name = (string)($u['email'] ?? '—');
                        $initU = mb_strtoupper(mb_substr($name, 0, 1));
                        $partsU = explode(' ', trim($name));
                        if (count($partsU) > 1) $initU .= mb_strtoupper(mb_substr($partsU[1], 0, 1));
                        $isActiveU = !$hasUserActiveCol || (int)($u['is_active'] ?? 1) === 1;
                    ?>
                        <div class="dir-item">
                            <div class="dir-avatar"><?php echo html($initU ?: '?'); ?></div>
                            <div class="dir-info">
                                <div class="dir-name"><?php echo html($name); ?></div>
                                <div class="dir-email"><?php echo html((string)($u['email'] ?? '')); ?></div>
                            </div>
                            <span class="dir-badge user">Cliente</span>
                            <?php if ($hasUserActiveCol): ?>
                                <span class="dir-status <?php echo $isActiveU ? 'active' : ''; ?>" title="<?php echo $isActiveU ? 'Activo' : 'Inactivo'; ?>"></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php endif; ?>
        <?php endif; ?>

        <?php if ($directoryView === 'staff' || $directoryView === 'both'): ?>
        <div class="col-12 col-xl-6">
            <div class="card pro-card shadow-sm h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="card-title-sm"><i class="bi bi-person-badge me-1"></i>Staff / Auditoría</span>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:.67rem"><?php echo (int)($auditCounts['staff_total'] ?? 0); ?> total</span>
                </div>
                <div class="card-body">
                    <?php if (empty($auditStaffAdmins) && empty($auditStaffAgents)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-person-badge fs-2 d-block mb-2 opacity-25"></i>Sin staff registrado
                        </div>
                    <?php else: ?>
                    <div class="dir-list">
                    <?php foreach (array_merge($auditStaffAdmins, $auditStaffAgents) as $s):
                        $name = trim((string)($s['firstname'] ?? '') . ' ' . (string)($s['lastname'] ?? ''));
                        if ($name === '') $name = (string)($s['email'] ?? '—');
                        $role = strtolower((string)($s['role'] ?? 'agent'));
                        $isAdmin = ($role === 'admin');
                        $initS = mb_strtoupper(mb_substr($name, 0, 1));
                        $partsS = explode(' ', trim($name));
                        if (count($partsS) > 1) $initS .= mb_strtoupper(mb_substr($partsS[1], 0, 1));
                        $isActiveS = !$hasStaffActiveCol || (int)($s['is_active'] ?? 1) === 1;
                    ?>
                        <div class="dir-item">
                            <div class="dir-avatar <?php echo $isAdmin ? 'is-admin' : ''; ?>"><?php echo html($initS ?: '?'); ?></div>
                            <div class="dir-info">
                                <div class="dir-name"><?php echo html($name); ?></div>
                                <div class="dir-email"><?php echo html((string)($s['email'] ?? '')); ?></div>
                            </div>
                            <span class="dir-badge <?php echo $isAdmin ? 'admin' : 'agent'; ?>">
                                <?php echo $isAdmin ? 'Admin' : 'Agente'; ?>
                            </span>
                            <?php if ($hasStaffActiveCol): ?>
                                <span class="dir-status <?php echo $isActiveS ? 'active' : ''; ?>" title="<?php echo $isActiveS ? 'Activo' : 'Inactivo'; ?>"></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <p class="section-title"><i class="bi bi-ticket-detailed"></i> Tickets</p>
    <div class="card pro-card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span class="card-title-sm"><i class="bi bi-ticket-perforated me-1"></i>Últimos tickets</span>
            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:.67rem">Hasta <?php echo (int)$ticketLimit; ?></span>
        </div>
        <div class="card-body">
            <?php if (empty($auditRecentTickets)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-ticket fs-2 d-block mb-2 opacity-25"></i>Sin tickets en este período
                </div>
            <?php else: ?>
            <div class="tx-list">
            <?php foreach ($auditRecentTickets as $t):
                $href   = '../tickets.php?id=' . (int)($t['id'] ?? 0);
                $closed = $hasTicketClosedCol ? (string)($t['closed'] ?? '') : '';
                $isOpen = ($closed === '' || $closed === null);
                $txNum  = (string)($t['ticket_number'] ?? '#' . (int)($t['id'] ?? 0));
                $subject = (string)($t['subject'] ?? 'Sin asunto');
                $created = $hasTicketCreatedCol ? (string)($t['created'] ?? '') : '';
                /* Format date nicely if possible */
                if ($created !== '') {
                    $ts = strtotime($created);
                    $created = $ts ? date('d M Y', $ts) : $created;
                }
            ?>
                <div class="tx-item">
                    <span class="tx-num"><?php echo html($txNum); ?></span>
                    <span class="tx-subject" title="<?php echo html($subject); ?>"><?php echo html($subject); ?></span>
                    <?php if ($created !== ''): ?>
                        <span class="tx-date"><i class="bi bi-clock me-1"></i><?php echo html($created); ?></span>
                    <?php endif; ?>
                    <?php if ($hasTicketClosedCol): ?>
                        <span class="tx-status <?php echo $isOpen ? 'open' : 'closed'; ?>">
                            <?php echo $isOpen ? 'Abierto' : 'Cerrado'; ?>
                        </span>
                    <?php endif; ?>
                    <a class="tx-open-btn" href="<?php echo html($href); ?>">
                        <i class="bi bi-arrow-up-right-square"></i>
                    </a>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <!-- Filtro de tickets -->
        <div class="tx-filter-bar">
            <form method="get" class="d-flex gap-2 flex-wrap align-items-end w-100">
                <input type="hidden" name="id" value="<?php echo (int)$selectedId; ?>">
                <input type="hidden" name="q" value="<?php echo html($q); ?>">
                <div>
                    <label class="form-label mb-1" style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em">Desde</label>
                    <input type="date" class="form-control form-control-sm" name="t_from" value="<?php echo html($ticketFrom); ?>">
                </div>
                <div>
                    <label class="form-label mb-1" style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em">Hasta</label>
                    <input type="date" class="form-control form-control-sm" name="t_to" value="<?php echo html($ticketTo); ?>">
                </div>
                <div>
                    <label class="form-label mb-1" style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em">Límite</label>
                    <select class="form-select form-select-sm" name="tlimit" style="min-width:80px">
                        <?php foreach ([5,10,30,50,100] as $n): ?>
                            <option value="<?php echo (int)$n; ?>" <?php echo ((int)$ticketLimit === (int)$n) ? 'selected' : ''; ?>><?php echo (int)$n; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex gap-2 ms-auto">
                    <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                    <a class="btn btn-sm btn-outline-secondary" href="empresas_actividad.php?id=<?php echo (int)$selectedId; ?><?php echo $q !== '' ? ('&q=' . urlencode($q)) : ''; ?>"><i class="bi bi-x-circle me-1"></i>Limpiar</a>
                </div>
            </form>
        </div>
    </div>
<?php elseif ($selectedId > 0): ?>
    <div class="alert alert-warning">Empresa no encontrada.</div>
<?php endif; ?>

<?php if ($selectedId <= 0): ?>
<div class="row g-3 mb-3">
    <?php
        $kpisGlobal = [
            [
                'label' => 'Empresas',
                'value' => (int)$totalEmpresas,
                'icon' => 'bi-buildings',
                'color' => 'danger',
            ],
            [
                'label' => 'Tickets (total)',
                'value' => (int)$totalTickets,
                'icon' => 'bi-ticket',
                'color' => 'danger',
            ],
            [
                'label' => 'Tickets (últimos 30 días)',
                'value' => (int)$totalTickets30d,
                'icon' => 'bi-calendar2-week',
                'color' => 'danger',
            ],
            [
                'label' => 'Staff',
                'value' => (int)$totalStaff,
                'icon' => 'bi-person-badge',
                'color' => 'danger',
            ],
        ];
        foreach ($kpisGlobal as $k):
    ?>
    <div class="col-6 col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="kpi-icon bg-<?php echo $k['color']; ?> bg-opacity-10 text-<?php echo $k['color']; ?>">
                    <i class="bi <?php echo $k['icon']; ?>"></i>
                </div>
                <div>
                    <div class="kpi-label"><?php echo html((string)$k['label']); ?></div>
                    <div class="kpi-number"><?php echo (int)$k['value']; ?></div>
                </div>
            </div>
            <div class="kpi-bar bg-<?php echo $k['color']; ?>"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card pro-card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span class="card-title-sm"><i class="bi bi-grid-3x3-gap me-1"></i>Actividad por empresa</span>
        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:.67rem">
            <?php echo count($rows); ?> empresas
        </span>
    </div>
    <div class="card-body p-3">
        <?php if (!$hasEmpresas): ?>
            <div class="alert alert-info mb-0">No se pudo acceder a la tabla <strong>empresas</strong>.</div>
        <?php elseif (empty($rows)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-buildings fs-1 d-block mb-2 opacity-25"></i>
                Sin datos de actividad
            </div>
        <?php else:
            /* Calcular máximo de tickets para la barra de progreso */
            $maxTickets = max(1, max(array_column($rows, 'total_tickets') ?: [1]));
        ?>
        <!-- Header de columnas -->
        <div class="act-grid-header d-none d-md-flex">
            <div class="act-gh-name">Empresa</div>
            <div class="act-gh-stats">
                <div class="act-gh-stat">Tickets</div>
                <div class="act-gh-stat">Abiertos</div>
                <div class="act-gh-stat">Últ. 30d</div>
                <div class="act-gh-stat">Usuarios</div>
                <div class="act-gh-stat">Staff</div>
                <div class="act-gh-stat">Admins</div>
                <div class="act-gh-stat">Agentes</div>
            </div>
            <div class="act-gh-action">Acción</div>
        </div>
        <div class="act-grid">
        <?php foreach ($rows as $r):
            $nombre = (string)($r['nombre'] ?? '');
            $initials = mb_strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9 ]/u', '', $nombre), 0, 1));
            $secondWord = explode(' ', trim($nombre));
            if (count($secondWord) > 1) $initials .= mb_strtoupper(mb_substr($secondWord[1], 0, 1));
            $totalTx = (int)($r['total_tickets'] ?? 0);
            $openTx  = (int)($r['tickets_abiertos'] ?? 0);
            $tx30    = (int)($r['tickets_30d'] ?? 0);
            $users   = (int)($r['total_users'] ?? 0);
            $staff   = (int)($r['total_staff'] ?? 0);
            $admins  = (int)($r['total_admins'] ?? 0);
            $agents  = (int)($r['total_agents'] ?? 0);
            $barPct  = $maxTickets > 0 ? round(($totalTx / $maxTickets) * 100) : 0;
            $openPct = $totalTx > 0 ? round(($openTx / $totalTx) * 100) : 0;
        ?>
        <div class="act-row">
            <!-- Avatar -->
            <div class="act-avatar"><?php echo html($initials ?: '#'); ?></div>

            <!-- Nombre -->
            <div style="min-width:130px; max-width:170px;">
                <div class="act-name" title="<?php echo html($nombre); ?>"><?php echo html($nombre); ?></div>
                <div class="act-id">ID #<?php echo (int)($r['id'] ?? 0); ?></div>
            </div>

            <div class="act-divider d-none d-md-block"></div>

            <!-- Stats -->
            <div class="act-stats">
                <!-- Tickets totales: color rojo (KPI principal) -->
                <div class="act-stat">
                    <span class="act-stat-val act-val-primary"><?php echo number_format($totalTx); ?></span>
                    <span class="act-stat-lbl">Tickets</span>
                    <div class="act-stat-bar" style="background: linear-gradient(90deg, #ef4444 <?php echo $barPct; ?>%, rgba(239,68,68,.12) <?php echo $barPct; ?>%);"></div>
                </div>
                <!-- Abiertos: rojo suave si hay, gris si cero -->
                <div class="act-stat">
                    <span class="act-stat-val <?php echo $openTx > 0 ? 'act-val-warn' : 'act-val-muted'; ?>"><?php echo number_format($openTx); ?></span>
                    <span class="act-stat-lbl">Abiertos</span>
                    <div class="act-stat-bar" style="background: linear-gradient(90deg, rgba(239,68,68,.55) <?php echo $openPct; ?>%, rgba(239,68,68,.08) <?php echo $openPct; ?>%);"></div>
                </div>
                <!-- Últimos 30d: slate neutro -->
                <div class="act-stat">
                    <span class="act-stat-val act-val-neutral"><?php echo number_format($tx30); ?></span>
                    <span class="act-stat-lbl">Últ. 30d</span>
                    <div class="act-stat-bar" style="background: rgba(100,116,139,.18);"></div>
                </div>

                <div class="act-divider d-none d-md-block"></div>

                <!-- Usuarios: slate neutro -->
                <div class="act-stat">
                    <span class="act-stat-val act-val-neutral"><?php echo number_format($users); ?></span>
                    <span class="act-stat-lbl">Usuarios</span>
                    <div class="act-stat-bar" style="background: rgba(100,116,139,.18);"></div>
                </div>
                <!-- Staff: slate neutro -->
                <div class="act-stat">
                    <span class="act-stat-val act-val-neutral"><?php echo number_format($staff); ?></span>
                    <span class="act-stat-lbl">Staff</span>
                    <div class="act-stat-bar" style="background: rgba(100,116,139,.18);"></div>
                </div>
                <!-- Admins: slate neutro -->
                <div class="act-stat">
                    <span class="act-stat-val act-val-neutral"><?php echo number_format($admins); ?></span>
                    <span class="act-stat-lbl">Admins</span>
                    <div class="act-stat-bar" style="background: rgba(100,116,139,.18);"></div>
                </div>
                <!-- Agentes: slate neutro -->
                <div class="act-stat">
                    <span class="act-stat-val act-val-neutral"><?php echo number_format($agents); ?></span>
                    <span class="act-stat-lbl">Agentes</span>
                    <div class="act-stat-bar" style="background: rgba(100,116,139,.18);"></div>
                </div>
            </div>

            <!-- Acción -->
            <a class="act-audit-btn" href="empresas_actividad.php?id=<?php echo (int)($r['id'] ?? 0); ?>">
                <i class="bi bi-shield-check"></i>
                <span class="d-none d-md-inline">Auditar</span>
            </a>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<?php
$content = (string)ob_get_clean();
$currentRoute = 'empresas_actividad';
require __DIR__ . '/layout.php';
