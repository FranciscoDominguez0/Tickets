<?php
require_once '../../../config.php';
require_once '../../../includes/helpers.php';

ob_start();

$hasEmpresas = false;
$hasPagos = false;
$dbName = '';

if (isset($mysqli) && $mysqli) {
    try {
        $rdb = $mysqli->query('SELECT DATABASE() db');
        if ($rdb) {
            $dbName = (string)($rdb->fetch_assoc()['db'] ?? '');
        }

        $r1 = $mysqli->query('SELECT 1 FROM empresas LIMIT 1');
        $hasEmpresas = ($r1 !== false);

        $r2 = $mysqli->query('SELECT 1 FROM pagos_empresas LIMIT 1');
        $hasPagos = ($r2 !== false);
    } catch (Throwable $e) {}
}

if ($hasEmpresas) {
    syncAllEmpresasBillingStatus();
}

/* =========================
   REGISTRAR PAGO
   ========================= */
$mensaje = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'delete_payment' && $hasPagos) {
    $payId = isset($_POST['payment_id']) && is_numeric($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;
    if ($payId <= 0) {
        $error = 'Pago inválido.';
    } else {
        $stmtD = $mysqli->prepare('DELETE FROM pagos_empresas WHERE id = ?');
        if ($stmtD) {
            $stmtD->bind_param('i', $payId);
            if ($stmtD->execute()) {
                $mensaje = 'Pago eliminado.';
            } else {
                $error = 'No se pudo eliminar el pago.';
            }
        } else {
            $error = 'No se pudo preparar la operación.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasEmpresas && $hasPagos && (string)($_POST['action'] ?? '') !== 'delete_payment') {

    $empresa_id = (int)($_POST['empresa_id'] ?? 0);
    $monto = (float)($_POST['monto'] ?? 0);
    $metodo = trim($_POST['metodo_pago'] ?? '');
    $referencia = trim($_POST['referencia'] ?? '');

    if ($empresa_id <= 0 || $monto <= 0) {
        $error = "Datos inválidos";
    } else {
        $mysqli->begin_transaction();

        try {
            $q = $mysqli->query("SELECT fecha_vencimiento FROM empresas WHERE id = {$empresa_id} FOR UPDATE");
            $empresa = $q ? $q->fetch_assoc() : null;

            $hoy = date('Y-m-d');
            $base = $empresa['fecha_vencimiento'] ?? null;
            if (!$base || $base < $hoy) $base = $hoy;

            $nuevo_vencimiento = date('Y-m-d', strtotime($base . ' +1 month'));

            $stmt = $mysqli->prepare("
                INSERT INTO pagos_empresas
                (empresa_id, monto, fecha_pago, periodo_desde, periodo_hasta, metodo_pago, referencia)
                VALUES (?, ?, NOW(), ?, ?, ?, ?)
            ");
            $stmt->bind_param("idssss", $empresa_id, $monto, $base, $nuevo_vencimiento, $metodo, $referencia);
            $stmt->execute();

            $stmt2 = $mysqli->prepare("
                UPDATE empresas
                SET fecha_vencimiento = ?,
                    estado_pago = 'al_dia',
                    bloqueada = 0,
                    motivo_bloqueo = NULL
                WHERE id = ?
            ");
            $stmt2->bind_param("si", $nuevo_vencimiento, $empresa_id);
            $stmt2->execute();

            syncAllEmpresasBillingStatus();

            $mysqli->commit();
            $mensaje = "Pago registrado y servicio renovado";

        } catch (Throwable $e) {
            $mysqli->rollback();
            $error = "Error al registrar pago";
        }
    }
}

/* =========================
   DATOS PARA KPIs Y TABLAS
   ========================= */
$empresas = [];
$pagos = [];

$kpiVencen7 = null;
$kpiVencidas = null;
$kpiBloqueadas = null;
$kpiIngresosMes = null;

if ($hasEmpresas && isset($mysqli) && $mysqli) {

    $resK1 = $mysqli->query("
        SELECT COUNT(*) c
        FROM empresas
        WHERE fecha_vencimiento IS NOT NULL
          AND DATEDIFF(fecha_vencimiento, CURDATE()) BETWEEN 0 AND 7
    ");
    if ($resK1) $kpiVencen7 = (int)($resK1->fetch_assoc()['c'] ?? 0);

    $resK2 = $mysqli->query("SELECT COUNT(*) c FROM empresas WHERE estado_pago = 'vencido'");
    if ($resK2) $kpiVencidas = (int)($resK2->fetch_assoc()['c'] ?? 0);

    $resK3 = $mysqli->query("SELECT COUNT(*) c FROM empresas WHERE bloqueada = 1");
    if ($resK3) $kpiBloqueadas = (int)($resK3->fetch_assoc()['c'] ?? 0);

    $sqlE = "SELECT id, nombre, estado, fecha_vencimiento, estado_pago, bloqueada,
                    CASE
                        WHEN fecha_vencimiento IS NULL THEN NULL
                        ELSE DATEDIFF(fecha_vencimiento, CURDATE())
                    END AS dias_restantes
             FROM empresas
             ORDER BY fecha_vencimiento IS NULL, fecha_vencimiento ASC, nombre ASC
             LIMIT 100";
    $resE = $mysqli->query($sqlE);
    if ($resE) {
        while ($row = $resE->fetch_assoc()) $empresas[] = $row;
    }
}

if ($hasPagos && isset($mysqli) && $mysqli) {
    $resK4 = $mysqli->query("
        SELECT COALESCE(SUM(monto), 0) total
        FROM pagos_empresas
        WHERE fecha_pago >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
          AND fecha_pago < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
    ");
    if ($resK4) $kpiIngresosMes = (float)($resK4->fetch_assoc()['total'] ?? 0);

    $sqlP = "SELECT p.id, p.empresa_id, p.monto, p.fecha_pago, p.periodo_desde, p.periodo_hasta, p.metodo_pago, p.referencia,
                    e.nombre AS empresa_nombre
             FROM pagos_empresas p
             INNER JOIN empresas e ON e.id = p.empresa_id
             ORDER BY p.fecha_pago DESC, p.id DESC
             LIMIT 50";
    $resP = $mysqli->query($sqlP);
    if ($resP) {
        while ($row = $resP->fetch_assoc()) $pagos[] = $row;
    }
}
?>

<link rel="stylesheet" href="css/empresas.css">

<!-- ══ HEADER ══════════════════════════════════════════════ -->
<div class="emp-hero mb-1">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="hero-icon" style="background:#0d6efd;">
                <i class="bi bi-credit-card-2-front"></i>
            </div>
            <div>
                <h1>Facturación</h1>
                <p>Control mensual de pagos y estado del servicio</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3 py-2">
                <i class="bi bi-calendar3 me-1"></i><?php echo date('d M Y'); ?>
            </span>
            <?php if ($dbName !== ''): ?>
                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-3 py-2">
                    <i class="bi bi-database me-1"></i><?php echo html($dbName); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ══ ALERTAS ══════════════════════════════════════════════ -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mt-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
        <div><?php echo html($error); ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($mensaje): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mt-3" role="alert">
        <i class="bi bi-check-circle-fill flex-shrink-0"></i>
        <div><?php echo html($mensaje); ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ══ KPIs ═════════════════════════════════════════════════ -->
<p class="section-title"><i class="bi bi-speedometer2"></i> Resumen</p>

<div class="row g-3 mb-2">
    <?php
    $kpis = [
        ['icon' => 'bi-hourglass-split',    'label' => 'Vencen en ≤7 días', 'value' => $kpiVencen7,      'color' => 'secondary'],
        ['icon' => 'bi-x-circle',           'label' => 'Vencidas',          'value' => $kpiVencidas,     'color' => 'secondary'],
        ['icon' => 'bi-slash-circle',       'label' => 'Bloqueadas',        'value' => $kpiBloqueadas,   'color' => 'secondary'],
        ['icon' => 'bi-cash-stack',         'label' => 'Ingresos del mes',  'value' => $kpiIngresosMes,  'color' => 'secondary', 'prefix' => '$'],
    ];
    foreach ($kpis as $k):
        $display = $k['value'] === null ? '—' : (($k['prefix'] ?? '') . ($k['color'] === 'success' ? number_format((float)$k['value'], 2) : (int)$k['value']));
    ?>
    <div class="col-6 col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="kpi-icon bg-<?php echo $k['color']; ?> bg-opacity-10 text-<?php echo $k['color']; ?>">
                    <i class="bi <?php echo $k['icon']; ?>"></i>
                </div>
                <div>
                    <div class="kpi-label text-muted"><?php echo $k['label']; ?></div>
                    <div class="kpi-number text-<?php echo $k['color']; ?>"><?php echo $display; ?></div>
                </div>
            </div>
            <div class="kpi-bar bg-<?php echo $k['color']; ?>"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ══ REGISTRAR PAGO ═══════════════════════════════════════ -->
<p class="section-title"><i class="bi bi-plus-circle"></i> Registrar pago</p>

<div class="card pro-card mb-3">
    <div class="card-header">
        <span class="card-title-sm"><i class="bi bi-plus-circle me-1"></i>Nuevo pago</span>
    </div>
    <div class="card-body">
        <?php if (!$hasEmpresas || !$hasPagos): ?>
            <div class="alert alert-info mb-0">
                Verifica que existan las tablas <strong>empresas</strong> y <strong>pagos_empresas</strong>.
                <?php if ($dbName !== ''): ?> BD actual: <strong><?php echo html($dbName); ?></strong><?php endif; ?>
            </div>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="create_payment">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Empresa</label>
                        <select name="empresa_id" class="form-select" required>
                            <option value="">Seleccione</option>
                            <?php
                            $resSel = $mysqli->query("SELECT id, nombre FROM empresas ORDER BY nombre");
                            if ($resSel) {
                                while ($e = $resSel->fetch_assoc()) {
                                    echo '<option value="'.(int)$e['id'].'">'.html($e['nombre']).'</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Monto</label>
                        <div class="input-group">
                            <span class="input-group-text" style="border-radius:8px 0 0 8px">$</span>
                            <input name="monto" type="number" step="0.01" class="form-control" style="border-radius:0 8px 8px 0" required>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Método de pago</label>
                        <select name="metodo_pago" class="form-select">
                            <option value="transferencia">Transferencia</option>
                            <option value="efectivo">Efectivo</option>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="yappy">Yappy</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Referencia</label>
                        <input name="referencia" class="form-control" placeholder="# referencia">
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3 mt-3 flex-wrap">
                    <button class="btn btn-primary btn-sm px-4" type="submit">
                        <i class="bi bi-check2-circle me-1"></i>Guardar pago
                    </button>
                    <span class="text-muted" style="font-size:.8rem">
                        <i class="bi bi-info-circle me-1 opacity-50"></i>
                        Al guardar se renueva 1 mes y se desbloquea la empresa.
                    </span>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- ══ PAGOS RECIENTES ══════════════════════════════════════ -->
<p class="section-title"><i class="bi bi-clock-history"></i> Pagos recientes</p>

<div class="card pro-card mb-4">
    <div class="card-header">
        <span class="card-title-sm">Historial de pagos</span>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="input-group input-group-sm" style="max-width: 320px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="paymentsSearch" placeholder="Buscar (empresa, referencia, método...)" autocomplete="off">
            </div>
            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25"
                  style="font-size:.67rem"><?php echo count($pagos); ?> registros</span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (!$hasPagos): ?>
            <div class="alert alert-info m-3 mb-0">
                No se pudo acceder a la tabla <strong>pagos_empresas</strong>.
                <?php if ($dbName !== ''): ?> BD: <strong><?php echo html($dbName); ?></strong><?php endif; ?>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table pro-table mb-0">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Monto</th>
                        <th>Fecha</th>
                        <th>Periodo</th>
                        <th>Método</th>
                        <th>Referencia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pagos)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
                                No hay pagos registrados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pagos as $p): ?>
                        <tr class="payment-row" role="button" tabindex="0"
                            data-payment-id="<?php echo (int)($p['id'] ?? 0); ?>"
                            data-payment-empresa="<?php echo html((string)($p['empresa_nombre'] ?? '')); ?>"
                            data-payment-monto="<?php echo number_format((float)($p['monto'] ?? 0), 2); ?>"
                            data-payment-fecha="<?php echo html((string)($p['fecha_pago'] ?? '')); ?>">
                            <td class="fw-semibold"><?php echo html((string)($p['empresa_nombre'] ?? '')); ?></td>
                            <td class="text-success fw-semibold">
                                $<?php echo number_format((float)($p['monto'] ?? 0), 2); ?>
                            </td>
                            <td>
                                <i class="bi bi-calendar3 me-1 text-muted opacity-50"></i>
                                <?php echo html((string)($p['fecha_pago'] ?? '')); ?>
                            </td>
                            <td class="text-muted" style="font-size:.85rem">
                                <?php echo html((string)($p['periodo_desde'] ?? '')); ?>
                                <i class="bi bi-arrow-right mx-1 opacity-50"></i>
                                <?php echo html((string)($p['periodo_hasta'] ?? '')); ?>
                            </td>
                            <td>
                                <span class="badge-pill badge bg-primary bg-opacity-10 text-primary">
                                    <?php echo html((string)($p['metodo_pago'] ?? '—')); ?>
                                </span>
                            </td>
                            <td class="text-muted" style="font-size:.85rem">
                                <?php echo html((string)($p['referencia'] ?? '—')); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="deletePaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="pagos.php" id="deletePaymentForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-trash me-2 text-danger"></i>Eliminar pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_payment">
                    <input type="hidden" name="payment_id" id="deletePaymentId" value="">

                    <div class="alert alert-danger d-flex align-items-center gap-2" style="border-radius:10px">
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                        <div>
                            Esta acción eliminará el pago seleccionado.
                            <div class="mt-1">Empresa: <strong id="deletePaymentEmpresa"></strong></div>
                            <div class="mt-1">Monto: <strong id="deletePaymentMonto"></strong> | Fecha: <strong id="deletePaymentFecha"></strong></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="bi bi-trash me-1"></i>Eliminar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('paymentsSearch');
    var table = document.querySelector('.card.pro-card.mb-4 table');
    if (!input || !table) return;

    function normalize(s) {
        return (s || '').toString().toLowerCase().trim();
    }

    input.addEventListener('input', function () {
        var q = normalize(input.value);
        var rows = table.querySelectorAll('tbody tr');
        rows.forEach(function (tr) {
            var txt = normalize(tr.textContent);
            tr.style.display = (q === '' || txt.indexOf(q) !== -1) ? '' : 'none';
        });
    });

    var deleteModalEl = document.getElementById('deletePaymentModal');
    var payRows = document.querySelectorAll('tr.payment-row');
    if (deleteModalEl && payRows && payRows.length) {
        payRows.forEach(function (tr) {
            tr.addEventListener('click', function () {
                var id = tr.getAttribute('data-payment-id') || '';
                if (!id) return;
                document.getElementById('deletePaymentId').value = id;
                document.getElementById('deletePaymentEmpresa').textContent = tr.getAttribute('data-payment-empresa') || '';
                document.getElementById('deletePaymentMonto').textContent = '$' + (tr.getAttribute('data-payment-monto') || '0.00');
                document.getElementById('deletePaymentFecha').textContent = tr.getAttribute('data-payment-fecha') || '';
                bootstrap.Modal.getOrCreateInstance(deleteModalEl).show();
            });
        });
    }
});
</script>

<?php
$content = (string)ob_get_clean();
$currentRoute = 'pagos';
require __DIR__ . '/layout.php';