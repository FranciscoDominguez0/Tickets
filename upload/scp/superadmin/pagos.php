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

/* =========================
   REGISTRAR PAGO
   ========================= */
$mensaje = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasEmpresas && $hasPagos) {

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

    // KPIs de estado
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

    // Empresas con días restantes
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
        while ($row = $resE->fetch_assoc()) {
            $empresas[] = $row;
        }
    }
}

if ($hasPagos && isset($mysqli) && $mysqli) {
    // Ingresos del mes
    $resK4 = $mysqli->query("
        SELECT COALESCE(SUM(monto), 0) total
        FROM pagos_empresas
        WHERE fecha_pago >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
          AND fecha_pago < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
    ");
    if ($resK4) $kpiIngresosMes = (float)($resK4->fetch_assoc()['total'] ?? 0);

    // Pagos recientes
    $sqlP = "SELECT p.id, p.empresa_id, p.monto, p.fecha_pago, p.periodo_desde, p.periodo_hasta, p.metodo_pago, p.referencia,
                    e.nombre AS empresa_nombre
             FROM pagos_empresas p
             INNER JOIN empresas e ON e.id = p.empresa_id
             ORDER BY p.fecha_pago DESC, p.id DESC
             LIMIT 50";
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
            <span class="settings-hero-icon"><i class="bi bi-credit-card-2-front"></i></span>
            <div>
                <h1>Facturación</h1>
                <p>Control mensual de pagos y estado del servicio</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php if ($dbName !== ''): ?>
                <span class="badge bg-info">BD: <?php echo html($dbName); ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success mt-3"><?php echo html($mensaje); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger mt-3"><?php echo html($error); ?></div>
<?php endif; ?>

<div class="row g-3 mt-3">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="text-muted">Vencen en ≤ 7 días</div>
                <div class="fs-3 fw-semibold"><?php echo $kpiVencen7 === null ? '-' : (int)$kpiVencen7; ?></div>
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
    <div class="card-header">
        <strong><i class="bi bi-plus-circle"></i> Registrar pago</strong>
    </div>
    <div class="card-body">

        <?php if (!$hasEmpresas || !$hasPagos): ?>
            <div class="alert alert-warning mb-0">
                Verifica que existan las tablas <strong>empresas</strong> y <strong>pagos_empresas</strong>.
                <?php if ($dbName !== ''): ?>BD actual: <strong><?php echo html($dbName); ?></strong><?php endif; ?>
            </div>
        <?php else: ?>
            <form method="post">
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
                        <input name="monto" type="number" step="0.01" class="form-control" required>
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

                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-check2-circle"></i> Guardar pago
                    </button>
                </div>

                <div class="alert alert-info mt-3 mb-0">
                    Al guardar: se registra el pago, se renueva 1 mes y se desbloquea la empresa.
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card settings-card mt-3">
    <div class="card-header">
        <strong><i class="bi bi-buildings"></i> Estado de empresas</strong>
    </div>
    <div class="card-body">

        <?php if (!$hasEmpresas): ?>
            <div class="alert alert-warning mb-0">
                No se pudo acceder a la tabla <strong>empresas</strong>.
                <?php if ($dbName !== ''): ?>BD actual: <strong><?php echo html($dbName); ?></strong><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>Vencimiento</th>
                            <th>Días restantes</th>
                            <th>Estado pago</th>
                            <th>Bloqueada</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($empresas)): ?>
                            <tr><td colspan="5" class="text-muted">No hay empresas registradas.</td></tr>
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

                                $diasClass = 'text-muted';
                                if ($dias !== null && $dias <= 7 && $dias >= 0) $diasClass = 'text-warning fw-semibold';
                                if ($dias !== null && $dias < 0) $diasClass = 'text-danger fw-semibold';
                                ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo html((string)($e['nombre'] ?? '')); ?></td>
                                    <td><?php echo html((string)($e['fecha_vencimiento'] ?? '-')); ?></td>
                                    <td class="<?php echo html($diasClass); ?>">
                                        <?php echo $dias === null ? '-' : html((string)$dias); ?>
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
    <div class="card-header">
        <strong><i class="bi bi-clock-history"></i> Pagos recientes</strong>
    </div>
    <div class="card-body">

        <?php if (!$hasPagos): ?>
            <div class="alert alert-warning mb-0">
                No se pudo acceder a la tabla <strong>pagos_empresas</strong>.
                <?php if ($dbName !== ''): ?>BD actual: <strong><?php echo html($dbName); ?></strong><?php endif; ?>
            </div>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pagos)): ?>
                            <tr><td colspan="6" class="text-muted">No hay pagos registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pagos as $p): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo html((string)($p['empresa_nombre'] ?? '')); ?></td>
                                    <td><?php echo number_format((float)($p['monto'] ?? 0), 2); ?></td>
                                    <td><?php echo html((string)($p['fecha_pago'] ?? '')); ?></td>
                                    <td><?php echo html((string)($p['periodo_desde'] ?? '')); ?> - <?php echo html((string)($p['periodo_hasta'] ?? '')); ?></td>
                                    <td><?php echo html((string)($p['metodo_pago'] ?? '')); ?></td>
                                    <td><?php echo html((string)($p['referencia'] ?? '')); ?></td>
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
$currentRoute = 'pagos';
require __DIR__ . '/layout.php';