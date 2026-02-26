<?php
require_once '../../../config.php';
require_once '../../../includes/helpers.php';

ob_start();

global $mysqli;

$err = '';
$msg = '';
$warn = '';

$selectedId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

$hasEmpresas = false;
if (isset($mysqli) && $mysqli) {
    try {
        $hasEmpresas = ($mysqli->query('SELECT 1 FROM empresas LIMIT 1') !== false);
    } catch (Throwable $e) {
        $hasEmpresas = false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $err = 'Token de seguridad inválido.';
    } elseif (!$hasEmpresas) {
        $err = 'No se pudo acceder a la tabla empresas.';
    } else {
        $action = strtolower((string)($_POST['action'] ?? ''));
        $empresaId = isset($_POST['empresa_id']) && is_numeric($_POST['empresa_id']) ? (int)$_POST['empresa_id'] : 0;
        $now = date('Y-m-d H:i:s');

        try {
            if ($action === 'create') {
                $nombre = trim((string)($_POST['nombre'] ?? ''));
                $precio = (float)($_POST['precio_mensual'] ?? 0);
                $inicio = trim((string)($_POST['fecha_inicio_servicio'] ?? ''));
                $venc = trim((string)($_POST['fecha_vencimiento'] ?? ''));
                $diasGracia = (int)($_POST['dias_gracia'] ?? 0);

                if ($nombre === '') {
                    $err = 'El nombre es obligatorio.';
                } else {
                    $inicioVal = $inicio !== '' ? $inicio : null;
                    $vencVal = $venc !== '' ? $venc : null;

                    $stmt = $mysqli->prepare('INSERT INTO empresas (nombre, estado, fecha_creacion, precio_mensual, fecha_inicio_servicio, fecha_vencimiento, dias_gracia, estado_pago, bloqueada, motivo_bloqueo) VALUES (?, \'activa\', ?, ?, ?, ?, ?, \'al_dia\', 0, NULL)');
                    if (!$stmt) {
                        $err = 'No se pudo preparar la creación.';
                    } else {
                        $stmt->bind_param('ssdssi', $nombre, $now, $precio, $inicioVal, $vencVal, $diasGracia);
                        if ($stmt->execute()) {
                            $newId = (int)$stmt->insert_id;
                            $msg = 'Empresa creada correctamente.';
                            $selectedId = $newId;
                        } else {
                            $err = 'No se pudo crear la empresa.';
                        }
                    }
                }
            } elseif ($empresaId <= 0) {
                $err = 'Empresa inválida.';
            } elseif ($action === 'block') {
                $motivo = trim((string)($_POST['motivo_bloqueo'] ?? 'Pago mensual vencido'));
                $stmt = $mysqli->prepare("UPDATE empresas SET bloqueada = 1, estado_pago = 'suspendido', motivo_bloqueo = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('si', $motivo, $empresaId);
                    if ($stmt->execute()) {
                        $msg = 'Empresa bloqueada.';
                        $selectedId = $empresaId;
                    } else {
                        $err = 'No se pudo bloquear la empresa.';
                    }
                } else {
                    $err = 'No se pudo preparar la operación.';
                }
            } elseif ($action === 'unblock') {
                $stmt = $mysqli->prepare("UPDATE empresas SET bloqueada = 0, motivo_bloqueo = NULL WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $empresaId);
                    if ($stmt->execute()) {
                        $msg = 'Empresa desbloqueada.';
                        $selectedId = $empresaId;
                    } else {
                        $err = 'No se pudo desbloquear la empresa.';
                    }
                } else {
                    $err = 'No se pudo preparar la operación.';
                }
            } elseif ($action === 'suspend') {
                $stmt = $mysqli->prepare("UPDATE empresas SET estado = 'suspendida' WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $empresaId);
                    if ($stmt->execute()) {
                        $msg = 'Empresa suspendida.';
                        $selectedId = $empresaId;
                    } else {
                        $err = 'No se pudo suspender la empresa.';
                    }
                } else {
                    $err = 'No se pudo preparar la operación.';
                }
            } elseif ($action === 'activate') {
                $stmt = $mysqli->prepare("UPDATE empresas SET estado = 'activa' WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $empresaId);
                    if ($stmt->execute()) {
                        $msg = 'Empresa activada.';
                        $selectedId = $empresaId;
                    } else {
                        $err = 'No se pudo activar la empresa.';
                    }
                } else {
                    $err = 'No se pudo preparar la operación.';
                }
            } elseif ($action === 'update_data') {
                $nombre = trim((string)($_POST['nombre'] ?? ''));
                $inicio = trim((string)($_POST['fecha_inicio_servicio'] ?? ''));
                $venc = trim((string)($_POST['fecha_vencimiento'] ?? ''));
                $diasGracia = (int)($_POST['dias_gracia'] ?? 0);
                $precio = (float)($_POST['precio_mensual'] ?? 0);

                if ($nombre === '') {
                    $err = 'El nombre es obligatorio.';
                } else {
                    $inicioVal = $inicio !== '' ? $inicio : null;
                    $vencVal = $venc !== '' ? $venc : null;

                    $stmt = $mysqli->prepare('UPDATE empresas SET nombre = ?, fecha_inicio_servicio = ?, fecha_vencimiento = ?, dias_gracia = ?, precio_mensual = ? WHERE id = ?');
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
            } elseif ($action === 'delete') {
                if ($empresaId === 1) {
                    $err = 'No se puede eliminar la empresa principal.';
                } else {
                    $canDelete = true;
                    foreach (['staff','users','tickets'] as $tbl) {
                        try {
                            $resT = $mysqli->query("SELECT 1 FROM {$tbl} LIMIT 1");
                            if ($resT !== false) {
                                $stmtC = $mysqli->prepare("SELECT COUNT(*) c FROM {$tbl} WHERE empresa_id = ?");
                                if ($stmtC) {
                                    $stmtC->bind_param('i', $empresaId);
                                    if ($stmtC->execute()) {
                                        $c = (int)($stmtC->get_result()->fetch_assoc()['c'] ?? 0);
                                        if ($c > 0) {
                                            $canDelete = false;
                                            break;
                                        }
                                    } else {
                                        $canDelete = false;
                                        break;
                                    }
                                } else {
                                    $canDelete = false;
                                    break;
                                }
                            }
                        } catch (Throwable $e) {
                            $canDelete = false;
                            break;
                        }
                    }

                    if (!$canDelete) {
                        $err = 'No se puede eliminar: la empresa tiene datos asociados (staff/usuarios/tickets).';
                    } else {
                        $stmt = $mysqli->prepare('DELETE FROM empresas WHERE id = ?');
                        if ($stmt) {
                            $stmt->bind_param('i', $empresaId);
                            if ($stmt->execute()) {
                                $msg = 'Empresa eliminada.';
                                $selectedId = 0;
                            } else {
                                $err = 'No se pudo eliminar la empresa.';
                            }
                        } else {
                            $err = 'No se pudo preparar la operación.';
                        }
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

$empresas = [];
$empresa = null;

if ($hasEmpresas && isset($mysqli) && $mysqli) {
    $resE = $mysqli->query("SELECT id, nombre, estado, precio_mensual, fecha_inicio_servicio, fecha_vencimiento, dias_gracia, estado_pago, bloqueada, motivo_bloqueo,\n                                   CASE WHEN fecha_vencimiento IS NULL THEN NULL ELSE DATEDIFF(fecha_vencimiento, CURDATE()) END AS dias_restantes\n                            FROM empresas\n                            ORDER BY id DESC\n                            LIMIT 200");
    if ($resE) {
        while ($row = $resE->fetch_assoc()) {
            $empresas[] = $row;
        }
    }

    if ($selectedId > 0) {
        $stmt = $mysqli->prepare("SELECT id, nombre, estado, precio_mensual, fecha_inicio_servicio, fecha_vencimiento, dias_gracia, estado_pago, bloqueada, motivo_bloqueo,\n                                         CASE WHEN fecha_vencimiento IS NULL THEN NULL ELSE DATEDIFF(fecha_vencimiento, CURDATE()) END AS dias_restantes\n                                  FROM empresas WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $selectedId);
            if ($stmt->execute()) {
                $empresa = $stmt->get_result()->fetch_assoc();
            }
        }
    }
}

?>

<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-buildings"></i></span>
            <div>
                <h1>Empresas</h1>
                <p>Administración de servicios, pagos y accesos por tenant</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createEmpresaModal"><i class="bi bi-plus-lg"></i> Nueva empresa</button>
        </div>
    </div>
</div>

<?php if ($err !== ''): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo html($err); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($warn !== ''): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo html($warn); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($msg !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?php echo html($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card settings-card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-list-ul"></i> Lista de empresas</strong>
        <div class="text-muted" style="font-size: 0.92rem;">Selecciona una empresa para ver detalle y acciones</div>
    </div>
    <div class="card-body">
        <?php if (!$hasEmpresas): ?>
            <div class="alert alert-warning mb-0">No se pudo acceder a la tabla <strong>empresas</strong>. Ejecuta la migración en la BD correcta.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
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
                                <td class="text-muted" colspan="7">No hay empresas registradas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($empresas as $e): ?>
                                <?php
                                $id = (int)($e['id'] ?? 0);
                                $dias = ($e['dias_restantes'] ?? null);
                                $isBlocked = (int)($e['bloqueada'] ?? 0) === 1;
                                $estadoPago = (string)($e['estado_pago'] ?? '');
                                $badge = 'bg-secondary';
                                if ($estadoPago === 'al_dia') $badge = 'bg-success';
                                if ($estadoPago === 'vencido') $badge = 'bg-warning text-dark';
                                if ($estadoPago === 'suspendido') $badge = 'bg-danger';
                                ?>
                                <tr <?php echo ($selectedId === $id) ? 'style="background: rgba(29, 78, 216, 0.05);"' : ''; ?>>
                                    <td class="fw-semibold"><a href="empresas.php?id=<?php echo (int)$id; ?>" style="text-decoration:none;"><?php echo html((string)($e['nombre'] ?? '')); ?></a></td>
                                    <td><?php echo html((string)($e['estado'] ?? '')); ?></td>
                                    <td><?php echo html((string)($e['fecha_vencimiento'] ?? '')); ?></td>
                                    <td><?php echo ($dias === null) ? '<span class="text-muted">-</span>' : html((string)(int)$dias); ?></td>
                                    <td><span class="badge <?php echo html($badge); ?>"><?php echo html($estadoPago !== '' ? $estadoPago : '-'); ?></span></td>
                                    <td>
                                        <?php if ($isBlocked): ?>
                                            <span class="badge bg-danger">Sí</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="empresas.php?id=<?php echo (int)$id; ?>" class="btn btn-outline-primary"><i class="bi bi-eye"></i></a>
                                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editEmpresaModal" data-empresa-id="<?php echo (int)$id; ?>" data-empresa-nombre="<?php echo html((string)($e['nombre'] ?? '')); ?>" data-empresa-inicio="<?php echo html((string)($e['fecha_inicio_servicio'] ?? '')); ?>" data-empresa-vencimiento="<?php echo html((string)($e['fecha_vencimiento'] ?? '')); ?>" data-empresa-gracia="<?php echo html((string)($e['dias_gracia'] ?? '0')); ?>" data-empresa-precio="<?php echo html((string)($e['precio_mensual'] ?? '0')); ?>" title="Editar datos"><i class="bi bi-pencil-square"></i></button>
                                            <?php if ($isBlocked): ?>
                                                <form method="post" action="empresas.php?id=<?php echo (int)$id; ?>" class="d-inline">
                                                    <?php csrfField(); ?>
                                                    <input type="hidden" name="action" value="unblock">
                                                    <input type="hidden" name="empresa_id" value="<?php echo (int)$id; ?>">
                                                    <button class="btn btn-outline-success" type="submit" title="Desbloquear"><i class="bi bi-unlock"></i></button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#blockModal" data-empresa-id="<?php echo (int)$id; ?>" data-empresa-nombre="<?php echo html((string)($e['nombre'] ?? '')); ?>"><i class="bi bi-lock"></i></button>
                                            <?php endif; ?>
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

<div class="card settings-card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-building-check"></i> Detalle de empresa</strong>
        <?php if ($empresa): ?>
            <div class="d-flex gap-2 flex-wrap">
                <?php $eid = (int)($empresa['id'] ?? 0); $blocked = (int)($empresa['bloqueada'] ?? 0) === 1; ?>
                <?php if ($blocked): ?>
                    <form method="post" action="empresas.php?id=<?php echo (int)$eid; ?>" class="d-inline">
                        <?php csrfField(); ?>
                        <input type="hidden" name="action" value="unblock">
                        <input type="hidden" name="empresa_id" value="<?php echo (int)$eid; ?>">
                        <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-unlock"></i> Desbloquear</button>
                    </form>
                <?php else: ?>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#blockModal" data-empresa-id="<?php echo (int)$eid; ?>" data-empresa-nombre="<?php echo html((string)($empresa['nombre'] ?? '')); ?>"><i class="bi bi-lock"></i> Bloquear</button>
                <?php endif; ?>

                <?php if ((string)($empresa['estado'] ?? '') === 'activa'): ?>
                    <form method="post" action="empresas.php?id=<?php echo (int)$eid; ?>" class="d-inline">
                        <?php csrfField(); ?>
                        <input type="hidden" name="action" value="suspend">
                        <input type="hidden" name="empresa_id" value="<?php echo (int)$eid; ?>">
                        <button class="btn btn-outline-warning btn-sm" type="submit"><i class="bi bi-pause-circle"></i> Suspender</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="empresas.php?id=<?php echo (int)$eid; ?>" class="d-inline">
                        <?php csrfField(); ?>
                        <input type="hidden" name="action" value="activate">
                        <input type="hidden" name="empresa_id" value="<?php echo (int)$eid; ?>">
                        <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-play-circle"></i> Activar</button>
                    </form>
                <?php endif; ?>

                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editEmpresaModal" data-empresa-id="<?php echo (int)$eid; ?>" data-empresa-nombre="<?php echo html((string)($empresa['nombre'] ?? '')); ?>" data-empresa-inicio="<?php echo html((string)($empresa['fecha_inicio_servicio'] ?? '')); ?>" data-empresa-vencimiento="<?php echo html((string)($empresa['fecha_vencimiento'] ?? '')); ?>" data-empresa-gracia="<?php echo html((string)($empresa['dias_gracia'] ?? '0')); ?>" data-empresa-precio="<?php echo html((string)($empresa['precio_mensual'] ?? '0')); ?>"><i class="bi bi-pencil-square"></i> Editar</button>

                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal" data-empresa-id="<?php echo (int)$eid; ?>" data-empresa-nombre="<?php echo html((string)($empresa['nombre'] ?? '')); ?>"><i class="bi bi-trash"></i> Eliminar</button>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!$empresa): ?>
            <div class="text-muted">Selecciona una empresa para ver el detalle.</div>
        <?php else: ?>
            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="fw-semibold mb-2">Datos generales</div>
                            <div class="row g-2">
                                <div class="col-6 text-muted">Nombre</div>
                                <div class="col-6"><?php echo html((string)($empresa['nombre'] ?? '')); ?></div>
                                <div class="col-6 text-muted">Fecha inicio servicio</div>
                                <div class="col-6"><?php echo html((string)($empresa['fecha_inicio_servicio'] ?? '')); ?></div>
                                <div class="col-6 text-muted">Precio mensual</div>
                                <div class="col-6"><?php echo html((string)($empresa['precio_mensual'] ?? '0')); ?></div>
                                <div class="col-6 text-muted">Días de gracia</div>
                                <div class="col-6"><?php echo html((string)($empresa['dias_gracia'] ?? '0')); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="fw-semibold mb-2">Estado de servicio</div>
                            <div class="row g-2">
                                <div class="col-6 text-muted">Estado</div>
                                <div class="col-6"><?php echo html((string)($empresa['estado'] ?? '')); ?></div>
                                <div class="col-6 text-muted">Fecha vencimiento</div>
                                <div class="col-6"><?php echo html((string)($empresa['fecha_vencimiento'] ?? '')); ?></div>
                                <div class="col-6 text-muted">Días restantes</div>
                                <div class="col-6"><?php echo ($empresa['dias_restantes'] ?? null) === null ? '<span class="text-muted">-</span>' : html((string)(int)$empresa['dias_restantes']); ?></div>
                                <div class="col-6 text-muted">Estado de pago</div>
                                <div class="col-6"><?php echo html((string)($empresa['estado_pago'] ?? '')); ?></div>
                                <div class="col-6 text-muted">Bloqueada</div>
                                <div class="col-6"><?php echo ((int)($empresa['bloqueada'] ?? 0) === 1) ? '<span class="badge bg-danger">Sí</span>' : '<span class="badge bg-success">No</span>'; ?></div>
                                <div class="col-6 text-muted">Motivo de bloqueo</div>
                                <div class="col-6"><?php echo html((string)($empresa['motivo_bloqueo'] ?? '')); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mt-3 mb-0">Acciones de pagos y extensión del servicio se implementarán en <strong>Facturación</strong>.</div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="createEmpresaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="empresas.php">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Nueva empresa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="create">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Precio mensual</label>
                            <input type="number" step="0.01" min="0" name="precio_mensual" class="form-control" value="0">
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
                    <button type="submit" class="btn btn-primary">Crear empresa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editEmpresaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="empresas.php">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Actualizar datos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="update_data">
                    <input type="hidden" name="empresa_id" id="editEmpresaId" value="">
                    <div class="mb-2 text-muted">Empresa: <strong id="editEmpresaNombreLabel"></strong></div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" id="editEmpresaNombre" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Precio mensual</label>
                            <input type="number" step="0.01" min="0" name="precio_mensual" id="editEmpresaPrecio" class="form-control" value="0">
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
                            <input type="number" min="0" name="dias_gracia" id="editEmpresaGracia" class="form-control" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="blockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="empresas.php">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-lock me-2"></i>Bloquear empresa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="block">
                    <input type="hidden" name="empresa_id" id="blockEmpresaId" value="">
                    <div class="mb-2 text-muted">Empresa: <strong id="blockEmpresaNombre"></strong></div>
                    <label class="form-label">Motivo de bloqueo</label>
                    <input type="text" name="motivo_bloqueo" class="form-control" value="Pago mensual vencido">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Bloquear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="empresas.php">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Eliminar empresa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="empresa_id" id="deleteEmpresaId" value="">
                    <div class="alert alert-warning mb-0">
                        Esta acción es permanente. Empresa: <strong id="deleteEmpresaNombre"></strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
        });
    }

    var blockModal = document.getElementById('blockModal');
    if (blockModal) {
        blockModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            document.getElementById('blockEmpresaId').value = btn.getAttribute('data-empresa-id') || '';
            document.getElementById('blockEmpresaNombre').textContent = btn.getAttribute('data-empresa-nombre') || '';
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
});
</script>

<?php
$content = (string)ob_get_clean();
$currentRoute = 'empresas';
require __DIR__ . '/layout.php';
