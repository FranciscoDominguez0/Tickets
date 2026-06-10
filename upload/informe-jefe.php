<?php
require_once '../config.php';
require_once '../includes/helpers.php';

requireLogin('cliente');

$uid = (int) ($_SESSION['user_id'] ?? 0);
$eid = (int) empresaId();
if ($eid <= 0) {
    $eid = 1;
}

if (!userOrgTicketsViewEnabled($mysqli, $uid, $eid)) {
    header('Location: tickets.php');
    exit;
}

ensureOrgBossReportAttachmentsTable();
ensureOrgBossReportReadsTable();

$reportId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;
if ($reportId <= 0) {
    header('Location: informes-jefes.php');
    exit;
}

// Descarga de adjunto
$downloadId = isset($_GET['download']) && is_numeric($_GET['download']) ? (int) $_GET['download'] : 0;
if ($downloadId > 0) {
    $stmtD = $mysqli->prepare(
        'SELECT a.*, r.organization_id, r.empresa_id FROM org_boss_report_attachments a'
        . ' JOIN org_boss_reports r ON r.id = a.report_id'
        . ' WHERE a.id = ? AND a.report_id = ? AND a.empresa_id = ? LIMIT 1'
    );
    if ($stmtD) {
        $stmtD->bind_param('iii', $downloadId, $reportId, $eid);
        $stmtD->execute();
        $att = $stmtD->get_result()->fetch_assoc();
        if ($att && bossCanAccessOrgReport($mysqli, $uid, $eid, $att)) {
            $rel = (string) ($att['path'] ?? '');
            $fs = __DIR__ . '/' . ltrim(str_replace('\\', '/', $rel), '/');
            if (!is_file($fs)) {
                $fs2 = dirname(__DIR__) . '/' . ltrim($rel, '/');
                if (is_file($fs2)) {
                    $fs = $fs2;
                }
            }
            if (is_file($fs) && is_readable($fs)) {
                $mime = (string) ($att['mimetype'] ?? 'application/octet-stream');
                $orig = (string) ($att['original_filename'] ?? 'archivo');
                header('Content-Type: ' . $mime);
                header('Content-Disposition: attachment; filename="' . str_replace('"', '', $orig) . '"');
                header('Content-Length: ' . (string) filesize($fs));
                readfile($fs);
                exit;
            }
        }
    }
    http_response_code(404);
    echo 'Archivo no encontrado';
    exit;
}

$stmt = $mysqli->prepare(
    'SELECT r.*, o.name AS org_name, s.firstname AS staff_first, s.lastname AS staff_last,'
    . ' tu.firstname AS target_first, tu.lastname AS target_last, tu.email AS target_email'
    . ' FROM org_boss_reports r'
    . ' JOIN organizations o ON o.id = r.organization_id AND o.empresa_id = r.empresa_id'
    . ' LEFT JOIN staff s ON s.id = r.staff_id'
    . ' LEFT JOIN users tu ON tu.id = r.target_user_id'
    . ' WHERE r.id = ? AND r.empresa_id = ? LIMIT 1'
);
if (!$stmt) {
    header('Location: informes-jefes.php');
    exit;
}
$stmt->bind_param('ii', $reportId, $eid);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
if (!$report) {
    header('Location: informes-jefes.php');
    exit;
}

markOrgBossReportRead($mysqli, $reportId, $uid);

$attachments = [];
$stmtA = $mysqli->prepare('SELECT id, original_filename, mimetype, size, created_at FROM org_boss_report_attachments WHERE report_id = ? AND empresa_id = ? ORDER BY id ASC');
if ($stmtA) {
    $stmtA->bind_param('ii', $reportId, $eid);
    $stmtA->execute();
    $resA = $stmtA->get_result();
    while ($resA && ($row = $resA->fetch_assoc())) {
        $attachments[] = $row;
    }
}

$isDarkMode = (int) ($_SESSION['client_dark_mode'] ?? 0) === 1;
$staffName = trim((string) ($report['staff_first'] ?? '') . ' ' . (string) ($report['staff_last'] ?? ''));
$targetName = trim((string) ($report['target_first'] ?? '') . ' ' . (string) ($report['target_last'] ?? ''));
$created = (string) ($report['created_at'] ?? '');
$bodyHtml = (string) ($report['body_html'] ?? '');
$companyLogoUrl = (string) getCompanyLogoUrl('publico/img/vigitec-logo.webp');

function formatFileSizeHuman(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / 1048576, 1) . ' MB';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo html((string) ($report['subject'] ?? 'Informe')); ?> — <?php echo html(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/client_dark.css?v=<?php echo (int) @filemtime(__DIR__ . '/css/client_dark.css'); ?>">
    <style>
        body { background:#f8fafc; padding-top:72px; }
        .topbar { background:linear-gradient(135deg,#0f172a,#1e293b); }
        .shell { max-width:860px; margin:0 auto; padding:0 16px 40px; }
        .panel { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; }
        .meta { color:#64748b; font-size:.9rem; }
        .body-content { line-height:1.6; color:#334155; }
        .body-content img { max-width:100%; height:auto; border-radius:8px; }
        .att-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border:1px solid #e2e8f0; border-radius:10px; text-decoration:none; color:inherit; margin-bottom:8px; }
        .att-item:hover { background:#f8fafc; }
    </style>
</head>
<body class="<?php echo $isDarkMode ? 'dark-mode' : ''; ?>">
<nav class="navbar navbar-dark topbar fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="tickets.php"><img src="<?php echo html($companyLogoUrl); ?>" alt="Logo" height="32"></a>
        <a href="informes-jefes.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Informes</a>
    </div>
</nav>

<div class="shell">
    <div class="panel">
        <h2 class="h4 mb-2"><?php echo html((string) ($report['subject'] ?? '')); ?></h2>
        <div class="meta mb-4">
            <div><strong>Organización:</strong> <?php echo html((string) ($report['org_name'] ?? '')); ?></div>
            <?php if ($targetName !== ''): ?>
            <div><strong>Usuario referencia:</strong> <?php echo html($targetName); ?><?php if (!empty($report['target_email'])): ?> (<?php echo html((string) $report['target_email']); ?>)<?php endif; ?></div>
            <?php endif; ?>
            <?php if ($staffName !== ''): ?>
            <div><strong>Enviado por:</strong> <?php echo html($staffName); ?></div>
            <?php endif; ?>
            <?php if ($created !== ''): ?>
            <div><strong>Fecha:</strong> <?php echo html(date('d/m/Y H:i', strtotime($created))); ?></div>
            <?php endif; ?>
        </div>

        <div class="body-content mb-4">
            <?php echo $bodyHtml; ?>
        </div>

        <?php if (!empty($attachments)): ?>
        <h3 class="h6 text-uppercase text-muted mb-3">Archivos adjuntos</h3>
        <?php foreach ($attachments as $att): ?>
        <?php
        $mime = (string) ($att['mimetype'] ?? '');
        $isImage = str_starts_with($mime, 'image/');
        $icon = $isImage ? 'bi-image' : 'bi-paperclip';
        ?>
        <a class="att-item" href="informe-jefe.php?id=<?php echo $reportId; ?>&download=<?php echo (int) $att['id']; ?>">
            <i class="bi <?php echo $icon; ?> fs-5 text-primary"></i>
            <div class="flex-grow-1">
                <div class="fw-semibold"><?php echo html((string) ($att['original_filename'] ?? 'archivo')); ?></div>
                <div class="small text-muted"><?php echo html(formatFileSizeHuman((int) ($att['size'] ?? 0))); ?></div>
            </div>
            <i class="bi bi-download text-muted"></i>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
