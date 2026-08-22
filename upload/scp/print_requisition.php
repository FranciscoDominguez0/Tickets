<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
$eid = empresaId();
$sid = (int)$_SESSION['staff_id'];
$canManage = roleHasPermission('requisitions.manage');

$reqId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if ($reqId <= 0) {
    http_response_code(400);
    exit('Requisición inválida');
}

$stmt = $mysqli->prepare("SELECT * FROM requisitions WHERE id = ? AND empresa_id = ? LIMIT 1");
$stmt->bind_param('ii', $reqId, $eid);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();

if (!$req) {
    http_response_code(404);
    exit('Requisición no encontrada');
}

if ($req['agent_id'] != $sid && !$canManage) {
    http_response_code(403);
    exit('No tienes permisos para imprimir esta requisición.');
}

$stmtItems = $mysqli->prepare("SELECT * FROM requisition_items WHERE requisition_id = ?");
$stmtItems->bind_param('i', $reqId);
$stmtItems->execute();
$items = $stmtItems->get_result();

$adminName = 'Desconocido';
if ($req['admin_id_delivered']) {
    $stmtA = $mysqli->prepare("SELECT firstname, lastname FROM staff WHERE id = ? LIMIT 1");
    $stmtA->bind_param('i', $req['admin_id_delivered']);
    $stmtA->execute();
    $resA = $stmtA->get_result()->fetch_assoc();
    if ($resA) $adminName = trim($resA['firstname'] . ' ' . $resA['lastname']);
}

$agentName = 'Desconocido';
if ($req['agent_id']) {
    $stmtA = $mysqli->prepare("SELECT firstname, lastname FROM staff WHERE id = ? LIMIT 1");
    $stmtA->bind_param('i', $req['agent_id']);
    $stmtA->execute();
    $resA = $stmtA->get_result()->fetch_assoc();
    if ($resA) $agentName = trim($resA['firstname'] . ' ' . $resA['lastname']);
}

// App settings para encabezado
$companyName = trim((string)getAppSetting('company.name', ''));
if ($companyName === '') $companyName = (string)APP_NAME;
$companyWebsite = trim((string)getAppSetting('company.website', ''));
if ($companyWebsite === '') $companyWebsite = (string)APP_URL;
$logoUrl = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png');

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Requisición #REQ-<?php echo str_pad($reqId, 5, '0', STR_PAD_LEFT); ?></title>
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
        .brand{display:flex; align-items:center; gap:12px; min-width: 0;}
        .logo{text-align:left;}
        .logo img{max-height:60px; max-width:220px; display:block;}
        .brand h1{font-size:16px; margin:0; font-weight:900; line-height:1.1;}
        .brand .web{color:var(--muted); font-weight:600; margin-top:2px;}
        .meta{ text-align:right; }

        .summary{margin-top: 14px; background: var(--soft); border:1px solid var(--line); border-radius:12px; padding: 12px 14px;}
        .summary-table{width: 100%; border-collapse: collapse;}
        .summary-table td{padding: 5px 8px; vertical-align: top; width: 50%;}
        .kv{margin-bottom: 0;}
        .kv .k{display: block; color:var(--muted); font-weight:800; text-transform:uppercase; letter-spacing:.06em; font-size: 11px; margin-bottom: 3px;}
        .kv .v{display: block; font-weight:700; color:var(--ink); font-size: 14px;}

        .items-section{margin-top: 14px; border:1px solid var(--line); border-radius:14px; padding: 12px 14px;}
        .items-table{width: 100%; border-collapse: collapse; margin-top: 10px;}
        .items-table th{text-align:left; padding: 8px; border-bottom: 2px solid var(--line); font-size: 11px; text-transform:uppercase; color: var(--muted); letter-spacing:.06em;}
        .items-table td{padding: 8px; border-bottom: 1px solid var(--line); font-weight:700;}
        .items-table tr:last-child td{border-bottom: none;}

        .footer{margin-top: 16px; color: var(--muted); font-weight:700; font-size: 12px; text-align:center;}
        .signatures{display: flex; flex-wrap: wrap; justify-content: space-around; gap: 30px; margin-top: 40px; padding: 0 10%;}
        .sig-box{flex: 1; min-width: 200px; text-align: center; max-width: 280px;}
        .sig-title{font-size:11px; text-transform:uppercase; letter-spacing:.05em; font-weight:800; color:var(--muted); border-bottom:1px solid var(--line); padding-bottom:6px; margin-bottom:8px;}
        .sig-body{text-align:center; padding: 4px; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; min-height: 110px;}
        .sig-space { height: 80px; display: flex; align-items: flex-end; justify-content: center; margin-bottom: 8px; width: 100%; }
        .sig-img{display:inline-block; max-width:100%; max-height:80px; width:auto; height:auto; filter: contrast(1.1) grayscale(0.5);}
        
        .top-header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #cbd5e1; padding-bottom: 24px; margin-bottom: 20px; gap: 20px; }
        .top-left { flex: 1; text-align: left; }
        .top-right { flex: 0 0 auto; text-align: right; }

        @media (max-width: 600px) {
            .top-header { flex-direction: column; align-items: center; text-align: center; gap: 24px; }
            .top-left, .top-right { text-align: center !important; }
            .logo { display: flex; justify-content: center; margin-bottom: 12px; }
            
            .summary-table, .summary-table tbody { display: block; width: 100%; }
            .summary-table tr { display: flex; flex-direction: column; width: 100%; gap: 12px; margin-bottom: 12px; }
            .summary-table td { width: 100%; padding: 0 8px; }
            
            .signatures { flex-direction: column; align-items: center; padding: 0; }
            .sig-box { width: 100%; max-width: 100%; }
        }
        
        @page {
            margin: 0;
        }
        @media print{
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            body {
                padding: 1.5cm;
            }
            .sheet{max-width:none; margin:0; padding:0;}
            .items-section{page-break-inside: avoid;}
            .signatures{page-break-inside: avoid;}
        }
    </style>
</head>
<body>
<div class="sheet">
    <div class="top-header">
        <div class="top-left">
            <?php if ($logoUrl !== ''): ?>
                <div class="logo"><img src="<?php echo html($logoUrl); ?>" alt="<?php echo html($companyName); ?>"></div>
            <?php endif; ?>
            <h1 style="font-size:13px; margin:0; font-weight:900; text-transform:uppercase; letter-spacing:0.06em; color:#0f172a;"><?php echo html($companyName); ?></h1>
            <div style="color:#ef4444; font-weight:700; margin-top:4px; font-size:11px; letter-spacing:0.02em;"><?php echo html(str_replace(['http://', 'https://'], '', $companyWebsite)); ?></div>
        </div>
        <div class="top-right">
            <div style="font-size:10px; text-transform:uppercase; font-weight:800; color:#64748b; letter-spacing:0.08em; margin-bottom:8px;">Comprobante de Salida</div>
            <div style="display:inline-flex; align-items: baseline; gap: 2px; padding: 6px 16px 7px; border-radius: 12px; background: #0f172a; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.18); margin-bottom: 12px;">
                <span style="color: #ef4444; font-weight: 800; font-size: 24px; line-height: 1;">REQ-</span>
                <span style="color: #ffffff; font-weight: 900; font-size: 26px; line-height: 1; letter-spacing: 0.04em; font-family: ui-monospace, 'Cascadia Code', 'Segoe UI Mono', monospace;">
                    <?php echo str_pad($reqId, 5, '0', STR_PAD_LEFT); ?>
                </span>
            </div>
            <div style="font-size:11px; color:#64748b; font-weight:700;">Emitido: <?php 
                $originalTz = date_default_timezone_get();
                date_default_timezone_set('America/Panama');
                echo date('d M Y - h:i A'); 
                date_default_timezone_set($originalTz);
            ?></div>
        </div>
    </div>

    <div class="summary">
        <table class="summary-table">
            <tr>
                <td class="kv">
                    <span class="k">Agente</span>
                    <span class="v"><?php echo html($agentName); ?></span>
                </td>
                <td class="kv">
                    <span class="k">Destino / Cliente</span>
                    <span class="v"><?php echo html($req['client_name']); ?></span>
                </td>
            </tr>
            <tr>
                <td class="kv">
                    <span class="k">Estado</span>
                    <span class="v"><?php echo $req['status'] === 'delivered' ? 'Entregado' : 'Pendiente'; ?></span>
                </td>
                <td class="kv">
                    <span class="k">Fecha Solicitud</span>
                    <span class="v"><?php echo html(date('d/m/Y h:i A', strtotime((string)$req['created_at']))); ?></span>
                </td>
            </tr>
        </table>
    </div>

    <div class="items-section">
        <h3 style="margin:0 0 10px 0; font-size: 13px; text-transform:uppercase; letter-spacing:0.04em; color:var(--ink);">Productos Solicitados</h3>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th style="width: 80px; text-align: center;">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($item = $items->fetch_assoc()): ?>
                <tr>
                    <td><?php echo html($item['product_name']); ?></td>
                    <td style="text-align: center;"><span style="background: #f1f5f9; padding: 2px 8px; border-radius: 12px; font-weight: 900; border: 1px solid #e2e8f0;"><?php echo html($item['quantity']); ?></span></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php if ($req['status'] === 'delivered'): ?>
    <div class="signatures">
        <div class="sig-box">
            <div class="sig-title">Entregado por</div>
            <div class="sig-body">
                <div class="sig-space"></div>
                <div style="font-size:11px; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:0.02em;"><?php echo html($adminName); ?></div>
                <div style="font-size:10px; color:#94a3b8; font-weight:600; margin-top:2px;">Fecha: <?php echo date('d/m/Y h:i A', strtotime($req['delivered_at'])); ?></div>
            </div>
        </div>
        <div class="sig-box">
            <div class="sig-title">Recibido por</div>
            <div class="sig-body">
                <div class="sig-space">
                    <?php if (!empty($req['agent_signature'])): ?>
                        <img src="<?php echo html($req['agent_signature']); ?>" alt="Firma del agente" class="sig-img">
                    <?php else: ?>
                        <span style="color: #94a3b8; font-size: 13px; font-weight: 700; font-style: italic; letter-spacing: 0.03em;">(No incluye firma)</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:11px; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:0.02em;"><?php echo html($agentName); ?></div>
                <?php if ($req['signed_at']): ?>
                    <div style="font-size:10px; color:#94a3b8; font-weight:600; margin-top:2px;">Fecha: <?php echo date('d/m/Y h:i A', strtotime($req['signed_at'])); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="footer">
        <?php echo html($companyName); ?> · <?php echo html($companyWebsite); ?>
    </div>
</div>

<script>
    window.addEventListener('load', function () {
        setTimeout(function () {
            try { window.print(); } catch (e) {}
        }, 500);
    });
</script>
</body>
</html>
