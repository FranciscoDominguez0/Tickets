<?php
ob_start();
/**
 * Vigitec — Reporte Profesional de Servicios (.xlsx)
 * Diseño Premium con Logo Profesional.
 */

// Desactivar visualización de errores para evitar que corrompan el Excel
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

// ── Autenticación ──────────────────────────────────────────────────────────
if (!isset($_SESSION['staff_id'])) { 
    if (ob_get_length()) ob_end_clean();
    die("Acceso denegado."); 
}
requireLogin('agente');
$eid = empresaId();

// ── Procesamiento de Filtros ───────────────────────────────────────────────
$month  = trim((string)($_GET['month'] ?? date('Y-m')));
$search = trim((string)($_GET['q']     ?? ''));
$statusIdClosed = getClosedStatusId($mysqli);

$data = fetchReportData($mysqli, $eid, $statusIdClosed, $month, $search);
$itemData = fetchReportItems($mysqli, $eid, $statusIdClosed, $month, $search);
$fullMonthName = getSpanishMonthName($month);

// ── Generación de Excel ────────────────────────────────────────────────────
generatePremiumExcel($data, $itemData, $month, $fullMonthName);

// ── Funciones Auxiliares ───────────────────────────────────────────────────

function getClosedStatusId($mysqli) {
    $rsSt = $mysqli->query('SELECT id, name FROM ticket_status');
    if ($rsSt) {
        while ($st = $rsSt->fetch_assoc()) {
            $sname = strtolower(trim((string)($st['name'] ?? '')));
            if ($sname !== '' && (str_contains($sname, 'cerrad') || str_contains($sname, 'closed'))) {
                return (int)$st['id'];
            }
        }
    }
    return 0;
}

function fetchReportData($mysqli, $eid, $statusIdClosed, $month, $search) {
    $searchLike = '%' . $search . '%';
    $searchWhere = $search !== ''
        ? " AND (t.ticket_number LIKE ? OR d.name LIKE ? OR CONCAT(u.firstname,' ',u.lastname) LIKE ? OR u.email LIKE ?)"
        : '';

    $sql = "SELECT t.ticket_number, d.name AS department,
                   CONCAT(s.firstname,' ',s.lastname) AS staff_name,
                   t.closed, r.final_price, r.work_description
            FROM tickets t
            JOIN departments d ON t.dept_id = d.id AND d.requires_report = 1
            JOIN ticket_reports r ON r.ticket_id = t.id
            LEFT JOIN staff s ON t.staff_id = s.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE t.empresa_id = ?
              AND t.status_id = ?
              AND DATE_FORMAT(t.closed,'%Y-%m') = ?
              {$searchWhere}
            ORDER BY t.closed DESC";

    $stmt = $mysqli->prepare($sql);
    if ($search !== '') {
        $stmt->bind_param('iisssss', $eid, $statusIdClosed, $month, $searchLike, $searchLike, $searchLike, $searchLike);
    } else {
        $stmt->bind_param('iis', $eid, $statusIdClosed, $month);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) { $rows[] = $row; }
    return $rows;
}

function fetchReportItems($mysqli, $eid, $statusIdClosed, $month, $search) {
    $searchLike = '%' . $search . '%';
    $searchWhere = $search !== ''
        ? " AND (t.ticket_number LIKE ? OR d.name LIKE ? OR CONCAT(u.firstname,' ',u.lastname) LIKE ? OR u.email LIKE ?)"
        : '';

    $sql = "SELECT t.ticket_number, i.description, i.price
            FROM ticket_report_items i
            JOIN ticket_reports r ON i.report_id = r.id
            JOIN tickets t ON r.ticket_id = t.id
            JOIN departments d ON t.dept_id = d.id AND d.requires_report = 1
            LEFT JOIN staff s ON t.staff_id = s.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE t.empresa_id = ?
              AND t.status_id = ?
              AND DATE_FORMAT(t.closed,'%Y-%m') = ?
              {$searchWhere}
            ORDER BY t.closed DESC, i.id ASC";

    $stmt = $mysqli->prepare($sql);
    if ($search !== '') {
        $stmt->bind_param('iisssss', $eid, $statusIdClosed, $month, $searchLike, $searchLike, $searchLike, $searchLike);
    } else {
        $stmt->bind_param('iis', $eid, $statusIdClosed, $month);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) { $rows[] = $row; }
    return $rows;
}

function getSpanishMonthName($month) {
    $monthsEs = ['January'=>'Enero','February'=>'Febrero','March'=>'Marzo','April'=>'Abril','May'=>'Mayo','June'=>'Junio','July'=>'Julio','August'=>'Agosto','September'=>'Septiembre','October'=>'Octubre','November'=>'Noviembre','December'=>'Diciembre'];
    return str_replace(array_keys($monthsEs), array_values($monthsEs), date('F Y', strtotime($month.'-01')));
}

function generatePremiumExcel($rows, $itemRows, $monthKey, $monthName) {
    $spreadsheet = new Spreadsheet();
    $ws = $spreadsheet->getActiveSheet();
    $ws->setTitle('Reporte Detallado');

    // Paleta de colores Vigitec
    $c = [
        'primary'    => '0A2463',
        'secondary'  => '247BA0',
        'accent'     => 'FB3640',
        'gold'       => 'D4AF37',
        'white'      => 'FFFFFF',
        'light_bg'   => 'F8F9FA',
        'border'     => 'CED4DA',
        'text'       => '212529'
    ];

    // Configuración de página
    $ws->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    $ws->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
    $ws->setShowGridlines(false);

    // Ancho de columnas
    $ws->getColumnDimension('A')->setWidth(16); // Ticket #
    $ws->getColumnDimension('B')->setWidth(26); // Departamento
    $ws->getColumnDimension('C')->setWidth(28); // Técnico
    $ws->getColumnDimension('D')->setWidth(22); // Fecha/Hora
    $ws->getColumnDimension('E')->setWidth(20); // Precio
    $ws->getColumnDimension('F')->setWidth(70); // Descripción

    // ── LOGO Y CABECERA ────────────────────────────────────────────────────
    $ws->getRowDimension(1)->setRowHeight(40);
    $ws->getRowDimension(2)->setRowHeight(40);
    $ws->getRowDimension(3)->setRowHeight(30);

    // Intentar insertar logo si existe
    $logoPath = '../../publico/img/vigitec-logo.webp';
    if (file_exists($logoPath)) {
        try {
            $drawing = new Drawing();
            $drawing->setName('Logo Vigitec');
            $drawing->setDescription('Logo');
            $drawing->setPath($logoPath);
            $drawing->setHeight(70);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(10);
            $drawing->setOffsetY(5);
            $drawing->setWorksheet($ws);
        } catch (\Exception $e) {
            // Si falla la carga del logo por formato, ignorar y seguir con texto
        }
    }

    // Título Principal
    $ws->mergeCells('B1:F2');
    $ws->setCellValue('B1', "VIGITEC — SISTEMA INTEGRAL DE TICKETS\nREPORTE DE SERVICIOS Y FACTURACIÓN");
    $ws->getStyle('B1')->getAlignment()->setWrapText(true);
    $ws->getStyle('B1')->applyFromArray([
        'font' => ['bold'=>true,'size'=>20,'color'=>['argb'=>'FF'.$c['white']]],
        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FF'.$c['primary']]],
        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    ]);

    // Color de fondo para A1:A2
    $ws->getStyle('A1:A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF'.$c['primary']);

    // Subheader con Información de Periodo
    $ws->mergeCells('A3:F3');
    $ws->setCellValue('A3', "PERIODO: " . mb_strtoupper($monthName, 'UTF-8') . " | GENERADO: " . date('d/m/Y H:i'));
    $ws->getStyle('A3')->applyFromArray([
        'font' => ['bold'=>true,'size'=>11,'color'=>['argb'=>'FF'.$c['primary']]],
        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FFFFE6E6']],
        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
        'borders' => ['bottom'=>['borderStyle'=>Border::BORDER_MEDIUM,'color'=>['argb'=>'FF'.$c['primary']]]]
    ]);

    // Cabeceras de Tabla
    $ws->getRowDimension(5)->setRowHeight(35);
    $headers = [
        'A5'=>'TICKET #',
        'B5'=>'DEPARTAMENTO',
        'C5'=>'TÉCNICO RESPONSABLE',
        'D5'=>'FECHA/HORA CIERRE',
        'E5'=>'PRECIO FINAL',
        'F5'=>'DESCRIPCIÓN DEL TRABAJO'
    ];
    foreach($headers as $cell => $val) { $ws->setCellValue($cell, $val); }

    $ws->getStyle('A5:F5')->applyFromArray([
        'font' => ['bold'=>true,'size'=>11,'color'=>['argb'=>'FF'.$c['white']]],
        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FF'.$c['secondary']]],
        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['argb'=>'FFFFFFFF']]]
    ]);

    // Datos
    $startRow = 6;
    $currentRow = $startRow;
    $totalAmount = 0.0;

    foreach ($rows as $row) {
        $price = (float)preg_replace('/[^0-9.]/', '', str_replace(',', '.', (string)$row['final_price']));
        $totalAmount += $price;

        $ws->getRowDimension($currentRow)->setRowHeight(40);
        $ws->setCellValue("A$currentRow", " " . $row['ticket_number']);
        $ws->setCellValue("B$currentRow", $row['department']);
        $ws->setCellValue("C$currentRow", mb_strtoupper((string)$row['staff_name'], 'UTF-8'));
        $ws->setCellValue("D$currentRow", date('d/m/Y H:i', strtotime((string)$row['closed'])));
        $ws->setCellValue("E$currentRow", $price);
        $ws->getStyle("E$currentRow")->getNumberFormat()->setFormatCode('"USD "#,##0.00');
        $ws->setCellValue("F$currentRow", trim(preg_replace('/\s+/', ' ', (string)$row['work_description'])));

        $currentRow++;
    }

    // Aplicar estilos a todo el rango a la vez (Optimización de velocidad)
    if ($currentRow > $startRow) {
        $lastRow = $currentRow - 1;
        $ws->getStyle("A$startRow:F$lastRow")->applyFromArray([
            'alignment' => ['vertical'=>Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['argb'=>'FFEEEEEE']]]
        ]);
        $ws->getStyle("A$startRow:A$lastRow")->getFont()->setBold(true)->getColor()->setARGB('FF'.$c['secondary']);
        $ws->getStyle("F$startRow:F$lastRow")->getAlignment()->setWrapText(true);
    }

    // Fila de Total
    $ws->getRowDimension($currentRow)->setRowHeight(35);
    $ws->mergeCells("A$currentRow:D$currentRow");
    $ws->setCellValue("A$currentRow", 'SUMATORIA TOTAL DEL PERIODO ');
    $ws->setCellValue("E$currentRow", $totalAmount);
    $ws->getStyle("E$currentRow")->getNumberFormat()->setFormatCode('"USD "#,##0.00');

    $ws->getStyle("A$currentRow:E$currentRow")->applyFromArray([
        'font' => ['bold'=>true,'size'=>12,'color'=>['argb'=>'FF'.$c['white']]],
        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FF'.$c['primary']]],
        'alignment' => ['vertical'=>Alignment::VERTICAL_CENTER],
    ]);
    $ws->getStyle("A$currentRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    // Repetir cabeceras al imprimir
    $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 5);

    // Segunda Hoja: Análisis por Área
    setupAnalysisSheet($spreadsheet, $rows, $monthName, $c);

    // Tercera Hoja: Detalle por Item
    setupDetailSheet($spreadsheet, $itemRows, $monthName, $c);

    $spreadsheet->setActiveSheetIndex(0);

    $filename = "reporte_vigitec_usd_{$monthKey}.xlsx";

    // Limpiar cualquier salida previa para evitar corrupción
    if (ob_get_length()) ob_end_clean();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    setcookie('fileDownloadToken', 'true', time() + 300, '/');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function setupAnalysisSheet($spreadsheet, $rows, $monthName, $c) {
    $ws = $spreadsheet->createSheet();
    $ws->setTitle('Análisis por Área');
    $ws->setShowGridlines(false);

    $ws->getColumnDimension('A')->setWidth(40);
    $ws->getColumnDimension('B')->setWidth(20);
    $ws->getColumnDimension('C')->setWidth(25);

    $ws->mergeCells('A1:C1');
    $ws->setCellValue('A1', 'ESTADÍSTICAS POR DEPARTAMENTO — ' . mb_strtoupper($monthName, 'UTF-8'));
    $ws->getStyle('A1')->applyFromArray([
        'font' => ['bold'=>true,'size'=>14,'color'=>['argb'=>'FF'.$c['white']]],
        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FF'.$c['primary']]],
        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    ]);

    $ws->getRowDimension(3)->setRowHeight(25);
    $ws->setCellValue('A3', 'DEPARTAMENTO');
    $ws->setCellValue('B3', 'N° SERVICIOS');
    $ws->setCellValue('C3', 'TOTAL FACTURADO (USD)');
    $ws->getStyle('A3:C3')->applyFromArray([
        'font' => ['bold'=>true,'color'=>['argb'=>'FF'.$c['white']]],
        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FF'.$c['secondary']]],
        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
    ]);

    $stats = [];
    foreach ($rows as $r) {
        $d = $r['department'];
        $p = (float)preg_replace('/[^0-9.]/', '', str_replace(',', '.', (string)$r['final_price']));
        if (!isset($stats[$d])) $stats[$d] = ['count'=>0, 'total'=>0.0];
        $stats[$d]['count']++;
        $stats[$d]['total'] += $p;
    }

    $rowNum = 4;
    foreach ($stats as $name => $val) {
        $ws->setCellValue("A$rowNum", $name);
        $ws->setCellValue("B$rowNum", $val['count']);
        $ws->setCellValue("C$rowNum", $val['total']);
        $ws->getStyle("C$rowNum")->getNumberFormat()->setFormatCode('"USD "#,##0.00');

        $ws->getStyle("A$rowNum:C$rowNum")->applyFromArray([
            'borders' => ['bottom'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['argb'=>'FFDDDDDD']]],
            'alignment' => ['vertical'=>Alignment::VERTICAL_CENTER]
        ]);
        $rowNum++;
    }
}

function setupDetailSheet($spreadsheet, $itemRows, $monthName, $c) {
    $ws = $spreadsheet->createSheet();
    $ws->setTitle('Detalle por Item');
    $ws->setShowGridlines(false);

    $ws->getColumnDimension('A')->setWidth(18);
    $ws->getColumnDimension('B')->setWidth(60);
    $ws->getColumnDimension('C')->setWidth(18);

    $ws->mergeCells('A1:C1');
    $ws->setCellValue('A1', 'DETALLE DE TRABAJOS REALIZADOS — ' . mb_strtoupper($monthName, 'UTF-8'));
    $ws->getStyle('A1')->applyFromArray([
        'font' => ['bold'=>true,'size'=>14,'color'=>['argb'=>'FF'.$c['white']]],
        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FF'.$c['primary']]],
        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    ]);

    $ws->getRowDimension(3)->setRowHeight(25);
    $ws->setCellValue('A3', 'TICKET #');
    $ws->setCellValue('B3', 'DESCRIPCIÓN DEL TRABAJO');
    $ws->setCellValue('C3', 'PRECIO (USD)');
    $ws->getStyle('A3:C3')->applyFromArray([
        'font' => ['bold'=>true,'color'=>['argb'=>'FF'.$c['white']]],
        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FF'.$c['secondary']]],
        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
    ]);

    $rowNum = 4;
    $sumTotal = 0.0;
    foreach ($itemRows as $r) {
        $price = (float)($r['price'] ?? 0);
        $sumTotal += $price;

        $ws->setCellValue("A$rowNum", $r['ticket_number']);
        $ws->setCellValue("B$rowNum", $r['description']);
        $ws->setCellValue("C$rowNum", $price);
        $ws->getStyle("C$rowNum")->getNumberFormat()->setFormatCode('"USD "#,##0.00');

        $fill = ($rowNum % 2 === 0) ? 'FFF8F9FA' : 'FFFFFFFF';
        $ws->getStyle("A$rowNum:C$rowNum")->applyFromArray([
            'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$fill]],
            'alignment' => ['vertical'=>Alignment::VERTICAL_CENTER],
            'borders' => ['bottom'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['argb'=>'FFEEEEEE']]]
        ]);
        $rowNum++;
    }

    // Fila de suma total
    $ws->getRowDimension($rowNum)->setRowHeight(30);
    $ws->mergeCells("A$rowNum:B$rowNum");
    $ws->setCellValue("A$rowNum", 'TOTAL DE TRABAJOS REALIZADOS');
    $ws->setCellValue("C$rowNum", $sumTotal);
    $ws->getStyle("C$rowNum")->getNumberFormat()->setFormatCode('"USD "#,##0.00');

    $ws->getStyle("A$rowNum:C$rowNum")->applyFromArray([
        'font' => ['bold'=>true,'size'=>12,'color'=>['argb'=>'FF'.$c['white']]],
        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FF'.$c['primary']]],
        'alignment' => ['vertical'=>Alignment::VERTICAL_CENTER],
    ]);
    $ws->getStyle("A$rowNum")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}
