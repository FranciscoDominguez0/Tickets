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
<div class="page-header">
    <h1>Registros del Sistema</h1>
    <p>Auditoría de acciones y eventos</p>
</div>

<?php if (!empty($errors['err'])): ?>
    <div class="alert alert-danger"><?php echo html($errors['err']); ?></div>
<?php endif; ?>
<?php if ($warn): ?>
    <div class="alert alert-warning"><?php echo html($warn); ?></div>
<?php endif; ?>
<?php if ($msg): ?>
    <div class="alert alert-success"><?php echo html($msg); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="d-flex align-items-end justify-content-between flex-wrap gap-2">
            <form method="get" action="logs.php" class="d-flex align-items-end flex-wrap gap-2">
                <div>
                    <label class="form-label mb-1">Entre:</label>
                    <input type="date" name="from" value="<?php echo html($date_from); ?>" class="form-control form-control-sm">
                </div>
                <div>
                    <label class="form-label mb-1">&nbsp;</label>
                    <input type="date" name="to" value="<?php echo html($date_to); ?>" class="form-control form-control-sm">
                </div>
                <div>
                    <label class="form-label mb-1">Nivel de registro:</label>
                    <select name="level" class="form-select form-select-sm" style="min-width: 170px;">
                        <option value="">Todos</option>
                        <option value="Error" <?php echo ($level === 'Error') ? 'selected' : ''; ?>>Error</option>
                        <option value="Warning" <?php echo ($level === 'Warning') ? 'selected' : ''; ?>>Warning</option>
                        <option value="Info" <?php echo ($level === 'Info') ? 'selected' : ''; ?>>Info</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary btn-sm">¡Vamos!</button>
                    <a href="logs.php" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                </div>
            </form>

            <button type="submit" form="massDeleteForm" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Eliminar las entradas seleccionadas?');">
                <i class="bi bi-trash"></i> Eliminar las entradas seleccionadas
            </button>
        </div>
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

<script>
window.addEventListener('load', function () {
    var all = document.getElementById('checkAll');
    if (all) {
        all.addEventListener('change', function () {
            document.querySelectorAll('.row-check').forEach(function (cb) {
                cb.checked = all.checked;
            });
        });
    }

    function b64decode(v) {
        try {
            var s = atob(v || '');
            try { return decodeURIComponent(escape(s)); } catch (e2) { return s; }
        } catch (e) {
            return '';
        }
    }

    var tip = document.createElement('div');
    tip.id = 'log-tip';
    tip.style.position = 'fixed';
    tip.style.zIndex = '2000';
    tip.style.maxWidth = '520px';
    tip.style.background = '#fff';
    tip.style.border = '1px solid rgba(148,163,184,0.7)';
    tip.style.borderRadius = '10px';
    tip.style.boxShadow = '0 14px 34px rgba(15,23,42,0.18)';
    tip.style.padding = '10px 12px';
    tip.style.display = 'none';
    tip.style.pointerEvents = 'none';
    tip.innerHTML = '<div id="log-tip-title" style="font-weight:900; color:#0f172a; margin-bottom:6px;"></div>'
        + '<div id="log-tip-body" style="white-space:pre-wrap; color:#334155; font-size:0.92rem; line-height:1.35;"></div>';
    document.body.appendChild(tip);

    function showTip(el) {
        var t = b64decode(el.getAttribute('data-pop-title'));
        var b = b64decode(el.getAttribute('data-pop-body'));
        var tEl = document.getElementById('log-tip-title');
        var bEl = document.getElementById('log-tip-body');
        if (tEl) tEl.textContent = t || 'Registro';
        if (bEl) bEl.textContent = b || '';
        tip.style.display = 'block';
    }
    function hideTip() {
        tip.style.display = 'none';
    }
    function placeTipNear(el) {
        if (tip.style.display === 'none') return;
        var pad = 12;
        var r = el.getBoundingClientRect();
        var rect = tip.getBoundingClientRect();
        var x = r.right + pad;
        var y = r.top;
        if (x + rect.width > window.innerWidth - 10) x = Math.max(10, r.left - rect.width - pad);
        if (y + rect.height > window.innerHeight - 10) y = Math.max(10, window.innerHeight - rect.height - 10);
        tip.style.left = x + 'px';
        tip.style.top = y + 'px';
    }

    document.querySelectorAll('a.log-pop').forEach(function (el) {
        el.addEventListener('click', function (e) { e.preventDefault(); });
        el.addEventListener('mouseenter', function () { showTip(el); placeTipNear(el); });
        el.addEventListener('mouseleave', function () { hideTip(); });
        el.addEventListener('focus', function () { showTip(el); placeTipNear(el); });
        el.addEventListener('blur', function () { hideTip(); });
    });

    window.addEventListener('scroll', function () {
        var active = document.activeElement;
        if (active && active.classList && active.classList.contains('log-pop') && tip.style.display !== 'none') {
            placeTipNear(active);
        }
    }, {passive:true});
});
</script>

<?php
$content = ob_get_clean();

require_once 'layout_admin.php';