<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';

if (!defined('TICKET_PDF_RENDER')) {
    if (!isset($_SESSION['staff_id'])) {
        header('Location: login.php');
        exit;
    }
    requireLogin('agente');
}

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

$ticketClientSignatureUrl = '';
$ticketClientSignaturePath = trim((string)($t['client_signature'] ?? ''));
if ($ticketClientSignaturePath !== '') {
    $projectRoot = realpath(dirname(__DIR__, 2));
    $sigPath = ltrim(str_replace('\\', '/', $ticketClientSignaturePath), '/');
    if ($projectRoot !== false && str_starts_with($sigPath, 'firmas/')) {
        $fullSigPath = $projectRoot . '/' . $sigPath;
        if (is_file($fullSigPath)) {
            $ticketClientSignatureUrl = toAppAbsoluteUrl($sigPath) . '?v=' . (string)@filemtime($fullSigPath);
        }
    }
}

if (defined('TICKET_PDF_RENDER')) {
    $projectRoot = realpath(dirname(__DIR__, 2));
    if ($projectRoot !== false) {
        $logoMode = (string)getAppSetting('company.logo_mode', '');
        $logoSetting = (string)getAppSetting('company.logo', '');
        $logoRel = 'publico/img/vigitec-logo.webp';
        if ($logoMode === '') {
            $logoMode = $logoSetting !== '' ? 'custom' : 'default';
        }
        if ($logoMode === 'custom' && $logoSetting !== '') {
            $candidate = ltrim(str_replace('\\', '/', (string)$logoSetting), '/');
            if ($candidate !== '' && is_file($projectRoot . '/' . $candidate)) {
                $logoRel = $candidate;
            }
        }
        if (!is_file($projectRoot . '/' . ltrim($logoRel, '/')) && is_file($projectRoot . '/publico/img/vigitec-logo.png')) {
            $logoRel = 'publico/img/vigitec-logo.png';
        }
        
        $logoAbsPath = $projectRoot . '/' . ltrim($logoRel, '/');
        if (is_file($logoAbsPath)) {
            $ext = strtolower(pathinfo($logoAbsPath, PATHINFO_EXTENSION));
            $imageData = file_get_contents($logoAbsPath);
            if ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
                $im = @imagecreatefromwebp($logoAbsPath);
                if ($im !== false) {
                    ob_start();
                    imagepng($im);
                    $imageData = (string)ob_get_clean();
                    unset($im);
                    $ext = 'png';
                }
            } elseif ($ext === 'jpg') {
                $ext = 'jpeg';
            }
            $base64 = base64_encode($imageData);
            $logoUrl = 'data:image/' . $ext . ';base64,' . $base64;
        }

        if ($ticketClientSignaturePath !== '') {
            $sigPath = ltrim(str_replace('\\', '/', $ticketClientSignaturePath), '/');
            $sigAbsPath = $projectRoot . '/' . ltrim($sigPath, '/');
            if ($sigPath !== '' && is_file($sigAbsPath)) {
                $ext = strtolower(pathinfo($sigAbsPath, PATHINFO_EXTENSION));
                $imageData = file_get_contents($sigAbsPath);
                if ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
                    $im = @imagecreatefromwebp($sigAbsPath);
                    if ($im !== false) {
                        ob_start();
                        imagepng($im);
                        $imageData = (string)ob_get_clean();
                        unset($im);
                        $ext = 'png';
                    }
                } elseif ($ext === 'jpg') {
                    $ext = 'jpeg';
                }
                $base64 = base64_encode($imageData);
                $ticketClientSignatureUrl = 'data:image/' . $ext . ';base64,' . $base64;
            }
        }
    }
}

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Ticket <?php echo html((string)($t['ticket_number'] ?? ('#' . $tid))); ?></title>
    <?php if (!defined('TICKET_PDF_RENDER')): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php endif; ?>
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
        .logo{text-align:left;}
        .logo img{max-height:60px; max-width:220px; display:block;}
        .brand h1{font-size:16px; margin:0; font-weight:900; line-height:1.1;}
        .brand .web{color:var(--muted); font-weight:600; margin-top:2px;}
        .meta{ text-align:right; }
        .meta .no{font-weight:900; font-size:15px;}
        .meta .sub{color:var(--muted); font-weight:600; margin-top:3px;}

        .summary{margin-top: 14px; background: var(--soft); border:1px solid var(--line); border-radius:12px; padding: 12px 14px;}
        .summary-table{width: 100%; border-collapse: collapse;}
        .summary-table td{padding: 5px 8px; vertical-align: top;}
        .kv .k{color:var(--muted); font-weight:800; text-transform:uppercase; letter-spacing:.06em; font-size: 11px; display:inline-block; width:110px;}
        .kv .v{font-weight:700; color:var(--ink); display:inline-block;}

        .thread{margin-top: 14px;}
        .entry{border:1px solid var(--line); border-radius:12px; padding: 12px 14px; margin-bottom: 10px;}
        .entry.internal{border-color:#fbbf24; background:#fffbeb;}
        .entry.staff{border-color:#fed7aa; background:#fff7ed;}
        .entry.user{border-color:#bfdbfe; background:#eff6ff;}
        .entry-head{border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 6px; margin-bottom: 8px;}
        .entry-head-table{width:100%;}
        .who{font-weight:900;}
        .when{color:var(--muted); font-weight:700; font-size: 12px; text-align:right;}
        .body{white-space:pre-wrap; word-break:break-word; line-height:1.35;}
        .body img{max-width:100%; height:auto; display:block;}
        .body iframe{max-width:100%; width:100%;}
        .tag{font-weight:900; font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#92400e; margin-left:10px;}

        .footer{margin-top: 10px; color: var(--muted); font-weight:700; font-size: 12px; text-align:center;}
        .closed-note{margin-top:12px; border:1px solid var(--line); border-radius:12px; background:#f8fafc; padding:10px 12px;}
        .closed-note .k{font-weight:900; font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); margin-bottom:4px;}
        .closed-note .v{white-space:pre-wrap; word-break:break-word;}
        .sig-box{margin-top:30px; margin-left:auto; width: 280px;}
        .sig-title{font-size:11px; text-transform:uppercase; letter-spacing:.05em; font-weight:800; color:var(--muted); text-align:center; border-bottom:1px solid var(--line); padding-bottom:6px; margin-bottom:8px;}
        .sig-body{text-align:center; padding: 4px;}
        .sig-img{display:inline-block; max-width:100%; max-height:120px; width:auto; height:auto; filter: contrast(1.1) grayscale(0.5);}

        @media print{
            .sheet{max-width:none; margin:0; padding:0;}
            .entry{page-break-inside: avoid;}
            .summary{page-break-inside: avoid;}
            .closed-note, .sig-box{page-break-inside: avoid;}
        }
    </style>
</head>
<body>
<div class="sheet">
    <table style="width:100%; border-bottom:2px solid #cbd5e1; padding-bottom: 24px; margin-bottom:20px;">
        <tr>
            <td style="vertical-align:bottom; width:60%;">
                <div style="text-align:left;">
                    <?php if ($logoUrl !== ''): ?>
                        <div class="logo" style="margin-bottom:14px;"><img src="<?php echo html($logoUrl); ?>" alt="<?php echo html($companyName); ?>"></div>
                    <?php endif; ?>
                    <h1 style="font-size:13px; margin:0; font-weight:900; text-transform:uppercase; letter-spacing:0.06em; color:#0f172a;"><?php echo html($companyName); ?></h1>
                    <div style="color:#2563eb; font-weight:700; margin-top:4px; font-size:11px; letter-spacing:0.02em;"><?php echo html(str_replace(['http://', 'https://'], '', $companyWebsite)); ?></div>
                </div>
            </td>
            <td style="vertical-align:bottom; text-align:right; width:40%;">
                <div style="font-size:10px; text-transform:uppercase; font-weight:800; color:#64748b; letter-spacing:0.08em; margin-bottom:6px;">Documento de Ticket</div>
                <div style="font-size:28px; font-weight:900; color:#0f172a; line-height:1; letter-spacing:-0.02em; margin-bottom:10px;"><?php echo html((string)($t['ticket_number'] ?? ('#' . $tid))); ?></div>
                <div style="font-size:13px; font-weight:800; color:#334155; margin-bottom:6px;"><?php echo html((string)($t['subject'] ?? '')); ?></div>
                <div style="font-size:11px; color:#64748b; font-weight:700;">Emitido: <?php 
                    $originalTz = date_default_timezone_get();
                    date_default_timezone_set('America/Panama');
                    echo date('d M Y - h:i A'); 
                    date_default_timezone_set($originalTz);
                ?></div>
            </td>
        </tr>
    </table>

    <div class="summary">
        <table class="summary-table">
            <tr>
                <td class="kv"><span class="k">Cliente</span><span class="v"><?php echo html($userName); ?> (<?php echo html((string)($t['user_email'] ?? '')); ?>)</span></td>
                <td class="kv"><span class="k">Departamento</span><span class="v"><?php echo html((string)($t['dept_name'] ?? '')); ?></span></td>
            </tr>
            <tr>
                <td class="kv"><span class="k">Estado</span><span class="v"><?php echo html((string)($t['status_name'] ?? '')); ?></span></td>
                <td class="kv"><span class="k">Prioridad</span><span class="v"><?php echo html((string)($t['priority_name'] ?? '')); ?></span></td>
            </tr>
            <tr>
                <td class="kv"><span class="k">Asignado</span><span class="v"><?php echo html($staffName); ?></span></td>
                <td class="kv"><span class="k">Creado</span><span class="v"><?php echo !empty($t['created']) ? html(date('d/m/Y H:i', strtotime((string)$t['created']))) : '—'; ?></span></td>
            </tr>
            <tr>
                <td class="kv"><span class="k">Cerrado</span><span class="v"><?php echo !empty($t['closed']) ? html(date('d/m/Y H:i', strtotime((string)$t['closed']))) : '—'; ?></span></td>
                <td></td>
            </tr>
        </table>
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
                        <table class="entry-head-table" style="border-collapse:collapse; width:100%;">
                            <tr>
                                <td style="vertical-align:middle;">
                                    <span class="who"><?php echo html($author); ?></span>
                                    <?php if ($isInternal): ?>
                                        <span class="tag"><i class="bi bi-shield-lock"></i> Nota interna</span>
                                    <?php endif; ?>
                                </td>
                                <td class="when" style="vertical-align:middle; width:150px; text-align:right;">
                                    <?php echo !empty($e['created']) ? html(date('d/m/Y H:i', strtotime((string)$e['created']))) : ''; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="body"><?php
                        echo sanitizeRichText((string)($e['body'] ?? ''));
                    ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($t['close_message'])): ?>
        <div class="closed-note">
            <div class="k">Motivo de cierre</div>
            <div class="v"><?php echo nl2br(html((string)$t['close_message'])); ?></div>
        </div>
    <?php endif; ?>

    <div class="sig-box">
        <div class="sig-title">Firma del cliente</div>
        <div class="sig-body">
            <?php if ($ticketClientSignatureUrl !== ''): ?>
                <img src="<?php echo html($ticketClientSignatureUrl); ?>" alt="Firma del cliente" class="sig-img">
            <?php else: ?>
                <div style="padding: 20px 0; color: #94a3b8; font-size: 13px; font-weight: 700; font-style: italic; letter-spacing: 0.03em;">(No incluye firma)</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        <?php echo html($companyName); ?> · <?php echo html($companyWebsite); ?>
    </div>
</div>

<script>
    // Auto print like osTicket-style print view
    <?php if (!defined('TICKET_PDF_RENDER')): ?>
    window.addEventListener('load', function () {
        setTimeout(function () {
            try { window.print(); } catch (e) {}
        }, 300);
    });
    <?php endif; ?>
</script>
</body>
</html>
