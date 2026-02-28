<?php

$eid = (int)($_SESSION['empresa_id'] ?? 0);
if ($eid <= 0) $eid = 1;

$hasEmpresas = false;
$hasPagos = false;
if (isset($mysqli) && $mysqli) {
    try {
        $r1 = $mysqli->query("SHOW TABLES LIKE 'empresas'");
        $hasEmpresas = ($r1 && $r1->num_rows > 0);
        $r2 = $mysqli->query("SHOW TABLES LIKE 'pagos_empresas'");
        $hasPagos = ($r2 && $r2->num_rows > 0);
    } catch (Throwable $e) {
    }
}

$empresa = null;
if ($hasEmpresas && isset($mysqli) && $mysqli) {
    $stmtE = $mysqli->prepare('SELECT id, nombre, fecha_vencimiento, estado_pago, bloqueada, motivo_bloqueo FROM empresas WHERE id = ? LIMIT 1');
    if ($stmtE) {
        $stmtE->bind_param('i', $eid);
        $stmtE->execute();
        $empresa = $stmtE->get_result()->fetch_assoc();
    }
}

$pagos = [];
if ($hasPagos && isset($mysqli) && $mysqli) {
    $stmtP = $mysqli->prepare('SELECT id, monto, fecha_pago, periodo_desde, periodo_hasta, metodo_pago, referencia FROM pagos_empresas WHERE empresa_id = ? ORDER BY fecha_pago DESC, id DESC LIMIT 50');
    if ($stmtP) {
        $stmtP->bind_param('i', $eid);
        $stmtP->execute();
        $resP = $stmtP->get_result();
        if ($resP) {
            while ($r = $resP->fetch_assoc()) {
                $pagos[] = $r;
            }
        }
    }
}

$empresaNombre = (string)($empresa['nombre'] ?? '');
$fechaVenc = (string)($empresa['fecha_vencimiento'] ?? '');
$estadoPago = (string)($empresa['estado_pago'] ?? '');
$bloqueada = (int)($empresa['bloqueada'] ?? 0) === 1;

$diasRestantes = null;
if ($fechaVenc !== '') {
    $today = new DateTime(date('Y-m-d'));
    $fv = DateTime::createFromFormat('Y-m-d', $fechaVenc);
    if ($fv) {
        $diasRestantes = (int)$today->diff($fv)->format('%r%a');
    }
}

$estadoBadge = 'secondary';
$estadoLabel = $estadoPago !== '' ? $estadoPago : 'N/D';
if ($estadoPago === 'al_dia') {
    $estadoBadge = 'success';
    $estadoLabel = 'Al día';
} elseif ($estadoPago === 'suspendido') {
    $estadoBadge = 'warning';
    $estadoLabel = 'Suspendido';
}
if ($bloqueada) {
    $estadoBadge = 'danger';
    $estadoLabel = 'Bloqueada';
}

ob_start();
?>
<div class="settings-hero" id="settings">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-receipt"></i></span>
            <div>
                <h1>Facturación</h1>
                <p>Consulta el estado de tu plan y tu historial de pagos.</p>
            </div>
        </div>
    </div>
</div>

<?php if (!$hasEmpresas): ?>
    <div class="alert alert-info">No se pudo acceder a la tabla <strong>empresas</strong>.</div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card settings-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <strong>Estado del plan</strong>
                    <span class="badge bg-<?php echo html($estadoBadge); ?>"><?php echo html($estadoLabel); ?></span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="text-muted">Empresa</div>
                            <div class="fw-semibold"><?php echo html($empresaNombre !== '' ? $empresaNombre : ('Empresa #' . (int)$eid)); ?></div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="text-muted">Vence</div>
                            <div class="fw-semibold"><?php echo html($fechaVenc !== '' ? $fechaVenc : 'Sin fecha'); ?></div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="text-muted">Días restantes</div>
                            <div class="fw-semibold">
                                <?php
                                if ($diasRestantes === null) {
                                    echo 'N/D';
                                } else {
                                    echo (int)$diasRestantes;
                                }
                                ?>
                            </div>
                        </div>
                        <?php if ($bloqueada): ?>
                            <div class="col-12">
                                <div class="alert alert-danger mb-0">
                                    <?php echo html((string)($empresa['motivo_bloqueo'] ?? 'Servicio suspendido por falta de pago')); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card settings-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <strong>Resumen</strong>
                    <span class="text-muted">Últimos 50 pagos</span>
                </div>
                <div class="card-body">
                    <?php if (!$hasPagos): ?>
                        <div class="alert alert-info mb-0">No se pudo acceder a la tabla <strong>pagos_empresas</strong>.</div>
                    <?php else: ?>
                        <?php
                        $total = 0.0;
                        foreach ($pagos as $p) {
                            $total += (float)($p['monto'] ?? 0);
                        }
                        ?>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="text-muted">Cantidad de pagos</div>
                                <div class="fw-semibold"><?php echo (int)count($pagos); ?></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="text-muted">Total pagado</div>
                                <div class="fw-semibold"><?php echo number_format((float)$total, 2); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card settings-card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <strong>Historial de pagos</strong>
        </div>
        <div class="card-body p-0">
            <?php if (!$hasPagos): ?>
                <div class="alert alert-info m-3 mb-0">No se pudo acceder a la tabla <strong>pagos_empresas</strong>.</div>
            <?php elseif (empty($pagos)): ?>
                <div class="alert alert-secondary m-3 mb-0">No hay pagos registrados para tu empresa.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Monto</th>
                                <th>Fecha</th>
                                <th>Periodo</th>
                                <th>Método</th>
                                <th>Referencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagos as $p): ?>
                                <tr>
                                    <td><?php echo number_format((float)($p['monto'] ?? 0), 2); ?></td>
                                    <td><?php echo html(formatDate($p['fecha_pago'] ?? null)); ?></td>
                                    <td>
                                        <?php
                                        $pd = (string)($p['periodo_desde'] ?? '');
                                        $ph = (string)($p['periodo_hasta'] ?? '');
                                        echo html(trim($pd . ($pd !== '' && $ph !== '' ? ' - ' : '') . $ph));
                                        ?>
                                    </td>
                                    <td><?php echo html((string)($p['metodo_pago'] ?? '')); ?></td>
                                    <td><?php echo html((string)($p['referencia'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php
$content = (string)ob_get_clean();
