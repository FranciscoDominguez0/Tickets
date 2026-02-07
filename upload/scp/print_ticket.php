<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');

$tid = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;
if ($tid <= 0) {
    http_response_code(400);
    exit('Ticket inválido');
}

// Cargar ticket con usuario, estado, prioridad, departamento, asignado
$stmt = $mysqli->prepare(
    "SELECT t.*, u.firstname AS user_first, u.lastname AS user_last, u.email AS user_email,
     s.firstname AS staff_first, s.lastname AS staff_last, s.email AS staff_email,
     d.name AS dept_name, ts.name AS status_name,
     p.name AS priority_name
     FROM tickets t
     JOIN users u ON t.user_id = u.id
     LEFT JOIN staff s ON t.staff_id = s.id
     JOIN departments d ON t.dept_id = d.id
     JOIN ticket_status ts ON t.status_id = ts.id
     JOIN priorities p ON t.priority_id = p.id
     WHERE t.id = ? LIMIT 1"
);
$stmt->bind_param('i', $tid);
$stmt->execute();
$t = $stmt->get_result()->fetch_assoc();
if (!$t) {
    http_response_code(404);
    exit('Ticket no encontrado');
}

// Thread
$stmt = $mysqli->prepare("SELECT id FROM threads WHERE ticket_id = ? LIMIT 1");
$stmt->bind_param('i', $tid);
$stmt->execute();
$threadRow = $stmt->get_result()->fetch_assoc();
$thread_id = (int) ($threadRow['id'] ?? 0);

$entries = [];
if ($thread_id > 0) {
    $stmt = $mysqli->prepare(
        "SELECT te.id, te.user_id, te.staff_id, te.body, te.is_internal, te.created,
         u.firstname AS user_first, u.lastname AS user_last,
         s.firstname AS staff_first, s.lastname AS staff_last
         FROM thread_entries te
         LEFT JOIN users u ON te.user_id = u.id
         LEFT JOIN staff s ON te.staff_id = s.id
         WHERE te.thread_id = ?
         ORDER BY te.created ASC"
    );
    $stmt->bind_param('i', $thread_id);
    $stmt->execute();
    $entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// App settings para encabezado
$companyName = trim((string)getAppSetting('company.name', ''));
if ($companyName === '') $companyName = (string)APP_NAME;
$companyWebsite = trim((string)getAppSetting('company.website', ''));
if ($companyWebsite === '') $companyWebsite = (string)APP_URL;
$logoUrl = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png');

$userName = trim((string)($t['user_first'] ?? '') . ' ' . (string)($t['user_last'] ?? ''));
if ($userName === '') $userName = (string)($t['user_email'] ?? '');

$staffName = trim((string)($t['staff_first'] ?? '') . ' ' . (string)($t['staff_last'] ?? ''));
if ($staffName === '') $staffName = '— Sin asignar —';

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Ticket <?php echo html((string)($t['ticket_number'] ?? ('#' . $tid))); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root{
            --ink:#0f172a;
            --muted:#64748b;
            --line:#e2e8f0;
            --paper:#ffffff;
            --soft:#f8fafc;
            --brand:#2563eb;
        }
        html,body{background:var(--paper); color:var(--ink); font-family: "Lato", "Segoe UI", Arial, sans-serif; font-size:14px; margin:0; padding:0;}
        .sheet{max-width: 920px; margin: 22px auto; padding: 0 18px;}
        .topbar{display:flex; align-items:center; justify-content:space-between; gap:16px; padding: 14px 0 12px; border-bottom:2px solid var(--line);}
        .brand{display:flex; align-items:center; gap:12px; min-width: 0;}
        .logo{width:64px; height:64px; border:1px solid var(--line); border-radius:10px; display:flex; align-items:center; justify-content:center; overflow:hidden; background:#fff;}
        .logo img{max-width:100%; max-height:100%; padding:10px; object-fit:contain;}
        .brand h1{font-size:16px; margin:0; font-weight:900; line-height:1.1;}
        .brand .web{color:var(--muted); font-weight:600; margin-top:2px;}
        .meta{ text-align:right; }
        .meta .no{font-weight:900; font-size:15px;}
        .meta .sub{color:var(--muted); font-weight:600; margin-top:3px;}

        .summary{margin-top: 14px; background: var(--soft); border:1px solid var(--line); border-radius:12px; padding: 12px 14px;}
        .summary-grid{display:grid; grid-template-columns: 1fr 1fr; gap: 10px 16px;}
        .kv{display:flex; gap: 8px;}
        .kv .k{min-width: 120px; color:var(--muted); font-weight:800; text-transform:uppercase; letter-spacing:.06em; font-size: 11px;}
        .kv .v{font-weight:700; color:var(--ink);}

        .thread{margin-top: 14px;}
        .entry{border:1px solid var(--line); border-radius:12px; padding: 12px 14px; margin-bottom: 10px;}
        .entry.internal{border-color:#fbbf24; background:#fffbeb;}
        .entry.staff{border-color:#fed7aa; background:#fff7ed;}
        .entry.user{border-color:#bfdbfe; background:#eff6ff;}
        .entry-head{display:flex; align-items:flex-start; justify-content:space-between; gap: 10px; margin-bottom: 8px;}
        .who{font-weight:900;}
        .when{color:var(--muted); font-weight:700; font-size: 12px; white-space:nowrap;}
        .body{white-space:pre-wrap; word-break:break-word; line-height:1.35;}
        .tag{display:inline-flex; align-items:center; gap:6px; font-weight:900; font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#92400e; margin-left:10px;}

        .footer{margin-top: 10px; color: var(--muted); font-weight:700; font-size: 12px; text-align:center;}

        @media print{
            .sheet{max-width:none; margin:0; padding:0;}
            .entry{page-break-inside: avoid;}
            .summary{break-inside: avoid;}
        }
    </style>
</head>
<body>
<div class="sheet">
    <div class="topbar">
        <div class="brand">
            <?php if ($logoUrl !== ''): ?>
                <div class="logo"><img src="<?php echo html($logoUrl); ?>" alt="<?php echo html($companyName); ?>"></div>
            <?php endif; ?>
            <div style="min-width:0;">
                <h1><?php echo html($companyName); ?></h1>
                <div class="web"><?php echo html($companyWebsite); ?></div>
            </div>
        </div>
        <div class="meta">
            <div class="no">Ticket <?php echo html((string)($t['ticket_number'] ?? ('#' . $tid))); ?></div>
            <div class="sub"><?php echo html((string)($t['subject'] ?? '')); ?></div>
            <div class="sub">Impreso: <?php echo date('d/m/Y H:i'); ?></div>
        </div>
    </div>

    <div class="summary">
        <div class="summary-grid">
            <div class="kv"><div class="k">Cliente</div><div class="v"><?php echo html($userName); ?> (<?php echo html((string)($t['user_email'] ?? '')); ?>)</div></div>
            <div class="kv"><div class="k">Departamento</div><div class="v"><?php echo html((string)($t['dept_name'] ?? '')); ?></div></div>
            <div class="kv"><div class="k">Estado</div><div class="v"><?php echo html((string)($t['status_name'] ?? '')); ?></div></div>
            <div class="kv"><div class="k">Prioridad</div><div class="v"><?php echo html((string)($t['priority_name'] ?? '')); ?></div></div>
            <div class="kv"><div class="k">Asignado</div><div class="v"><?php echo html($staffName); ?></div></div>
            <div class="kv"><div class="k">Creado</div><div class="v"><?php echo !empty($t['created']) ? html(date('d/m/Y H:i', strtotime((string)$t['created']))) : '—'; ?></div></div>
        </div>
    </div>

    <div class="thread">
        <?php if (empty($entries)): ?>
            <div class="entry"><div class="who">Sin mensajes</div><div class="body">Aún no hay mensajes en el hilo.</div></div>
        <?php else: ?>
            <?php foreach ($entries as $e): ?>
                <?php
                    $isInternal = (int)($e['is_internal'] ?? 0) === 1;
                    $isStaff = !empty($e['staff_id']);
                    $author = $isStaff
                        ? (trim((string)($e['staff_first'] ?? '') . ' ' . (string)($e['staff_last'] ?? '')) ?: 'Agente')
                        : (trim((string)($e['user_first'] ?? '') . ' ' . (string)($e['user_last'] ?? '')) ?: 'Usuario');
                    $cls = $isInternal ? 'internal' : ($isStaff ? 'staff' : 'user');
                ?>
                <div class="entry <?php echo html($cls); ?>">
                    <div class="entry-head">
                        <div>
                            <span class="who"><?php echo html($author); ?></span>
                            <?php if ($isInternal): ?>
                                <span class="tag"><i class="bi bi-shield-lock"></i> Nota interna</span>
                            <?php endif; ?>
                        </div>
                        <div class="when"><?php echo !empty($e['created']) ? html(date('d/m/Y H:i', strtotime((string)$e['created']))) : ''; ?></div>
                    </div>
                    <div class="body"><?php
                        $b = (string)($e['body'] ?? '');
                        if (strpos($b, '<') !== false) {
                            echo strip_tags($b, '<p><br><strong><em><b><i><u><s><ul><ol><li><a><span>');
                        } else {
                            echo nl2br(html($b));
                        }
                    ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="footer">
        <?php echo html($companyName); ?> · <?php echo html($companyWebsite); ?>
    </div>
</div>

<script>
    // Auto print like osTicket-style print view
    window.addEventListener('load', function () {
        setTimeout(function () {
            try { window.print(); } catch (e) {}
        }, 300);
    });
</script>
</body>
</html>
