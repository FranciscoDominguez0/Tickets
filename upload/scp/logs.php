<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
$staff = getCurrentUser();
$currentRoute = 'logs';

$errors = [];
$msg = '';
$warn = '';

// Asegurar CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Acciones POST (borrado masivo)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $errors['err'] = 'Token de seguridad inválido.';
    } else {
        $do = strtolower((string)($_POST['do'] ?? ''));
        if ($do === 'mass_process') {
            $ids = $_POST['ids'] ?? [];
            $a = strtolower((string)($_POST['a'] ?? ''));
            if (!$ids || !is_array($ids) || !count($ids)) {
                $errors['err'] = 'Debe seleccionar al menos un registro.';
            } elseif ($a !== 'delete') {
                $errors['err'] = 'Acción desconocida.';
            } else {
                $ids = array_values(array_filter($ids, function ($v) {
                    return is_numeric($v) && (int)$v > 0;
                }));
                if (!count($ids)) {
                    $errors['err'] = 'Debe seleccionar al menos un registro.';
                } else {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $types = str_repeat('i', count($ids));
                    $stmt = $mysqli->prepare("DELETE FROM logs WHERE id IN ($placeholders)");
                    if (!$stmt) {
                        $errors['err'] = 'No se pudo preparar la operación.';
                    } else {
                        $stmt->bind_param($types, ...array_map('intval', $ids));
                        if ($stmt->execute()) {
                            $num = (int)$stmt->affected_rows;
                            $count = count($ids);
                            if ($num === $count) {
                                $msg = 'Registros eliminados correctamente.';
                            } else {
                                $warn = "$num de $count registros eliminados.";
                            }
                        } else {
                            $errors['err'] = 'No se pudieron eliminar los registros.';
                        }
                    }
                }
            }
        }
    }
}

// Filtros
$q = trim((string)($_GET['q'] ?? ''));
$level = trim((string)($_GET['level'] ?? ''));
$date_from = trim((string)($_GET['from'] ?? ''));
$date_to = trim((string)($_GET['to'] ?? ''));
$page = isset($_GET['p']) && is_numeric($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$pageSize = 25;
$offset = ($page - 1) * $pageSize;

$where = [];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = '(action LIKE ? OR object_type LIKE ? OR details LIKE ? OR ip_address LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'ssss';
}
if ($level !== '' && in_array($level, ['Error', 'Warning', 'Info'], true)) {
    $where[] = "(CASE WHEN (LOWER(action) LIKE '%error%' OR LOWER(details) LIKE '%error%') THEN 'Error' WHEN (LOWER(action) LIKE '%warn%' OR LOWER(details) LIKE '%warn%') THEN 'Warning' ELSE 'Info' END) = ?";
    $params[] = $level;
    $types .= 's';
}
if ($date_from !== '') {
    $ts = strtotime($date_from . ' 00:00:00');
    if ($ts) {
        $where[] = 'created >= ?';
        $params[] = date('Y-m-d H:i:s', $ts);
        $types .= 's';
    }
}
if ($date_to !== '') {
    $ts = strtotime($date_to . ' 23:59:59');
    if ($ts) {
        $where[] = 'created <= ?';
        $params[] = date('Y-m-d H:i:s', $ts);
        $types .= 's';
    }
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count total
$total = 0;
$sqlCount = "SELECT COUNT(*) AS c FROM logs $whereSql";
$stmtC = $mysqli->prepare($sqlCount);
if ($stmtC) {
    if ($types !== '') {
        $stmtC->bind_param($types, ...$params);
    }
    $stmtC->execute();
    $total = (int)($stmtC->get_result()->fetch_assoc()['c'] ?? 0);
}

// Data
$rows = [];
$sql = "SELECT id, action, object_type, object_id, user_type, user_id, details, ip_address, created FROM logs $whereSql ORDER BY created DESC LIMIT ? OFFSET ?";
$stmt = $mysqli->prepare($sql);
if ($stmt) {
    $bindParams = $params;
    $bindTypes = $types;
    $bindParams[] = $pageSize;
    $bindParams[] = $offset;
    $bindTypes .= 'ii';
    $stmt->bind_param($bindTypes, ...$bindParams);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
}

$totalPages = (int)ceil(max(1, $total) / $pageSize);
$mkUrl = function ($overrides = []) {
    $qs = array_merge($_GET, $overrides);
    foreach ($qs as $k => $v) {
        if ($v === '' || $v === null) unset($qs[$k]);
    }
    return 'logs.php' . (count($qs) ? ('?' . http_build_query($qs)) : '');
};

ob_start();
?>
<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-graph-up"></i></span>
            <div>
                <h1>Registros del Sistema</h1>
                <p>Auditoría de acciones y eventos</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-info"><?php echo (int)$total; ?> Total</span>
        </div>
    </div>
</div>

<?php if (!empty($errors['err'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo html($errors['err']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($warn): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo html($warn); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?php echo html($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card settings-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-list-ul"></i> Registros</strong>
        <button type="button" id="openDeleteLogsModalBtn" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteLogsModal">
            <i class="bi bi-trash"></i> Eliminar seleccionados
        </button>
    </div>
    <div class="card-body">
        <form method="get" action="logs.php" class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label mb-1">Entre:</label>
                <input type="date" name="from" value="<?php echo html($date_from); ?>" class="form-control form-control-sm">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1">Hasta:</label>
                <input type="date" name="to" value="<?php echo html($date_to); ?>" class="form-control form-control-sm">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1">Nivel de registro:</label>
                <select name="level" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="Error" <?php echo ($level === 'Error') ? 'selected' : ''; ?>>Error</option>
                    <option value="Warning" <?php echo ($level === 'Warning') ? 'selected' : ''; ?>>Warning</option>
                    <option value="Info" <?php echo ($level === 'Info') ? 'selected' : ''; ?>>Info</option>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                <a href="logs.php" class="btn btn-outline-secondary btn-sm">Limpiar</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <form id="massDeleteForm" method="post" action="<?php echo html($mkUrl()); ?>" class="m-0">
            <?php csrfField(); ?>
            <input type="hidden" name="do" value="mass_process">
            <input type="hidden" name="a" value="delete">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 44px;" class="text-center"><input class="form-check-input" type="checkbox" id="checkAll"></th>
                        <th>Título de registro</th>
                        <th style="width: 140px;">Tipo de registro</th>
                        <th style="width: 170px;">Fecha de registro</th>
                        <th style="width: 130px;">Dirección IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!count($rows)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No hay registros para los filtros seleccionados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $txt = (string)($r['details'] ?? '');
                            $title = (string)($r['action'] ?? '');
                            $lower = strtolower((string)($r['action'] ?? '') . ' ' . $txt);
                            if (strpos($lower, 'error') !== false) $lvl = 'Error';
                            elseif (strpos($lower, 'warn') !== false) $lvl = 'Warning';
                            else $lvl = 'Info';
                            $lvlClass = ($lvl === 'Error') ? 'text-danger fw-bold' : (($lvl === 'Warning') ? 'text-warning fw-bold' : 'text-muted');

                            $popoverTitle = (string)($r['action'] ?? 'Registro');
                            $popoverBody =
                                "Detalles: " . ($txt !== '' ? $txt : '-') . "\n\n"
                                . "Fecha: " . formatDate($r['created']) . "\n"
                                . "IP: " . ($r['ip_address'] ?: '-') . "\n"
                                . "Usuario: " . (($r['user_type'] ?: '-') . ($r['user_id'] ? (' #' . (int)$r['user_id']) : '')) . "\n"
                                . "Objeto: " . (($r['object_type'] ?: '-') . ($r['object_id'] ? (' #' . (int)$r['object_id']) : ''));

                            $popTitleB64 = base64_encode($popoverTitle);
                            $popBodyB64 = base64_encode($popoverBody);
                            ?>
                            <tr>
                                <td class="text-center"><input class="form-check-input row-check" type="checkbox" name="ids[]" value="<?php echo (int)$r['id']; ?>"></td>
                                <td>
                                    <a href="#" class="log-pop" tabindex="0" data-pop-title="<?php echo html($popTitleB64); ?>" data-pop-body="<?php echo html($popBodyB64); ?>" style="text-decoration:none; display:block;">
                                        <div class="text-truncate" style="max-width: 720px;" title="<?php echo html($title); ?>">
                                            <?php echo html($title); ?>
                                        </div>
                                    </a>
                                </td>
                                <td class="<?php echo $lvlClass; ?>"><?php echo html($lvl); ?></td>
                                <td style="white-space:nowrap;"><?php echo html(formatDate($r['created'])); ?></td>
                                <td style="white-space:nowrap;"><code><?php echo html($r['ip_address'] ?: '-'); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </form>
    </div>

    <div class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="text-muted small">Total: <strong><?php echo (int)$total; ?></strong></div>
        <nav aria-label="Paginación">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo html($mkUrl(['p' => max(1, $page - 1)])); ?>">&laquo;</a>
                </li>
                <li class="page-item disabled"><span class="page-link"><?php echo (int)$page; ?> / <?php echo (int)$totalPages; ?></span></li>
                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo html($mkUrl(['p' => min($totalPages, $page + 1)])); ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
    </div>
</div>

<div class="modal fade" id="deleteLogsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteLogsModalTitle">
                    <i class="bi bi-exclamation-triangle text-danger"></i> Confirmar eliminación
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="deleteLogsModalBody">
                <p class="mb-0">¿Está seguro de que desea eliminar los registros seleccionados?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteLogsBtn">
                    <i class="bi bi-trash"></i> Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    function getSelectedCount(){
        return document.querySelectorAll('.row-check:checked').length;
    }
    var openBtn = document.getElementById('openDeleteLogsModalBtn');
    var modalEl = document.getElementById('deleteLogsModal');
    var bodyEl = document.getElementById('deleteLogsModalBody');
    var confirmBtn = document.getElementById('confirmDeleteLogsBtn');
    if (!openBtn || !modalEl) return;

    openBtn.addEventListener('click', function(e){
        e.preventDefault();
        var n = getSelectedCount();
        if (bodyEl) {
            if (n <= 0) {
                bodyEl.innerHTML = '<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Debe seleccionar al menos un log.</div>';
            } else {
                bodyEl.innerHTML = '<p class="mb-0">¿Está seguro de que desea eliminar <strong>' + n + '</strong> log(s) seleccionado(s)? Esta acción no se puede deshacer.</p>';
            }
        }
        if (confirmBtn) confirmBtn.style.display = n > 0 ? '' : 'none';

        if (window.bootstrap && window.bootstrap.Modal) {
            try {
                if (typeof window.bootstrap.Modal.getOrCreateInstance === 'function') {
                    window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
                } else {
                    new window.bootstrap.Modal(modalEl).show();
                }
            } catch (err) {}
        }
    });
})();
</script>

<?php
$content = ob_get_clean();

require_once 'layout_admin.php';