<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

// Necesitamos autoload para Dompdf
require_once '../../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['staff_id'])) {
    die("Acceso denegado. Debe iniciar sesión.");
}
requireLogin('agente');

$reportId = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;
if ($reportId <= 0) {
    die("Reporte inválido.");
}
$eid = empresaId();

// 1. Obtener la información del reporte y del ticket asociado
$sql = "SELECT r.*, t.ticket_number, t.subject, t.closed, 
               d.name as department_name,
               s.firstname as st_first, s.lastname as st_last,
               c.firstname as cl_first, c.lastname as cl_last, c.email as cl_email
        FROM ticket_reports r
        JOIN tickets t ON r.ticket_id = t.id
        JOIN departments d ON t.dept_id = d.id
        LEFT JOIN staff s ON t.staff_id = s.id
        LEFT JOIN users c ON t.user_id = c.id
        WHERE r.id = ? AND t.empresa_id = ?";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('ii', $reportId, $eid);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
    die("Reporte no encontrado o no pertenece a esta empresa.");
}


// Obtener settings de empresa
$companyName = trim((string)getAppSetting('company.name', ''));
if ($companyName === '') $companyName = (string)APP_NAME;
$companyWebsite = trim((string)getAppSetting('company.website', ''));
if ($companyWebsite === '') $companyWebsite = (string)APP_URL;

$logoUrl = '';
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
}

// Format logic
$ticketNo = htmlspecialchars($report['ticket_number']);
$deptName = htmlspecialchars($report['department_name']);
$staffName = trim(($report['st_first'] ?? '') . ' ' . ($report['st_last'] ?? ''));
$staffName = $staffName !== '' ? htmlspecialchars($staffName) : 'Sin asignar';
$clientName = trim(($report['cl_first'] ?? '') . ' ' . ($report['cl_last'] ?? ''));
$clientName = $clientName !== '' ? htmlspecialchars($clientName) : htmlspecialchars($report['cl_email'] ?? 'Usuario Web');
$closeDate = htmlspecialchars(date('d/m/Y H:i', strtotime($report['closed'])));

$workDesc = nl2br(htmlspecialchars($report['work_description']));
$obs = !empty($report['observations']) ? nl2br(htmlspecialchars($report['observations'])) : '— Ninguna observación extra. —';
$price = !empty($report['final_price']) ? htmlspecialchars($report['final_price']) : 'N/A';
$appName = htmlspecialchars($companyName); // Use dynamic company name
$webSafe = htmlspecialchars(str_replace(['http://', 'https://'], '', $companyWebsite));
$nowDate = date('d M Y - h:i A');

// 3. Render HTML
$html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Servicio #{$ticketNo}</title>
    <style>
        :root{
            --ink:#0f172a;
            --muted:#64748b;
            --line:#e2e8f0;
            --paper:#ffffff;
            --soft:#f8fafc;
            --brand:#2563eb;
        }
        html,body{background:var(--paper); color:var(--ink); font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size:13px; margin:0; padding:0;}
        .sheet{padding: 30px; margin: 0;}
        
        .logo img { max-height: 50px; max-width: 220px; }
        
        .summary{margin-top: 20px; margin-bottom: 25px; background: var(--soft); border:1px solid var(--line); border-radius:10px; padding: 12px 14px;}
        .summary-table{width: 100%; border-collapse: collapse;}
        .summary-table td{padding: 6px 8px; vertical-align: top;}
        .kv .k{color:var(--muted); font-weight:bold; text-transform:uppercase; letter-spacing:1px; font-size: 10px; display:inline-block; width:110px;}
        .kv .v{font-weight:bold; color:var(--ink); display:inline-block; font-size: 12px;}

        h3.section-title {
            color: #1e293b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 4px;
            margin-top: 15px;
            margin-bottom: 15px;
        }

        .box {
            border: 1px solid #cbd5e1;
            padding: 12px 16px;
            border-radius: 8px;
            background: #ffffff;
            margin-bottom: 20px;
            color: #334155;
            line-height: 1.6;
        }

        .materials-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .materials-table th { background: #f1f5f9; padding: 8px; font-size: 11px; text-transform: uppercase; color: #475569; border: 1px solid #cbd5e1; text-align: left; }
        .materials-table td { padding: 8px; border: 1px solid #cbd5e1; font-size: 13px; color: #1e293b; }

        .price-wrapper {
            margin-top: 15px;
            text-align: right;
        }
        .price-box {
            display: inline-block;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            color: #1e3a8a;
        }

        .signatures {
            width: 100%;
            margin-top: 70px;
        }
        .signatures td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0 20px;
        }
        .sig-line {
            border-top: 1px solid #64748b;
            padding-top: 6px;
            font-weight: bold;
            color: #334155;
            font-size: 12px;
        }
        .sig-sub { font-size: 10px; font-weight: normal; color: #94a3b8; text-transform: uppercase; }

        .footer {
            position: fixed; bottom: -15px; left: 0; right: 0;
            font-size: 10px; color: #94a3b8; text-align: center;
            border-top: 1px solid #f1f5f9; padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="sheet">
        <!-- HEADER -->
        <table style="width:100%; border-bottom:2px solid #cbd5e1; padding-bottom: 20px;">
            <tr>
                <td style="vertical-align:bottom; width:60%;">
                    <div style="text-align:left;">
HTML;

if ($logoUrl !== '') {
    $html .= '<div class="logo" style="margin-bottom:12px;"><img src="' . htmlspecialchars($logoUrl) . '" alt="' . $appName . '"></div>';
}

$html .= <<<HTML
                        <h1 style="font-size:13px; margin:0; font-weight:bold; text-transform:uppercase; letter-spacing:0.06em; color:#0f172a;">{$appName}</h1>
                        <div style="color:#2563eb; font-weight:bold; margin-top:4px; font-size:11px; letter-spacing:0.02em;">{$webSafe}</div>
                    </div>
                </td>
                <td style="vertical-align:bottom; text-align:right; width:40%;">
                    <div style="font-size:10px; text-transform:uppercase; font-weight:bold; color:#64748b; letter-spacing:1px; margin-bottom:6px;">Reporte de Cierre de Servicio</div>
                    <div style="font-size:28px; font-weight:bold; color:#0f172a; line-height:1; margin-bottom:10px;">#{$ticketNo}</div>
                    <div style="font-size:11px; color:#64748b; font-weight:bold;">Impreso: {$nowDate}</div>
                </td>
            </tr>
        </table>

        <!-- RESUMEN -->
        <div class="summary">
            <table class="summary-table">
                <tr>
                    <td class="kv"><span class="k">Cliente (Dueño)</span><span class="v">{$clientName}</span></td>
                    <td class="kv"><span class="k">Departamento</span><span class="v">{$deptName}</span></td>
                </tr>
                <tr>
                    <td class="kv"><span class="k">Técnico Asignado</span><span class="v">{$staffName}</span></td>
                    <td class="kv"><span class="k">Fecha de Cierre</span><span class="v">{$closeDate}</span></td>
                </tr>
            </table>
        </div>

        <!-- DETALLES DEL TRABAJO -->
        <h3 class="section-title">Trabajo Realizado</h3>
        <div class="box">
            {$workDesc}
        </div>

HTML;
$html .= <<<HTML
        <!-- OBSERVACIONES -->
        <h3 class="section-title">Observaciones Adicionales</h3>
        <div class="box" style="margin-bottom: 10px;">
            {$obs}
        </div>

        <!-- PRECIO -->
        <div class="price-wrapper">
            <div class="price-box">Total del Servicio: {$price}</div>
        </div>

        <!-- FIRMAS -->
        <table class="signatures">
            <tr>
                <td>
                    <div class="sig-line">
                        {$staffName}<br>
                        <span class="sig-sub">Firma del Técnico</span>
                    </div>
                </td>
                <td>
                    <div class="sig-line">
                        {$clientName}<br>
                        <span class="sig-sub">Firma de Conformidad del Cliente</span>
                    </div>
                </td>
            </tr>
        </table>
        
        <br>
        <br>

        <!-- FOOTER -->
        <div class="footer">
            Generado automáticamente por {$appName} — Evidencia de Servicio Concluido
        </div>
    </div>
</body>
</html>
HTML;

// 4. Dompdf config and Output
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');

// Ignorar advertencias internas de Dompdf en producción
@$dompdf->render();

$filename = "Reporte_Ticket_{$ticketNo}.pdf";

// Output al navegador
$dompdf->stream($filename, ["Attachment" => false]);
