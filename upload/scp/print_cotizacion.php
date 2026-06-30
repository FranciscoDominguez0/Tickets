<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}
requireLogin('agente');

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Cotización inválida');
}

$eid = empresaId();

// Obtener cotización
$stmt = $mysqli->prepare("SELECT q.*, 
            o.name as org_name, o.website as org_website,
            CONCAT(s.firstname, ' ', s.lastname) as staff_name,
            (SELECT CONCAT(u.firstname, ' ', u.lastname) FROM user_organizations uo JOIN users u ON u.id = uo.user_id WHERE uo.organization_id = o.id AND u.org_tickets_view = 1 AND u.empresa_id = ? LIMIT 1) as org_boss_name,
            (SELECT u.id FROM user_organizations uo JOIN users u ON u.id = uo.user_id WHERE uo.organization_id = o.id AND u.org_tickets_view = 1 AND u.empresa_id = ? LIMIT 1) as org_boss_id
            FROM quotes q 
            LEFT JOIN organizations o ON q.org_id = o.id 
            LEFT JOIN staff s ON q.staff_id = s.id 
            WHERE q.id = ? AND q.empresa_id = ?");
$stmt->bind_param('iiii', $eid, $eid, $id, $eid);
$stmt->execute();
$quote = $stmt->get_result()->fetch_assoc();

if (!$quote) {
    http_response_code(404);
    exit('Cotización no encontrada');
}

// Obtener el hilo de mensajes
$messages = [];
$stmtMsg = $mysqli->prepare("SELECT m.*, 
    CONCAT(s.firstname, ' ', s.lastname) as staff_name,
    CONCAT(u.firstname, ' ', u.lastname) as user_name
    FROM quote_messages m
    LEFT JOIN staff s ON m.staff_id = s.id
    LEFT JOIN users u ON m.user_id = u.id
    WHERE m.quote_id = ?
    ORDER BY m.created_at ASC");
$stmtMsg->bind_param('i', $id);
$stmtMsg->execute();
$msgResult = $stmtMsg->get_result();
while ($row = $msgResult->fetch_assoc()) {
    $messages[] = $row;
}

// App settings para encabezado
$companyName = trim((string)getAppSetting('company.name', ''));
if ($companyName === '') $companyName = (string)APP_NAME;
$companyWebsite = trim((string)getAppSetting('company.website', ''));
if ($companyWebsite === '') $companyWebsite = (string)APP_URL;
$logoUrl = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png');

// En el caso de PDF render o modo de impresión, intentamos Base64 si aplica
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
        if ($ext === 'webp' && !function_exists('imagecreatefromwebp')) {
            $logoAbsPath = $projectRoot . '/publico/img/vigitec-logo.png';
            $ext = 'png';
        }
        if (is_file($logoAbsPath)) {
            $imageData = file_get_contents($logoAbsPath);
            $skipImage = false;
            if ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
                $im = @imagecreatefromwebp($logoAbsPath);
                if ($im !== false) {
                    ob_start();
                    imagepng($im);
                    $imageData = (string)ob_get_clean();
                    unset($im);
                    $ext = 'png';
                } else {
                    $skipImage = true;
                }
            } elseif ($ext === 'jpg') {
                $ext = 'jpeg';
            }
            if (!$skipImage && $ext !== 'webp') {
                $base64 = base64_encode($imageData);
                $logoUrl = 'data:image/' . $ext . ';base64,' . $base64;
            }
        }
    }
}

// Status mappings
$statusLabels = [
    'draft'    => 'Borrador',
    'pending'  => 'Pendiente de Solicitud',
    'requested'=> 'Solicitada',
    'answered' => 'Esperando Aprobación',
    'accepted' => 'Aceptada',
    'rejected' => 'Rechazada'
];
$statusLabel = $statusLabels[$quote['status']] ?? 'Borrador';

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Cotización #<?php echo $id; ?></title>
    <link rel="icon" href="<?php echo rtrim((string)(defined('APP_URL') ? APP_URL : ''), '/'); ?>/publico/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="css/vendor/bootstrap-icons.css">
    <style>
        :root{
            --ink:#0f172a;
            --muted:#64748b;
            --line:#e2e8f0;
            --paper:#ffffff;
            --soft:#f8fafc;
            --brand:#ef4444;
        }
        html,body{background:var(--paper); color:var(--ink); font-family: "Lato", "Segoe UI", Arial, sans-serif; font-size:14px; margin:0; padding:0;}
        .sheet{max-width: 920px; margin: 22px auto; padding: 0 18px;}
        .logo{text-align:left;}
        .logo img{max-height:60px; max-width:220px; display:block;}
        
        .summary{margin-top: 14px; background: var(--soft); border:1px solid var(--line); border-radius:12px; padding: 12px 14px;}
        .summary-table{width: 100%; border-collapse: collapse;}
        .summary-table td{padding: 5px 8px; vertical-align: top;}
        .kv .k{color:var(--muted); font-weight:800; text-transform:uppercase; letter-spacing:.06em; font-size: 11px; display:inline-block; width:110px;}
        .kv .v{font-weight:700; color:var(--ink); display:inline-block;}

        .thread{margin-top: 14px; overflow: hidden;}
        .entry{box-sizing: border-box; border:1px solid var(--line); border-radius:14px; padding: 12px 14px; margin-bottom: 14px; width: 80%;}
        .entry.staff{border-color:#cbd5e1; background:#f1f5f9; margin-right: 20%; border-bottom-left-radius: 4px;}
        .entry.user{border-color:#bae6fd; background:#e0f2fe; margin-left: 20%; border-bottom-right-radius: 4px;}
        .entry-head{border-bottom: 1px solid rgba(0,0,0,0.06); padding-bottom: 6px; margin-bottom: 8px;}
        .entry-head-table{width:100%;}
        .who{font-weight:900;}
        .when{color:var(--muted); font-weight:700; font-size: 11px; text-align:right;}
        .body{white-space:pre-wrap; word-break:break-word; line-height:1.45;}
        
        .footer{margin-top: 24px; color: var(--muted); font-weight:700; font-size: 12px; text-align:center; border-top: 1px solid var(--line); padding-top: 12px;}
        
        @media print{
            .sheet{max-width:none; margin:0; padding:0;}
            .entry{page-break-inside: avoid;}
            .summary{page-break-inside: avoid;}
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
                    <div style="color:#ef4444; font-weight:700; margin-top:4px; font-size:11px; letter-spacing:0.02em;"><?php echo html(str_replace(['http://', 'https://'], '', $companyWebsite)); ?></div>
                </div>
            </td>
            <td style="vertical-align:bottom; text-align:right; width:40%;">
                <div style="font-size:10px; text-transform:uppercase; font-weight:800; color:#64748b; letter-spacing:0.08em; margin-bottom:6px;">Documento de Cotización</div>
                <div style="font-size:28px; font-weight:900; color:#0f172a; line-height:1; letter-spacing:-0.02em; margin-bottom:10px;">#<?php echo $id; ?></div>
                <div style="font-size:13px; font-weight:800; color:#334155; margin-bottom:6px;"><?php echo html($quote['title']); ?></div>
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
                <td class="kv"><span class="k">Organización</span><span class="v"><?php echo html($quote['org_name'] ?: 'N/A'); ?></span></td>
                <td class="kv"><span class="k">Sucursal</span><span class="v"><?php echo html($quote['sucursal'] ?: 'Principal'); ?></span></td>
            </tr>
            <tr>
                <td class="kv"><span class="k">Estado</span><span class="v"><?php echo html($statusLabel); ?></span></td>
                <td class="kv"><span class="k">Agente Asignado</span><span class="v"><?php echo html($quote['staff_name'] ?: '—'); ?></span></td>
            </tr>
            <tr>
                <td class="kv"><span class="k">Encargado</span><span class="v"><?php echo html($quote['org_boss_name'] ?: '—'); ?></span></td>
                <td class="kv"><span class="k">Creada</span><span class="v"><?php echo !empty($quote['created_at']) ? html(date('d/m/Y h:i A', strtotime((string)$quote['created_at']))) : '—'; ?></span></td>
            </tr>
            <?php if ($quote['ticket_id']): ?>
            <tr>
                <td class="kv"><span class="k">Ticket Origen</span><span class="v">#<?php echo (int)$quote['ticket_id']; ?></span></td>
                <td class="kv"></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="thread">
        <?php if (empty($messages)): ?>
            <div class="entry"><div class="who">Sin mensajes</div><div class="body">Aún no hay mensajes en el hilo.</div></div>
        <?php else: ?>
            <?php foreach ($messages as $m): ?>
                <?php
                    $isStaff = !empty($m['staff_id']);
                    $author = $isStaff
                        ? (trim((string)($m['staff_name'] ?? '')) ?: 'Agente')
                        : (trim((string)($m['user_name'] ?? '')) ?: 'Cliente');
                    $cls = $isStaff ? 'staff' : 'user';
                ?>
                <div class="entry <?php echo html($cls); ?>">
                    <div class="entry-head">
                        <table class="entry-head-table" style="border-collapse:collapse; width:100%;">
                            <tr>
                                <td style="vertical-align:middle;">
                                    <span class="who"><?php echo html($author); ?></span>
                                    <span style="font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.04em; color: <?php echo $isStaff ? '#1d4ed8' : '#0369a1'; ?>; margin-left: 8px;">
                                        (<?php echo $isStaff ? 'Agente' : 'Cliente'; ?>)
                                    </span>
                                </td>
                                <td class="when" style="vertical-align:middle; width:150px; text-align:right;">
                                    <?php echo !empty($m['created_at']) ? html(date('d/m/Y h:i A', strtotime((string)$m['created_at']))) : ''; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="body"><?php echo html($m['message']); ?></div>
                    <?php if (!empty($m['file_path'])): 
                        $fileName = basename($m['file_path']);
                    ?>
                        <div class="attachments" style="margin-top: 10px; font-size: 12px; color: var(--muted); border-top: 1px dashed var(--line); padding-top: 6px;">
                            <strong>Adjunto:</strong> <i class="bi bi-paperclip"></i> <?php echo html($fileName); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="footer">
        <?php echo html($companyName); ?> · <?php echo html($companyWebsite); ?>
    </div>
</div>

<script>
    window.addEventListener('load', function () {
        setTimeout(function () {
            try { window.print(); } catch (e) {}
        }, 300);
    });
</script>
</body>
</html>
