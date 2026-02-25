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
    } catch (Throwable $e) {
    }
}

$empresas = [];
$pagos = [];

$kpiActivas = null;
$kpiVencidas = null;
$kpiBloqueadas = null;
$kpiIngresosMes = null;

if ($hasEmpresas && isset($mysqli) && $mysqli) {
    $resK1 = $mysqli->query("SELECT COUNT(*) c FROM empresas WHERE estado = 'activa'");
    if ($resK1) $kpiActivas = (int)($resK1->fetch_assoc()['c'] ?? 0);

    $resK2 = $mysqli->query("SELECT COUNT(*) c FROM empresas WHERE estado_pago = 'vencido'");
    if ($resK2) $kpiVencidas = (int)($resK2->fetch_assoc()['c'] ?? 0);

    $resK3 = $mysqli->query("SELECT COUNT(*) c FROM empresas WHERE bloqueada = 1");
    if ($resK3) $kpiBloqueadas = (int)($resK3->fetch_assoc()['c'] ?? 0);

    $sqlE = "SELECT id, nombre, estado, fecha_vencimiento, estado_pago, bloqueada,\n                    CASE\n                        WHEN fecha_vencimiento IS NULL THEN NULL\n                        ELSE DATEDIFF(fecha_vencimiento, CURDATE())\n                    END AS dias_restantes\n             FROM empresas\n             ORDER BY id DESC\n             LIMIT 50";
    $resE = $mysqli->query($sqlE);
    if ($resE) {
        while ($row = $resE->fetch_assoc()) {
            $empresas[] = $row;
        }
    }
}

if ($hasPagos && isset($mysqli) && $mysqli) {
    $resK4 = $mysqli->query("SELECT COALESCE(SUM(monto), 0) total FROM pagos_empresas WHERE fecha_pago >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND fecha_pago < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)");
    if ($resK4) $kpiIngresosMes = (float)($resK4->fetch_assoc()['total'] ?? 0);

    $sqlP = "SELECT p.id, p.empresa_id, p.monto, p.fecha_pago, p.periodo_desde, p.periodo_hasta, p.metodo_pago, p.referencia, p.registrado_por,\n                    e.nombre AS empresa_nombre\n             FROM pagos_empresas p\n             INNER JOIN empresas e ON e.id = p.empresa_id\n             ORDER BY p.fecha_pago DESC, p.id DESC\n             LIMIT 50";
    $resP = $mysqli->query($sqlP);
    if ($resP) {
        while ($row = $resP->fetch_assoc()) {
            $pagos[] = $row;
        }
    }
}
?>

<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-speedometer2"></i></span>
            <div>
                <h1>Panel general</h1>
                <p>Resumen de empresas, facturación y estado del servicio</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php if ($dbName !== ''): ?>
                <span class="badge bg-info">BD: <?php echo html($dbName); ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 mt-3">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="text-muted">Empresas activas</div>
                <div class="fs-3 fw-semibold"><?php echo $kpiActivas === null ? '-' : (int)$kpiActivas; ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="text-muted">Empresas vencidas</div>
                <div class="fs-3 fw-semibold"><?php echo $kpiVencidas === null ? '-' : (int)$kpiVencidas; ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="text-muted">Empresas bloqueadas</div>
                <div class="fs-3 fw-semibold"><?php echo $kpiBloqueadas === null ? '-' : (int)$kpiBloqueadas; ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="text-muted">Ingresos del mes</div>
                <div class="fs-3 fw-semibold"><?php echo $kpiIngresosMes === null ? '-' : number_format((float)$kpiIngresosMes, 2); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card settings-card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-buildings"></i> Empresas</strong>
        <a href="empresas.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-right"></i> Ver todas</a>
    </div>
    <div class="card-body">

        <?php if (!$hasEmpresas): ?>
            <div class="alert alert-warning mb-0">No se pudo acceder a la tabla <strong>empresas</strong>. Verifica que la migración se ejecutó en la misma base de datos. <?php if ($dbName !== ''): ?>BD actual: <strong><?php echo html($dbName); ?></strong><?php endif; ?></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th>Vencimiento</th>
                            <th>Días restantes</th>
                            <th>Estado pago</th>
                            <th>Bloqueada</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($empresas)): ?>
                            <tr>
                                <td colspan="6" class="text-muted">No hay empresas registradas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($empresas as $e): ?>
                                <?php
                                $dias = isset($e['dias_restantes']) ? (int)$e['dias_restantes'] : null;
                                $isBlocked = (int)($e['bloqueada'] ?? 0) === 1;
                                $estadoPago = (string)($e['estado_pago'] ?? '');
                                $badge = 'bg-secondary';
                                if ($estadoPago === 'al_dia') $badge = 'bg-success';
                                if ($estadoPago === 'vencido') $badge = 'bg-warning text-dark';
                                if ($estadoPago === 'suspendido') $badge = 'bg-danger';
                                ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo html((string)($e['nombre'] ?? '')); ?></td>
                                    <td><?php echo html((string)($e['estado'] ?? '')); ?></td>
                                    <td><?php echo html((string)($e['fecha_vencimiento'] ?? '')); ?></td>
                                    <td>
                                        <?php if (($e['fecha_vencimiento'] ?? null) === null): ?>
                                            <span class="text-muted">-</span>
                                        <?php else: ?>
                                            <?php echo html((string)$dias); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?php echo html($badge); ?>"><?php echo html($estadoPago !== '' ? $estadoPago : '-'); ?></span></td>
                                    <td>
                                        <?php if ($isBlocked): ?>
                                            <span class="badge bg-danger">Sí</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">No</span>
                                        <?php endif; ?>
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

<div class="card settings-card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-credit-card"></i> Pagos recientes</strong>
        <a href="pagos.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-right"></i> Ver historial</a>
    </div>
    <div class="card-body">

        <?php if (!$hasPagos): ?>
            <div class="alert alert-warning mb-0">No se pudo acceder a la tabla <strong>pagos_empresas</strong>. <?php if ($dbName !== ''): ?>BD actual: <strong><?php echo html($dbName); ?></strong><?php endif; ?></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>Monto</th>
                            <th>Fecha</th>
                            <th>Periodo</th>
                            <th>Método</th>
                            <th>Referencia</th>
                            <th>Registrado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pagos)): ?>
                            <tr>
                                <td colspan="7" class="text-muted">No hay pagos registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pagos as $p): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo html((string)($p['empresa_nombre'] ?? '')); ?></td>
                                    <td><?php echo html((string)($p['monto'] ?? '')); ?></td>
                                    <td><?php echo html((string)($p['fecha_pago'] ?? '')); ?></td>
                                    <td><?php echo html((string)($p['periodo_desde'] ?? '')); ?> - <?php echo html((string)($p['periodo_hasta'] ?? '')); ?></td>
                                    <td><?php echo html((string)($p['metodo_pago'] ?? '')); ?></td>
                                    <td><?php echo html((string)($p['referencia'] ?? '')); ?></td>
                                    <td><?php echo html((string)($p['registrado_por'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = (string)ob_get_clean();
$currentRoute = 'dashboard';
require __DIR__ . '/layout.php';
