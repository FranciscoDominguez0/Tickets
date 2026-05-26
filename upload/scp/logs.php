<?php
require_once '../../config.php';
require_once '../../includes/helpers.php';
require_once '../../includes/Auth.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

requireLogin('agente');
requireRolePermission('admin.access');
$staff = getCurrentUser();
$currentRoute = 'logs';

$eid = empresaId();
$logsHasEmpresaId = false;
if (isset($mysqli) && $mysqli) {
    try {
        $res = $mysqli->query("SHOW COLUMNS FROM logs LIKE 'empresa_id'");
        $logsHasEmpresaId = ($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        $logsHasEmpresaId = false;
    }
}

$errors = [];
$msg = '';
$warn = '';

// Asegurar CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Acciones POST (borrado masivo / vaciar todo)
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
                    $sqlDelete = "DELETE FROM logs WHERE id IN ($placeholders)";
                    if ($logsHasEmpresaId) {
                        $sqlDelete .= ' AND empresa_id = ?';
                        $types .= 'i';
                    }
                    $stmt = $mysqli->prepare($sqlDelete);
                    if (!$stmt) {
                        $errors['err'] = 'No se pudo preparar la operación.';
                    } else {
                        $bind = array_map('intval', $ids);
                        if ($logsHasEmpresaId) {
                            $bind[] = (int)$eid;
                        }
                        $stmt->bind_param($types, ...$bind);
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

        if ($do === 'empty_all') {
            if ($logsHasEmpresaId) {
                $stmt = $mysqli->prepare('DELETE FROM logs WHERE empresa_id = ?');
                if ($stmt) {
                    $stmt->bind_param('i', $eid);
                    if ($stmt->execute()) {
                        $n = (int)$stmt->affected_rows;
                        $msg = $n > 0
                            ? "Se eliminaron {$n} registro(s). La bitácora quedó vacía."
                            : 'No había registros para eliminar.';
                    } else {
                        $errors['err'] = 'No se pudo vaciar el registro de logs.';
                    }
                } else {
                    $errors['err'] = 'No se pudo preparar la operación.';
                }
            } else {
                if ($mysqli->query('DELETE FROM logs')) {
                    $n = (int)$mysqli->affected_rows;
                    $msg = $n > 0
                        ? "Se eliminaron {$n} registro(s). La bitácora quedó vacía."
                        : 'No había registros para eliminar.';
                } else {
                    $errors['err'] = 'No se pudo vaciar el registro de logs.';
                }
            }
        }
    }
}
$q = trim((string)($_GET['q'] ?? ''));
$level = trim((string)($_GET['level'] ?? ''));
$date_from = trim((string)($_GET['from'] ?? ''));
$date_to = trim((string)($_GET['to'] ?? ''));
$page = isset($_GET['p']) && is_numeric($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$pageSize = 10;
$offset = ($page - 1) * $pageSize;

$where = [];
$params = [];
$types = '';

if ($logsHasEmpresaId) {
    $where[] = 'empresa_id = ?';
    $params[] = (int)$eid;
    $types .= 'i';
}

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
            <span class="settings-hero-icon"><i class="bi bi-terminal"></i></span>
            <div>
                <h1>Registros del Sistema</h1>
                <p>Auditoría de acciones y eventos</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge" style="font-weight: 700; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; background: rgba(239, 68, 68, 0.1) !important; color: #ef4444 !important; border: 1px solid rgba(239, 68, 68, 0.2);"><i class="bi bi-activity me-1"></i><?php echo (int)$total; ?> Total</span>
        </div>
    </div>
</div>

<?php if (!empty($errors['err'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius: 12px; border-left: 4px solid #dc2626;">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo html($errors['err']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($warn): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert" style="border-radius: 12px; border-left: 4px solid #d97706;">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo html($warn); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius: 12px; border-left: 4px solid #16a34a;">
        <i class="bi bi-check-circle me-2"></i><?php echo html($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Buscador y Filtros Avanzados -->
<div class="search-card">
    <form method="get" action="logs.php" class="m-0">
        <div class="search-wrap">
            <div class="position-relative flex-grow-1">
                <i class="bi bi-search position-absolute top-50 translate-middle-y text-muted" style="left: 16px;"></i>
                <input type="text" name="q" value="<?php echo html($q); ?>" class="form-control shadow-none" placeholder="Buscar registros por acción, detalles, IP..." style="padding-left: 44px; border-radius: 12px; height: 46px; border: 1px solid #cbd5e1; font-weight: 500;">
            </div>
            <button type="submit" class="btn btn-primary px-4 d-flex align-items-center gap-2" style="border-radius: 12px; height: 46px; font-weight: 600; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border: none;">
                <i class="bi bi-search"></i> <span class="d-none d-sm-inline">Buscar</span>
            </button>
            <button type="button" class="btn btn-outline-secondary px-3 d-flex align-items-center gap-2" data-bs-toggle="collapse" data-bs-target="#advancedFilters" aria-expanded="<?php echo ($date_from || $date_to || $level) ? 'true' : 'false'; ?>" style="border-radius: 12px; height: 46px; font-weight: 600; border-color: #cbd5e1;">
                <i class="bi bi-sliders"></i> <span class="d-none d-sm-inline">Filtros</span>
            </button>
        </div>
        
        <div class="collapse <?php echo ($date_from || $date_to || $level) ? 'show' : ''; ?>" id="advancedFilters">
            <div class="row g-3 mt-2 pt-3" style="border-top: 1px dashed rgba(148,163,184,0.15);">
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-secondary">Entre:</label>
                    <input type="date" name="from" value="<?php echo html($date_from); ?>" class="form-control" style="border-radius:10px;">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-secondary">Hasta:</label>
                    <input type="date" name="to" value="<?php echo html($date_to); ?>" class="form-control" style="border-radius:10px;">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-secondary">Nivel de registro:</label>
                    <select name="level" class="form-select" style="border-radius:10px;">
                        <option value="">Todos</option>
                        <option value="Error" <?php echo ($level === 'Error') ? 'selected' : ''; ?>>Error</option>
                        <option value="Warning" <?php echo ($level === 'Warning') ? 'selected' : ''; ?>>Warning</option>
                        <option value="Info" <?php echo ($level === 'Info') ? 'selected' : ''; ?>>Info</option>
                    </select>
                </div>
                <?php if ($q || $date_from || $date_to || $level): ?>
                    <div class="col-12 text-end mt-2">
                        <a href="logs.php" class="btn btn-sm btn-outline-secondary px-3" style="border-radius: 8px; font-weight: 600;">
                            <i class="bi bi-x-circle me-1"></i>Limpiar Filtros
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<div class="card settings-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-list-ul"></i> Bitácora de Auditoría</strong>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <button type="button" id="openDeleteLogsModalBtn" class="btn btn-outline-danger btn-sm px-3 d-flex align-items-center gap-1" style="border-radius: 8px; font-weight: 600; transition: all 0.2s;">
                <i class="bi bi-trash"></i> Eliminar seleccionados
            </button>
            <button type="button" class="btn btn-danger btn-sm px-3 d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#emptyAllLogsModal" style="border-radius: 8px; font-weight: 600; background: #ef4444; border: none; transition: all 0.2s;">
                <i class="bi bi-trash3"></i> Vaciar todo
            </button>
        </div>
    </div>

    <form id="emptyAllLogsForm" method="post" action="logs.php" class="d-none" aria-hidden="true">
        <?php csrfField(); ?>
        <input type="hidden" name="do" value="empty_all">
    </form>

    <div class="table-responsive">
        <form id="massDeleteForm" method="post" action="<?php echo html($mkUrl()); ?>" class="m-0">
            <?php csrfField(); ?>
            <input type="hidden" name="do" value="mass_process">
            <input type="hidden" name="a" value="delete">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light" style="border-bottom: 2px solid #e2e8f0; background-color: #f8fafc;">
                    <tr>
                        <th style="width: 48px;" class="text-center">
                            <input class="form-check-input" type="checkbox" id="checkAll" style="width: 1.2rem; height: 1.2rem; border-radius: 4px;">
                        </th>
                        <th style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Evento / Acción</th>
                        <th style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; width: 140px;">Severidad</th>
                        <th style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; width: 180px;">Fecha y Hora</th>
                        <th style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; width: 150px;">Origen IP</th>
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
                                <!-- VISTA MÓVIL (Tarjeta Premium) -->
                                <td class="d-md-none p-0">
                                    <div style="padding: 14px; background: #ffffff; position: relative;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <input class="form-check-input row-check m-0 shadow-sm" type="checkbox" name="ids[]" value="<?php echo (int)$r['id']; ?>" style="width: 1.25rem; height: 1.25rem; cursor: pointer;">
                                                <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                                    <?php echo date('d M Y, H:i', strtotime($r['created'])); ?>
                                                </div>
                                            </div>
                                            <div>
                                                <?php 
                                                if ($lvl === 'Error') echo '<span class="badge-lvl lvl-error"><i class="bi bi-x-octagon me-1"></i>Error</span>';
                                                elseif ($lvl === 'Warning') echo '<span class="badge-lvl lvl-warning"><i class="bi bi-exclamation-triangle me-1"></i>Warn</span>';
                                                else echo '<span class="badge-lvl lvl-info"><i class="bi bi-info-circle me-1"></i>Info</span>';
                                                ?>
                                            </div>
                                        </div>

                                        <div style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin-bottom: 12px; line-height: 1.4;">
                                            <a href="#" class="log-pop text-decoration-none" tabindex="0" data-pop-title="<?php echo html($popTitleB64); ?>" data-pop-body="<?php echo html($popBodyB64); ?>" style="color: inherit;">
                                                <?php echo html(ucwords(str_replace(['_', '-'], ' ', $title))); ?>
                                            </a>
                                        </div>

                                        <div class="d-flex align-items-center justify-content-between mt-2 pt-3" style="border-top: 1px dashed #e2e8f0;">
                                            <div style="font-size: 0.75rem; color: #475569; font-family: monospace; background: #f1f5f9; padding: 3px 8px; border-radius: 6px; font-weight: 600;" class="log-ip-box">
                                                <i class="bi bi-hdd-network me-1"></i><?php echo html($r['ip_address'] ?: '-'); ?>
                                            </div>
                                            <a href="#" class="log-pop btn btn-sm" tabindex="0" data-pop-title="<?php echo html($popTitleB64); ?>" data-pop-body="<?php echo html($popBodyB64); ?>" style="color: #2563eb; background: rgba(37,99,235,0.08); border-radius: 8px; font-weight: 800; font-size: 0.75rem; padding: 4px 12px; transition: background 0.2s;">
                                                Detalles <i class="bi bi-chevron-right ms-1"></i>
                                            </a>
                                        </div>
                                    </div>
                                </td>

                                <!-- VISTA ESCRITORIO -->
                                <td class="text-center d-none d-md-table-cell">
                                    <input class="form-check-input row-check" type="checkbox" name="ids[]" value="<?php echo (int)$r['id']; ?>" style="width: 1.2rem; height: 1.2rem; border-radius: 4px;">
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <?php if ($lvl === 'Error'): ?>
                                            <span style="font-size: 1.1rem; line-height: 1;"><i class="bi bi-x-octagon-fill text-danger"></i></span>
                                        <?php elseif ($lvl === 'Warning'): ?>
                                            <span style="font-size: 1.1rem; line-height: 1;"><i class="bi bi-exclamation-triangle-fill text-warning"></i></span>
                                        <?php else: ?>
                                            <span style="font-size: 1.1rem; line-height: 1;"><i class="bi bi-info-circle-fill text-info"></i></span>
                                        <?php endif; ?>
                                        <a href="#" class="log-pop text-decoration-none" tabindex="0" data-pop-title="<?php echo html($popTitleB64); ?>" data-pop-body="<?php echo html($popBodyB64); ?>" style="color: #1e293b; font-weight: 700; font-size: 0.92rem; display: block; flex: 1; min-width: 0;">
                                            <div class="text-truncate log-desktop-title" style="max-width: 650px;" title="<?php echo html($title); ?>">
                                                <?php echo html(ucwords(str_replace(['_', '-'], ' ', $title))); ?>
                                            </div>
                                        </a>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php 
                                    if ($lvl === 'Error') echo '<span class="badge-lvl lvl-error"><i class="bi bi-x-octagon me-1"></i>Error</span>';
                                    elseif ($lvl === 'Warning') echo '<span class="badge-lvl lvl-warning"><i class="bi bi-exclamation-triangle me-1"></i>Warn</span>';
                                    else echo '<span class="badge-lvl lvl-info"><i class="bi bi-info-circle me-1"></i>Info</span>';
                                    ?>
                                </td>
                                <td style="white-space:nowrap; font-weight: 500; font-size: 0.85rem; color: #475569;" class="d-none d-md-table-cell agent-desktop-date">
                                    <i class="bi bi-clock me-1 text-muted" style="font-size:0.8rem;"></i> <?php echo html(formatDate($r['created'])); ?>
                                </td>
                                <td style="white-space:nowrap;" class="d-none d-md-table-cell">
                                    <span class="log-ip"><code><?php echo html($r['ip_address'] ?: '-'); ?></code></span>
                                </td>
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

<div class="modal fade" id="emptyAllLogsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="bi bi-exclamation-octagon"></i> Vaciar toda la bitácora
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Se eliminarán <strong>todos</strong> los registros de auditoría<?php echo $logsHasEmpresaId ? ' de <strong>esta empresa</strong>' : ''; ?>, incluidos los que no ves por filtros o paginación.</p>
                <p class="mb-0 text-muted small">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmEmptyAllLogsBtn">
                    <i class="bi bi-trash3"></i> Sí, vaciar todo
                </button>
            </div>
        </div>
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
    var massForm = document.getElementById('massDeleteForm');
    var emptyForm = document.getElementById('emptyAllLogsForm');
    var confirmEmptyBtn = document.getElementById('confirmEmptyAllLogsBtn');

    if (confirmBtn && massForm) {
        confirmBtn.addEventListener('click', function () {
            if (getSelectedCount() <= 0) return;
            massForm.submit();
        });
    }

    if (confirmEmptyBtn && emptyForm) {
        confirmEmptyBtn.addEventListener('click', function () {
            emptyForm.submit();
        });
    }

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

<style>
/* ── Premium Search Card ── */
.search-card {
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 16px;
    padding: 20px 24px;
    box-shadow: 0 4px 20px rgba(30, 58, 138, 0.06);
    border: 1px solid rgba(30, 64, 175, 0.08);
    margin-bottom: 24px;
}
.search-card .search-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
}
body.dark-mode .search-card {
    background: linear-gradient(145deg, #18181b 0%, #09090b 100%) !important;
    border-color: #27272a !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
}
body.dark-mode .search-card input,
body.dark-mode .search-card select {
    background-color: #09090b !important;
    border-color: #27272a !important;
    color: #f4f4f5 !important;
}
body.dark-mode .search-card input::placeholder {
    color: #52525b !important;
}

/* ── Dynamic Tooltip/Tip Popover ── */
#log-tip {
    font-family: inherit;
    transition: opacity 0.15s ease;
}
body.dark-mode #log-tip {
    background: #18181b !important;
    border-color: #27272a !important;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5) !important;
}
body.dark-mode #log-tip-title {
    color: #f4f4f5 !important;
}
body.dark-mode #log-tip-body {
    color: #d4d4d8 !important;
}

/* ── Table Typography & Elements ── */
.log-desktop-title {
    color: #0f172a;
    transition: color 0.15s ease;
}
.log-desktop-title:hover {
    color: #ef4444;
}
body.dark-mode .log-desktop-title {
    color: #e4e4e7 !important;
}
body.dark-mode .log-desktop-title:hover {
    color: #f87171 !important;
}
body.dark-mode .agent-desktop-date {
    color: #a1a1aa !important;
}

/* ── Log Badges ── */
.badge-lvl {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.badge-lvl.lvl-error {
    background: rgba(239, 68, 68, 0.06) !important;
    color: #ef4444 !important;
    border: 1px solid rgba(239, 68, 68, 0.15);
}
.badge-lvl.lvl-warning {
    background: rgba(245, 158, 11, 0.06) !important;
    color: #f59e0b !important;
    border: 1px solid rgba(245, 158, 11, 0.15);
}
.badge-lvl.lvl-info {
    background: rgba(59, 130, 246, 0.06) !important;
    color: #3b82f6 !important;
    border: 1px solid rgba(59, 130, 246, 0.15);
}

body.dark-mode .badge-lvl.lvl-error {
    background: rgba(239, 68, 68, 0.12) !important;
    color: #f87171 !important;
    border-color: rgba(239, 68, 68, 0.25);
}
body.dark-mode .badge-lvl.lvl-warning {
    background: rgba(245, 158, 11, 0.12) !important;
    color: #fbbf24 !important;
    border-color: rgba(245, 158, 11, 0.25);
}
body.dark-mode .badge-lvl.lvl-info {
    background: rgba(59, 130, 246, 0.12) !important;
    color: #60a5fa !important;
    border-color: rgba(59, 130, 246, 0.25);
}

/* ── IP Styles ── */
.log-ip code {
    font-family: var(--bs-font-monospace);
    background: #f1f5f9;
    color: #475569;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    border: 1px solid #e2e8f0;
}
body.dark-mode .log-ip code {
    background: #27272a !important;
    color: #cbd5e1 !important;
    border-color: #3f3f46 !important;
}

/* Responsive Table -> Cards for Mobile */
@media (max-width: 768px) {
    .settings-card { background: transparent !important; box-shadow: none !important; }
    .settings-card .card-header { border-radius: 12px; margin-bottom: 12px; }
    .settings-card .table-responsive { border: none !important; overflow: visible; }
    .settings-card .table { background: transparent; }
    .settings-card .table thead { display: none; }
    .settings-card .table tbody tr {
        display: block;
        margin-bottom: 1rem;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .settings-card .table tbody td {
        display: block;
        border: none !important;
        padding: 0 !important;
    }
    body.dark-mode .settings-card .table tbody tr {
        background: #18181b !important;
        border-color: #27272a !important;
    }
    body.dark-mode .settings-card .table tbody tr td div {
        background-color: transparent !important;
    }
}
</style>

<?php
$content = ob_get_clean();

require_once 'layout_admin.php';