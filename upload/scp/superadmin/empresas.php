<?php
/**
 * empresas.php
 * Ruta: /scp/superadmin/empresas.php
 *
 * Gestión de empresas (tenant): crear, editar, bloquear,
 * suspender, activar y eliminar.
 *
 * Estilos : css/empresas.css
 * Scripts : js/empresa.js
 */

require_once '../../../config.php';
require_once '../../../includes/helpers.php';

ob_start();

global $mysqli;

$alwaysRaw = trim((string)getAppSetting('billing.always_active_empresas', '1'));
$alwaysActiveIds = [];
if ($alwaysRaw !== '') {
    foreach (preg_split('/\s*,\s*/', $alwaysRaw) as $v) {
        if ($v === '') continue;
        if (is_numeric($v)) {
            $n = (int)$v;
            if ($n > 0) $alwaysActiveIds[$n] = true;
        }
    }
}
// La empresa principal siempre queda activa
$alwaysActiveIds[1] = true;

/* ── Estado de mensajes ──────────────────────────────────── */
$err  = '';
$msg  = '';
$warn = '';

/* ── Empresa seleccionada via GET ────────────────────────── */
$selectedId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

/* ── Verificar tabla empresas ────────────────────────────── */
$hasEmpresas = false;
if (isset($mysqli) && $mysqli) {
    try {
        $hasEmpresas = ($mysqli->query('SELECT 1 FROM empresas LIMIT 1') !== false);
    } catch (Throwable $e) {
        $hasEmpresas = false;
    }
}

if ($hasEmpresas) {
    syncAllEmpresasBillingStatus();
}

/* ================================================================
   PROCESAMIENTO POST
   ================================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validateCSRF()) {
        $err = 'Token de seguridad inválido.';

    } elseif (!$hasEmpresas) {
        $err = 'No se pudo acceder a la tabla empresas.';

    } else {
        $action    = strtolower((string)($_POST['action'] ?? ''));
        $empresaId = isset($_POST['empresa_id']) && is_numeric($_POST['empresa_id'])
                     ? (int)$_POST['empresa_id'] : 0;
        $now = date('Y-m-d H:i:s');

        try {

            /* ── Crear ── */
            if ($action === 'create') {
                $nombre     = trim((string)($_POST['nombre'] ?? ''));
                $precio     = (float)($_POST['precio_mensual'] ?? 0);
                $inicio     = trim((string)($_POST['fecha_inicio_servicio'] ?? ''));
                $venc       = trim((string)($_POST['fecha_vencimiento'] ?? ''));
                $diasGracia = (int)($_POST['dias_gracia'] ?? 0);

                if ($nombre === '') {
                    $err = 'El nombre es obligatorio.';
                } else {
                    $inicioVal = $inicio !== '' ? $inicio : null;
                    $vencVal   = $venc   !== '' ? $venc   : null;

                    $stmt = $mysqli->prepare(
                        "INSERT INTO empresas
                            (nombre, estado, fecha_creacion, precio_mensual,
                             fecha_inicio_servicio, fecha_vencimiento, dias_gracia,
                             estado_pago, bloqueada, motivo_bloqueo)
                         VALUES (?, 'activa', ?, ?, ?, ?, ?, 'al_dia', 0, NULL)"
                    );
                    if (!$stmt) {
                        $err = 'No se pudo preparar la creación.';
                    } else {
                        $stmt->bind_param('ssdssi', $nombre, $now, $precio, $inicioVal, $vencVal, $diasGracia);
                        if ($stmt->execute()) {
                            $msg        = 'Empresa creada correctamente.';
                            $selectedId = (int)$stmt->insert_id;
                        } else {
                            $err = 'No se pudo crear la empresa.';
                        }
                    }
                }

            /* ── Validar empresa_id ── */
            } elseif ($empresaId <= 0) {
                $err = 'Empresa inválida.';

            /* ── Bloquear ── */
            } elseif ($action === 'block') {
                $motivo = trim((string)($_POST['motivo_bloqueo'] ?? 'Pago mensual vencido'));
                $stmt   = $mysqli->prepare(
                    "UPDATE empresas SET bloqueada = 1, estado_pago = 'suspendido', motivo_bloqueo = ? WHERE id = ?"
                );
                if ($stmt) {
                    $stmt->bind_param('si', $motivo, $empresaId);
                    if ($stmt->execute()) { $msg = 'Empresa bloqueada.';    $selectedId = $empresaId; }
                    else                  { $err = 'No se pudo bloquear la empresa.'; }
                } else { $err = 'No se pudo preparar la operación.'; }

            /* ── Desbloquear ── */
            } elseif ($action === 'unblock') {
                $stmt = $mysqli->prepare(
                    "UPDATE empresas SET bloqueada = 0, estado_pago = 'al_dia', motivo_bloqueo = NULL WHERE id = ?"
                );
                if ($stmt) {
                    $stmt->bind_param('i', $empresaId);
                    if ($stmt->execute()) { $msg = 'Empresa desbloqueada.'; $selectedId = $empresaId; }
                    else                  { $err = 'No se pudo desbloquear la empresa.'; }
                } else { $err = 'No se pudo preparar la operación.'; }

            /* ── Cancelar servicio ── */
            } elseif ($action === 'cancel_service') {
                $motivo = trim((string)($_POST['motivo_bloqueo'] ?? 'Servicio cancelado'));
                $stmt = $mysqli->prepare(
                    "UPDATE empresas
                     SET fecha_vencimiento = CURDATE(),
                         estado_pago = 'suspendido',
                         bloqueada = 0,
                         motivo_bloqueo = ?
                     WHERE id = ?"
                );
                if ($stmt) {
                    $stmt->bind_param('si', $motivo, $empresaId);
                    if ($stmt->execute()) {
                        $msg = 'Servicio cancelado.';
                        $selectedId = $empresaId;
                    } else {
                        $err = 'No se pudo cancelar el servicio.';
                    }
                } else {
                    $err = 'No se pudo preparar la operación.';
                }

            /* ── Volver activar (3 días) ── */
            } elseif ($action === 'grace_3days') {
                $stmt = $mysqli->prepare(
                    "UPDATE empresas
                     SET fecha_vencimiento = DATE_ADD(CURDATE(), INTERVAL 3 DAY),
                         estado_pago = 'al_dia',
                         bloqueada = 0,
                         motivo_bloqueo = NULL
                     WHERE id = ?"
                );
                if ($stmt) {
                    $stmt->bind_param('i', $empresaId);
                    if ($stmt->execute()) {
                        $msg = 'Servicio reactivado por 3 días.';
                        $selectedId = $empresaId;
                    } else {
                        $err = 'No se pudo reactivar el servicio.';
                    }
                } else {
                    $err = 'No se pudo preparar la operación.';
                }

            /* ── Suspender ── */
            } elseif ($action === 'suspend') {
                $stmt = $mysqli->prepare(
                    "UPDATE empresas SET estado = 'suspendida' WHERE id = ?"
                );
                if ($stmt) {
                    $stmt->bind_param('i', $empresaId);
                    if ($stmt->execute()) { $msg = 'Empresa suspendida.';   $selectedId = $empresaId; }
                    else                  { $err = 'No se pudo suspender la empresa.'; }
                } else { $err = 'No se pudo preparar la operación.'; }

            /* ── Activar ── */
            } elseif ($action === 'activate') {
                $stmt = $mysqli->prepare(
                    "UPDATE empresas SET estado = 'activa' WHERE id = ?"
                );
                if ($stmt) {
                    $stmt->bind_param('i', $empresaId);
                    if ($stmt->execute()) { $msg = 'Empresa activada.';     $selectedId = $empresaId; }
                    else                  { $err = 'No se pudo activar la empresa.'; }
                } else { $err = 'No se pudo preparar la operación.'; }

            /* ── Actualizar datos ── */
            } elseif ($action === 'update_data') {

    // USA EL MISMO NOMBRE DEL FORMULARIO
    $empresaId = (int)($_POST['empresa_id'] ?? 0);

    $nombre     = trim((string)($_POST['nombre'] ?? ''));
    $inicio     = trim((string)($_POST['fecha_inicio_servicio'] ?? ''));
    $venc       = trim((string)($_POST['fecha_vencimiento'] ?? ''));
    $diasGracia = (int)($_POST['dias_gracia'] ?? 0);
    $precio     = (float)($_POST['precio_mensual'] ?? 0);

    if ($nombre === '') {
        $err = 'El nombre es obligatorio.';
    } else {
        $alwaysActiveNew = isset($_POST['always_active']) && (string)$_POST['always_active'] === '1';
        if ($empresaId > 1) {
            if ($alwaysActiveNew) {
                $alwaysActiveIds[$empresaId] = true;
            } else {
                unset($alwaysActiveIds[$empresaId]);
            }
        }
        $alwaysActiveIds[1] = true;
        $newSetting = implode(',', array_map('intval', array_keys($alwaysActiveIds)));
        setAppSetting('billing.always_active_empresas', $newSetting);

        $inicioVal = $inicio !== '' ? $inicio : null;
        $vencVal   = $venc   !== '' ? $venc   : null;

        $stmt = $mysqli->prepare(
            "UPDATE empresas
             SET nombre = ?, fecha_inicio_servicio = ?, fecha_vencimiento = ?,
                 dias_gracia = ?, precio_mensual = ?
             WHERE id = ?"
        );

        if ($stmt) {
            $stmt->bind_param('sssidi', $nombre, $inicioVal, $vencVal, $diasGracia, $precio, $empresaId);

            if ($stmt->execute()) {
                $msg = 'Datos de la empresa actualizados.';
                $selectedId = $empresaId;
            } else {
                $err = 'No se pudo actualizar la empresa.';
            }
        } else {
            $err = 'No se pudo preparar la operación.';
        }
    }


            /* ── Eliminar ── */
            } elseif ($action === 'delete') {
                if ($empresaId === 1) {
                    $err = 'No se puede eliminar la empresa principal.';
                } else {
                    $canDelete = true;
                    foreach (['staff', 'users', 'tickets'] as $tbl) {
                        try {
                            $resT = $mysqli->query("SELECT 1 FROM {$tbl} LIMIT 1");
                            if ($resT !== false) {
                                $stmtC = $mysqli->prepare("SELECT COUNT(*) c FROM {$tbl} WHERE empresa_id = ?");
                                if ($stmtC) {
                                    $stmtC->bind_param('i', $empresaId);
                                    if ($stmtC->execute()) {
                                        $c = (int)($stmtC->get_result()->fetch_assoc()['c'] ?? 0);
                                        if ($c > 0) { $canDelete = false; break; }
                                    } else { $canDelete = false; break; }
                                } else { $canDelete = false; break; }
                            }
                        } catch (Throwable $e) { $canDelete = false; break; }
                    }

                    if (!$canDelete) {
                        $err = 'No se puede eliminar: la empresa tiene datos asociados (staff/usuarios/tickets).';
                    } else {
                        $stmt = $mysqli->prepare('DELETE FROM empresas WHERE id = ?');
                        if ($stmt) {
                            $stmt->bind_param('i', $empresaId);
                            if ($stmt->execute()) { $msg = 'Empresa eliminada.'; $selectedId = 0; }
                            else                  { $err = 'No se pudo eliminar la empresa.'; }
                        } else { $err = 'No se pudo preparar la operación.'; }
                    }
                }

            } else {
                $err = 'Acción no válida.';
            }

        } catch (Throwable $e) {
            $err = 'Error al procesar la solicitud.';
        }
    }
}

/* ================================================================
   LECTURA DE DATOS
   ================================================================ */
$empresas = [];
$empresa  = null;

if ($hasEmpresas && isset($mysqli) && $mysqli) {

    $SQL_FIELDS = "id, nombre, estado, precio_mensual, fecha_inicio_servicio,
                   fecha_vencimiento, dias_gracia, estado_pago, bloqueada, motivo_bloqueo,
                   CASE WHEN fecha_vencimiento IS NULL
                        THEN NULL
                        ELSE DATEDIFF(fecha_vencimiento, CURDATE())
                   END AS dias_restantes";

    $resE = $mysqli->query("SELECT {$SQL_FIELDS} FROM empresas ORDER BY id DESC LIMIT 200");
    if ($resE) {
        while ($row = $resE->fetch_assoc()) $empresas[] = $row;
    }

    if ($selectedId > 0) {
        $stmt = $mysqli->prepare("SELECT {$SQL_FIELDS} FROM empresas WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $selectedId);
            if ($stmt->execute()) $empresa = $stmt->get_result()->fetch_assoc();
        }
    }
}

/* ── KPIs rápidos ─────────────────────────────────────────── */
$totalEmpresas   = count($empresas);
$totalActivas    = count(array_filter($empresas, fn($e) => ($e['estado']       ?? '') === 'activa'));
$totalBloqueadas = count(array_filter($empresas, fn($e) => (int)($e['bloqueada'] ?? 0) === 1));
$totalVencidas   = count(array_filter($empresas, fn($e) => ($e['estado_pago']  ?? '') === 'suspendido'));

/* ── Helpers de badge ─────────────────────────────────────── */
function badgeEstado(string $estado): string {
    return match(strtolower($estado)) {
        'activa'     => 'bg-success bg-opacity-10 text-success',
        'suspendida' => 'bg-info bg-opacity-10 text-info',
        default      => 'bg-secondary bg-opacity-10 text-secondary',
    };
}
function badgePago(string $pago): string {
    return match($pago) {
        'al_dia'    => 'bg-success bg-opacity-10 text-success',
        'vencido'   => 'bg-info bg-opacity-10 text-info',
        'suspendido'=> 'bg-danger bg-opacity-10 text-danger',
        default     => 'bg-secondary bg-opacity-10 text-secondary',
    };
}
function badgeDias(?int $d): string {
    if ($d === null) return 'bg-secondary bg-opacity-10 text-secondary';
    if ($d < 0)     return 'bg-danger text-white';
    if ($d <= 7)    return 'bg-info text-white';
    return 'bg-success bg-opacity-10 text-success';
}
?>

<!-- ══ CSS externo ══════════════════════════════════════════ -->
<link rel="stylesheet" href="css/empresas.css">

<!-- ══ HEADER ══════════════════════════════════════════════ -->
<div class="emp-hero mb-1">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="hero-icon"><i class="bi bi-buildings"></i></div>
            <div>
                <h1>Empresas</h1>
                <p>Administración de servicios, pagos y accesos por tenant</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3 py-2">
                <i class="bi bi-calendar3 me-1"></i><?php echo date('d M Y'); ?>
            </span>
            <button class="btn btn-primary btn-sm px-3" type="button"
                    data-bs-toggle="modal" data-bs-target="#createEmpresaModal">
                <i class="bi bi-plus-lg me-1"></i> Nueva empresa
            </button>
        </div>
    </div>
</div>

<!-- ══ KPIs ═════════════════════════════════════════════════ -->
<p class="section-title"><i class="bi bi-speedometer2"></i> Resumen</p>

<div class="row g-3 mb-2">
    <?php
    $kpis = [
        ['icon' => 'bi-buildings',          'label' => 'Total',      'value' => $totalEmpresas,   'color' => 'primary'],
        ['icon' => 'bi-building-check',     'label' => 'Activas',    'value' => $totalActivas,    'color' => 'success'],
        ['icon' => 'bi-exclamation-triangle','label' => 'Vencidas',  'value' => $totalVencidas,   'color' => 'info'],
        ['icon' => 'bi-slash-circle',       'label' => 'Bloqueadas', 'value' => $totalBloqueadas, 'color' => 'danger'],
    ];
    foreach ($kpis as $k): ?>
    <div class="col-6 col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="kpi-icon bg-<?php echo $k['color']; ?> bg-opacity-10 text-<?php echo $k['color']; ?>">
                    <i class="bi <?php echo $k['icon']; ?>"></i>
                </div>
                <div>
                    <div class="kpi-label text-muted"><?php echo $k['label']; ?></div>
                    <div class="kpi-number text-<?php echo $k['color']; ?>"><?php echo $k['value']; ?></div>
                </div>
            </div>
            <div class="kpi-bar bg-<?php echo $k['color']; ?>"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ══ ALERTAS ══════════════════════════════════════════════ -->
<?php if ($err !== ''): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mt-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
        <div><?php echo html($err); ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($warn !== ''): ?>
    <div class="alert alert-info alert-dismissible fade show d-flex align-items-center gap-2 mt-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
        <div><?php echo html($warn); ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($msg !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mt-3" role="alert">
        <i class="bi bi-check-circle-fill flex-shrink-0"></i>
        <div><?php echo html($msg); ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ══ TABLA DE EMPRESAS ════════════════════════════════════ -->
<p class="section-title"><i class="bi bi-list-ul"></i> Lista de empresas</p>

<div class="card pro-card mb-3">
    <div class="card-header">
        <span class="card-title-sm">Empresas registradas</span>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted" style="font-size:.75rem">Selecciona una fila para ver el detalle</span>
            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25"
                  style="font-size:.67rem"><?php echo count($empresas); ?> registros</span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (!$hasEmpresas): ?>
            <div class="alert alert-info m-3 mb-0">
                No se pudo acceder a la tabla <strong>empresas</strong>. Ejecuta la migración en la BD correcta.
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table pro-table mb-0">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Estado</th>
                        <th>Vencimiento</th>
                        <th>Días restantes</th>
                        <th>Estado de pago</th>
                        <th>Bloqueada</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($empresas)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
                            No hay empresas registradas.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($empresas as $e):
                        $id        = (int)($e['id'] ?? 0);
                        $isBlocked = (int)($e['bloqueada'] ?? 0) === 1;
                        $estadoPago= (string)($e['estado_pago'] ?? '');
                        $diasNum   = ($e['dias_restantes'] ?? null) !== null ? (int)$e['dias_restantes'] : null;
                        $isAlwaysActive = isset($alwaysActiveIds[$id]);
                    ?>
                    <tr class="<?php echo ($selectedId === $id) ? 'row-selected' : ''; ?>"
                        onclick="window.location='empresas.php?id=<?php echo $id; ?>'">

                        <td class="fw-semibold"><?php echo html((string)($e['nombre'] ?? '')); ?></td>

                        <td>
                            <span class="badge-pill badge <?php echo badgeEstado((string)($e['estado'] ?? '')); ?>">
                                <?php echo html((string)($e['estado'] ?? '—')); ?>
                            </span>
                        </td>

                        <td>
                            <?php if (!empty($e['fecha_vencimiento'])): ?>
                                <i class="bi bi-calendar3 me-1 text-muted opacity-50"></i>
                                <?php echo html((string)$e['fecha_vencimiento']); ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($diasNum === null): ?>
                                <span class="text-muted">—</span>
                            <?php else: ?>
                                <span class="dias-pill <?php echo badgeDias($diasNum); ?>">
                                    <?php echo $diasNum > 0 ? "+{$diasNum}" : $diasNum; ?>d
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="badge-pill badge <?php echo badgePago($estadoPago); ?>">
                                <?php echo html(str_replace('_', ' ', $estadoPago) ?? '—'); ?>
                            </span>
                        </td>

                        <td>
                            <?php if ($isBlocked): ?>
                                <span class="badge-pill badge bg-danger bg-opacity-10 text-danger">
                                    <i class="bi bi-lock-fill me-1"></i>Bloqueada
                                </span>
                            <?php else: ?>
                                <span class="badge-pill badge bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-check2 me-1"></i>Libre
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="text-end" onclick="event.stopPropagation()">
                            <div class="d-flex gap-1 justify-content-end btn-action-group">
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                    data-bs-toggle="modal" data-bs-target="#editEmpresaModal"
                                    data-empresa-id="<?php echo $id; ?>"
                                    data-empresa-nombre="<?php echo html((string)($e['nombre'] ?? '')); ?>"
                                    data-empresa-inicio="<?php echo html((string)($e['fecha_inicio_servicio'] ?? '')); ?>"
                                    data-empresa-vencimiento="<?php echo html((string)($e['fecha_vencimiento'] ?? '')); ?>"
                                    data-empresa-gracia="<?php echo html((string)($e['dias_gracia'] ?? '0')); ?>"
                                    data-empresa-precio="<?php echo html((string)($e['precio_mensual'] ?? '0')); ?>"
                                    data-empresa-alwaysactive="<?php echo $isAlwaysActive ? '1' : '0'; ?>"
                                    title="Editar">
                                    <i class="bi bi-pencil-square"></i>
                                </button>

                                <?php if ($isBlocked): ?>
                                    <form method="post" action="empresas.php?id=<?php echo $id; ?>" class="d-inline">
                                        <?php csrfField(); ?>
                                        <input type="hidden" name="action" value="unblock">
                                        <input type="hidden" name="empresa_id" value="<?php echo $id; ?>">
                                        <button class="btn btn-outline-success btn-sm" type="submit" title="Desbloquear">
                                            <i class="bi bi-unlock"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                        data-bs-toggle="modal" data-bs-target="#blockModal"
                                        data-empresa-id="<?php echo $id; ?>"
                                        data-empresa-nombre="<?php echo html((string)($e['nombre'] ?? '')); ?>"
                                        title="Bloquear">
                                        <i class="bi bi-lock"></i>
                                    </button>
                                <?php endif; ?>

                                <button type="button" class="btn btn-outline-danger btn-sm"
                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                    data-empresa-id="<?php echo $id; ?>"
                                    data-empresa-nombre="<?php echo html((string)($e['nombre'] ?? '')); ?>"
                                    title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
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

<!-- ══ DETALLE EMPRESA ══════════════════════════════════════ -->
<p class="section-title"><i class="bi bi-building-check"></i> Detalle de empresa</p>

<div class="card pro-card mb-4">
    <div class="card-header">
        <span class="card-title-sm">
            <?php echo $empresa ? html((string)($empresa['nombre'] ?? 'Empresa')) : 'Sin selección'; ?>
        </span>

        <?php if ($empresa):
            $eid     = (int)($empresa['id'] ?? 0);
            $blocked = (int)($empresa['bloqueada'] ?? 0) === 1;
        ?>
        <div class="d-flex gap-2 flex-wrap btn-action-group">

            <form method="post" action="empresas.php?id=<?php echo $eid; ?>" class="d-inline">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="grace_3days">
                <input type="hidden" name="empresa_id" value="<?php echo $eid; ?>">
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-play-circle me-1"></i>Volver activar (3 días)
                </button>
            </form>

            <button type="button" class="btn btn-outline-danger btn-sm"
                data-bs-toggle="modal" data-bs-target="#cancelServiceModal"
                data-empresa-id="<?php echo $eid; ?>"
                data-empresa-nombre="<?php echo html((string)($empresa['nombre'] ?? '')); ?>">
                <i class="bi bi-x-circle me-1"></i>Cancelar servicio
            </button>

        </div>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <?php if (!$empresa): ?>
            <div class="d-flex flex-column align-items-center justify-content-center py-5 text-muted">
                <i class="bi bi-building fs-1 opacity-25 mb-3"></i>
                <div>Selecciona una empresa de la lista para ver su detalle.</div>
            </div>
        <?php else:
            $detDiasNum = ($empresa['dias_restantes'] ?? null) !== null ? (int)$empresa['dias_restantes'] : null;
            $isAlwaysActive = isset($alwaysActiveIds[$eid]);
        ?>
        <div class="row g-3">

            <!-- Datos generales -->
            <div class="col-12 col-lg-6">
                <div class="card pro-card border" style="box-shadow:none">
                    <div class="card-header" style="padding:.7rem 1rem .55rem">
                        <span class="card-title-sm"><i class="bi bi-info-circle me-1"></i>Datos generales</span>
                    </div>
                    <div class="card-body py-1 px-3">
                        <div class="detail-row">
                            <div class="detail-label">Nombre</div>
                            <div class="detail-value fw-semibold"><?php echo html((string)($empresa['nombre'] ?? '')); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Precio mensual</div>
                            <div class="detail-value text-success fw-semibold">
                                $<?php echo number_format((float)($empresa['precio_mensual'] ?? 0), 2); ?>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Inicio servicio</div>
                            <div class="detail-value">
                                <?php if (!empty($empresa['fecha_inicio_servicio'])): ?>
                                    <i class="bi bi-calendar3 me-1 text-muted opacity-50"></i>
                                    <?php echo html((string)$empresa['fecha_inicio_servicio']); ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Días de gracia</div>
                            <div class="detail-value">
                                <span class="badge-pill badge bg-info bg-opacity-10 text-info">
                                    <?php echo (int)($empresa['dias_gracia'] ?? 0); ?> días
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estado de servicio -->
            <div class="col-12 col-lg-6">
                <div class="card pro-card border" style="box-shadow:none">
                    <div class="card-header" style="padding:.7rem 1rem .55rem">
                        <span class="card-title-sm"><i class="bi bi-activity me-1"></i>Estado del servicio</span>
                    </div>
                    <div class="card-body py-1 px-3">
                        <div class="detail-row">
                            <div class="detail-label">Estado</div>
                            <div class="detail-value">
                                <span class="badge-pill badge <?php echo badgeEstado((string)($empresa['estado'] ?? '')); ?>">
                                    <?php echo html((string)($empresa['estado'] ?? '—')); ?>
                                </span>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Vencimiento</div>
                            <div class="detail-value">
                                <?php if (!empty($empresa['fecha_vencimiento'])): ?>
                                    <i class="bi bi-calendar3 me-1 text-muted opacity-50"></i>
                                    <?php echo html((string)$empresa['fecha_vencimiento']); ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Días restantes</div>
                            <div class="detail-value">
                                <?php if ($detDiasNum === null): ?>
                                    <span class="text-muted">—</span>
                                <?php else: ?>
                                    <span class="dias-pill <?php echo badgeDias($detDiasNum); ?>">
                                        <?php echo $detDiasNum > 0 ? "+{$detDiasNum}" : $detDiasNum; ?>d
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Estado pago</div>
                            <div class="detail-value">
                                <span class="badge-pill badge <?php echo badgePago((string)($empresa['estado_pago'] ?? '')); ?>">
                                    <?php echo html(str_replace('_', ' ', (string)($empresa['estado_pago'] ?? '—'))); ?>
                                </span>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Acceso</div>
                            <div class="detail-value">
                                <?php if ((int)($empresa['bloqueada'] ?? 0) === 1): ?>
                                    <span class="badge-pill badge bg-danger bg-opacity-10 text-danger">
                                        <i class="bi bi-lock-fill me-1"></i>Bloqueada
                                    </span>
                                    <?php if (!empty($empresa['motivo_bloqueo'])): ?>
                                        <span class="text-muted ms-2" style="font-size:.8rem">
                                            <?php echo html((string)$empresa['motivo_bloqueo']); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge-pill badge bg-success bg-opacity-10 text-success">
                                        <i class="bi bi-check2 me-1"></i>Libre
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Siempre activa</div>
                            <div class="detail-value">
                                <?php if ($isAlwaysActive): ?>
                                    <span class="badge-pill badge bg-success bg-opacity-10 text-success">
                                        <i class="bi bi-check2 me-1"></i>Sí
                                    </span>
                                <?php else: ?>
                                    <span class="badge-pill badge bg-secondary bg-opacity-10 text-secondary">
                                        <i class="bi bi-x me-1"></i>No
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ MODAL: CREAR ══════════════════════════════════════════ -->
<div class="modal fade" id="createEmpresaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="empresas.php">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-lg me-2 text-primary"></i>Nueva empresa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="create">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control"
                                   placeholder="Nombre de la empresa" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Precio mensual</label>
                            <div class="input-group">
                                <span class="input-group-text" style="border-radius:8px 0 0 8px">$</span>
                                <input type="number" step="0.01" min="0" name="precio_mensual"
                                       class="form-control" style="border-radius:0 8px 8px 0" value="0">
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Inicio servicio</label>
                            <input type="date" name="fecha_inicio_servicio" class="form-control">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Vencimiento</label>
                            <input type="date" name="fecha_vencimiento" class="form-control">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Días de gracia</label>
                            <input type="number" min="0" name="dias_gracia" class="form-control" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-plus-lg me-1"></i>Crear empresa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ MODAL: EDITAR ════════════════════════════════════════ -->
<div class="modal fade" id="editEmpresaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="empresas.php">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2 text-secondary"></i>Actualizar datos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="update_data">
                    <input type="hidden" name="empresa_id" id="editEmpresaId" value="">
                    <div class="alert alert-secondary d-flex align-items-center gap-2 py-2 mb-3"
                         style="border-radius:8px;font-size:.8rem">
                        <i class="bi bi-building opacity-50"></i>
                        Editando: <strong id="editEmpresaNombreLabel"></strong>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" id="editEmpresaNombre" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="always_active" value="1" id="editEmpresaAlwaysActive">
                                <label class="form-check-label" for="editEmpresaAlwaysActive">Siempre activa (no vence por falta de pago)</label>
                            </div>
                            <div class="form-text">La empresa principal (ID 1) siempre permanece activa.</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Precio mensual</label>
                            <div class="input-group">
                                <span class="input-group-text" style="border-radius:8px 0 0 8px">$</span>
                                <input type="number" step="0.01" min="0" name="precio_mensual"
                                       id="editEmpresaPrecio" class="form-control"
                                       style="border-radius:0 8px 8px 0" value="0">
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Inicio servicio</label>
                            <input type="date" name="fecha_inicio_servicio" id="editEmpresaInicio" class="form-control">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Vencimiento</label>
                            <input type="date" name="fecha_vencimiento" id="editEmpresaVencimiento" class="form-control">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Días de gracia</label>
                            <input type="number" min="0" name="dias_gracia" id="editEmpresaGracia"
                                   class="form-control" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check2 me-1"></i>Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ MODAL: BLOQUEAR ══════════════════════════════════════ -->
<div class="modal fade" id="blockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="empresas.php" id="blockForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-lock me-2 text-danger"></i>Bloquear empresa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="block">
                    <input type="hidden" name="empresa_id" id="blockEmpresaId" value="">

                    <div class="alert alert-danger d-flex align-items-center gap-2" style="border-radius:10px">
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                        <div>
                            Esta acción marcará el <strong>estado de pago</strong> como <strong>suspendido</strong> y bloqueará el acceso.
                            <div class="mt-1">Empresa: <strong id="blockEmpresaNombre"></strong></div>
                        </div>
                    </div>

                    <label class="form-label">Motivo</label>
                    <input type="text" class="form-control" name="motivo_bloqueo" value="Pago mensual vencido">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="bi bi-lock me-1"></i>Bloquear
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ MODAL: CANCELAR SERVICIO ═════════════════════════════ -->
<div class="modal fade" id="cancelServiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="empresas.php" id="cancelServiceForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-x-circle me-2 text-danger"></i>Cancelar servicio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="cancel_service">
                    <input type="hidden" name="empresa_id" id="cancelEmpresaId" value="">
                    <input type="hidden" name="motivo_bloqueo" value="Servicio cancelado">

                    <div class="alert alert-danger d-flex align-items-center gap-2" style="border-radius:10px">
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                        <div>
                            Esta acción pondrá el servicio en <strong>0 días</strong> y el <strong>estado de pago</strong> en <strong>suspendido</strong>.
                            <div class="mt-1">Empresa: <strong id="cancelEmpresaNombre"></strong></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="bi bi-x-circle me-1"></i>Confirmar cancelación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ MODAL: ELIMINAR ══════════════════════════════════════ -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="empresas.php">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-trash me-2 text-danger"></i>Eliminar empresa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="empresa_id" id="deleteEmpresaId" value="">
                    <div class="alert alert-info d-flex align-items-center gap-2" style="border-radius:10px">
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                        <div>Esta acción es <strong>permanente</strong> y no se puede deshacer.<br>
                             Empresa: <strong id="deleteEmpresaNombre"></strong>
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

<!-- ══ JS externo ═══════════════════════════════════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    var editEmpresaModal = document.getElementById('editEmpresaModal');
    if (editEmpresaModal) {
        editEmpresaModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            document.getElementById('editEmpresaId').value = btn.getAttribute('data-empresa-id') || '';
            var nombre = btn.getAttribute('data-empresa-nombre') || '';
            document.getElementById('editEmpresaNombreLabel').textContent = nombre;
            document.getElementById('editEmpresaNombre').value = nombre;
            document.getElementById('editEmpresaPrecio').value = btn.getAttribute('data-empresa-precio') || '0';
            document.getElementById('editEmpresaInicio').value = btn.getAttribute('data-empresa-inicio') || '';
            document.getElementById('editEmpresaVencimiento').value = btn.getAttribute('data-empresa-vencimiento') || '';
            document.getElementById('editEmpresaGracia').value = btn.getAttribute('data-empresa-gracia') || '0';

            var eid = parseInt(btn.getAttribute('data-empresa-id') || '0', 10);
            var chk = document.getElementById('editEmpresaAlwaysActive');
            if (chk) {
                if (eid === 1) {
                    chk.checked = true;
                    chk.disabled = true;
                } else {
                    chk.disabled = false;
                    chk.checked = (btn.getAttribute('data-empresa-alwaysactive') === '1');
                }
            }
        });
    }

    var blockModal = document.getElementById('blockModal');
    if (blockModal) {
        blockModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;

            if (!btn) return;
            document.getElementById('blockEmpresaId').value = btn.getAttribute('data-empresa-id') || '';
            var eid = btn.getAttribute('data-empresa-id') || '';
            document.getElementById('blockEmpresaId').value = eid;
            document.getElementById('blockEmpresaNombre').textContent = btn.getAttribute('data-empresa-nombre') || '';

            var form = document.getElementById('blockForm');
            if (form && eid) {
                form.setAttribute('action', 'empresas.php?id=' + encodeURIComponent(eid));
            }
        });
    }

    var deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;

            if (!btn) return;
            document.getElementById('deleteEmpresaId').value = btn.getAttribute('data-empresa-id') || '';
            document.getElementById('deleteEmpresaNombre').textContent = btn.getAttribute('data-empresa-nombre') || '';
        });
    }

    var cancelServiceModal = document.getElementById('cancelServiceModal');
    if (cancelServiceModal) {
        cancelServiceModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            var eid = btn.getAttribute('data-empresa-id') || '';
            document.getElementById('cancelEmpresaId').value = eid;
            document.getElementById('cancelEmpresaNombre').textContent = btn.getAttribute('data-empresa-nombre') || '';

            var form = document.getElementById('cancelServiceForm');
            if (form && eid) {
                form.setAttribute('action', 'empresas.php?id=' + encodeURIComponent(eid));
            }
        });
    }
});
</script>

<?php
$content      = (string)ob_get_clean();
$currentRoute = 'empresas';
require __DIR__ . '/layout.php';