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

// 2. Obtener los materiales
$materials = [];
$matStmt = $mysqli->prepare("SELECT material_name, quantity FROM ticket_report_materials WHERE report_id = ? ORDER BY id ASC");
$matStmt->bind_param('i', $reportId);
$matStmt->execute();
$resM = $matStmt->get_result();
while ($m = $resM->fetch_assoc()) {
    $materials[] = $m;
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
$obs = !empty($report['observations']) ? nl2br(htmlspecialchars($report['observations'])) : '<em>Ninguna observación.</em>';
$price = !empty($report['final_price']) ? htmlspecialchars($report['final_price']) : 'N/A';
$appName = htmlspecialchars(APP_NAME);

// 3. Render HTML
$html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Servicio #{$ticketNo}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            margin: 0;
            padding: 0;
            font-size: 13px;
            line-height: 1.5;
        }
        .header {
            width: 100%;
            border-bottom: 2px solid #1d4ed8;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header td { vertical-align: bottom; }
        .title {
            color: #1d4ed8;
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }
        .subtitle {
            color: #64748b;
            font-size: 12px;
            margin: 0;
        }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table th {
            text-align: left; background-color: #f8fafc; color: #475569;
            padding: 6px 10px; font-size: 11px; text-transform: uppercase; border: 1px solid #e2e8f0; width: 25%;
        }
        .info-table td {
            padding: 6px 10px; border: 1px solid #e2e8f0; width: 25%; color: #0f172a; font-weight: bold;
        }
        
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #1d4ed8;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-top: 25px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .content-box {
            padding: 10px 15px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            color: #334155;
            min-height: 40px;
        }

        .materials-table th {
            background-color: #f1f5f9; padding: 8px 10px;
            text-align: left; font-size: 12px; border: 1px solid #cbd5e1;
        }
        .materials-table td {
            padding: 8px 10px; border: 1px solid #cbd5e1; font-size: 13px;
        }

        .price-box {
            float: right; width: 40%;
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 10px; margin-top: 10px;
            font-size: 18px; text-align: right; font-weight: bold; color: #1e3a8a;
        }
        .clear { clear: both; }

        .signatures {
            width: 100%;
            margin-top: 80px;
        }
        .signatures td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        .sig-line {
            width: 70%; margin: 0 auto;
            border-top: 1px solid #64748b;
            padding-top: 5px;
            font-weight: bold;
            color: #334155;
        }
        .sig-sub { font-size: 11px; font-weight: normal; color: #94a3b8; }
        
        .footer {
            position: fixed; bottom: -20px; left: 0; right: 0;
            font-size: 10px; color: #cbd5e1; text-align: center;
            border-top: 1px solid #f1f5f9; padding-top: 10px;
        }
    </style>
</head>
<body>

    <table class="header">
        <tr>
            <td style="width: 50%;">
                <h1 class="title">REPORTE DE SERVICIO</h1>
                <p class="subtitle">Evidencia de trabajo concluido</p>
            </td>
            <td style="width: 50%; text-align: right;">
                <h2 style="margin: 0; color: #0f172a;">{$appName}</h2>
                <p style="margin: 0; color: #64748b; font-size: 12px;">Fecha de impresión: {date('d/m/Y')}</p>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <th>Ticket #</th><td>{$ticketNo}</td>
            <th>Técnico Asignado</th><td>{$staffName}</td>
        </tr>
        <tr>
            <th>Departamento</th><td>{$deptName}</td>
            <th>Fecha de Cierre</th><td>{$closeDate}</td>
        </tr>
        <tr>
            <th>Cliente</th><td colspan="3">{$clientName}</td>
        </tr>
    </table>

    <div class="section-title">Trabajo Realizado</div>
    <div class="content-box">
        {$workDesc}
    </div>

    <div class="section-title">Materiales y Refacciones Utilizadas</div>
HTML;

if (empty($materials)) {
    $html .= '<p style="color: #64748b; font-style: italic;">No se registraron materiales para este servicio.</p>';
} else {
    $html .= '<table class="materials-table">
                <thead><tr><th>Nombre/Descripción del Material</th><th style="width:30%;">Cantidad / Medida</th></tr></thead>
                <tbody>';
    foreach ($materials as $m) {
        $html .= '<tr>
                    <td>' . htmlspecialchars($m['material_name']) . '</td>
                    <td>' . htmlspecialchars($m['quantity']) . '</td>
                  </tr>';
    }
    $html .= '</tbody></table>';
}

$html .= <<<HTML
    <div class="section-title" style="margin-top: 15px;">Observaciones Adicionales</div>
    <div class="content-box" style="margin-bottom: 20px;">
        {$obs}
    </div>

    <div class="price-box">
        Total del Servicio: {$price}
    </div>
    <div class="clear"></div>

    <table class="signatures">
        <tr>
            <td>
                <div class="sig-line">
                    Firma del Técnico<br>
                    <span class="sig-sub">{$staffName}</span>
                </div>
            </td>
            <td>
                <div class="sig-line">
                    Firma de Conformidad del Cliente<br>
                    <span class="sig-sub">{$clientName}</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Generado por {$appName} - Documento de uso interno
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
