<?php

if (isset($_SESSION['flash_msg'])) {
    $msg = (string)$_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}
if (isset($_SESSION['flash_error'])) {
    $error = (string)$_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

$priorities = [];
if (isset($mysqli) && $mysqli) {
    $res = $mysqli->query('SELECT id, name FROM priorities ORDER BY level');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $priorities[] = $row;
        }
    }
}

$eid = empresaId();
$departmentsHasEmpresaId = false;
$staffHasEmpresaId = false;
if (isset($mysqli) && $mysqli) {
    try {
        $res = $mysqli->query("SHOW COLUMNS FROM departments LIKE 'empresa_id'");
        $departmentsHasEmpresaId = ($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        $departmentsHasEmpresaId = false;
    }
    try {
        $res = $mysqli->query("SHOW COLUMNS FROM staff LIKE 'empresa_id'");
        $staffHasEmpresaId = ($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        $staffHasEmpresaId = false;
    }
}

// Asignación por defecto por departamento
$generalDeptId = 0;
if (isset($mysqli) && $mysqli) {
    $sqlG = "SELECT id FROM departments WHERE LOWER(name) LIKE '%general%'";
    if ($departmentsHasEmpresaId) {
        $sqlG .= ' AND empresa_id = ' . (int)$eid;
    }
    $sqlG .= ' LIMIT 1';
    $rgd = $mysqli->query($sqlG);
    if ($rgd && ($row = $rgd->fetch_assoc())) {
        $generalDeptId = (int) ($row['id'] ?? 0);
    }
}

$hasDeptDefaultStaff = false;
if (isset($mysqli) && $mysqli) {
    $chkCol = $mysqli->query("SHOW COLUMNS FROM departments LIKE 'default_staff_id'");
    if ($chkCol && $chkCol->num_rows > 0) {
        $hasDeptDefaultStaff = true;
    } else {
        $mysqli->query("ALTER TABLE departments ADD COLUMN default_staff_id INT NULL");
        $chkCol2 = $mysqli->query("SHOW COLUMNS FROM departments LIKE 'default_staff_id'");
        $hasDeptDefaultStaff = $chkCol2 && $chkCol2->num_rows > 0;
    }
}

$departments = [];
$agents = [];
if (isset($mysqli) && $mysqli) {
    $sqlD = 'SELECT id, name, default_staff_id FROM departments WHERE is_active = 1';
    if ($departmentsHasEmpresaId) {
        $sqlD .= ' AND empresa_id = ' . (int)$eid;
    }
    $sqlD .= ' ORDER BY name';
    $res = $mysqli->query($sqlD);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $departments[] = $row;
        }
    }
    $sqlA = 'SELECT id, firstname, lastname, dept_id FROM staff WHERE is_active = 1';
    if ($staffHasEmpresaId) {
        $sqlA .= ' AND empresa_id = ' . (int)$eid;
    }
    $sqlA .= ' ORDER BY firstname, lastname';
    $resA = $mysqli->query($sqlA);
    if ($resA) {
        while ($row = $resA->fetch_assoc()) {
            $agents[] = $row;
        }
    }
}

$statuses = [];
if (isset($mysqli) && $mysqli) {
    $res = $mysqli->query('SELECT id, name FROM ticket_status ORDER BY order_by, id');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $statuses[] = $row;
        }
    }
}

$helpTopics = [];
$hasHelpTopics = false;
if (isset($mysqli) && $mysqli) {
    $chk = $mysqli->query("SHOW TABLES LIKE 'help_topics'");
    $hasHelpTopics = $chk && $chk->num_rows > 0;
    if ($hasHelpTopics) {
        $res = $mysqli->query('SELECT id, name FROM help_topics WHERE is_active = 1 ORDER BY name');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $helpTopics[] = $row;
            }
        }
    }
}

$sequences = [];
$hasSequences = false;
if (isset($mysqli) && $mysqli) {
    $chkSeq = $mysqli->query("SHOW TABLES LIKE 'sequences'");
    $hasSequences = $chkSeq && $chkSeq->num_rows > 0;
    if ($hasSequences) {
        $res = $mysqli->query('SELECT id, name, next, increment, padding FROM sequences ORDER BY id');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $sequences[] = $row;
            }
        }
    }
}

if ($_POST) {
    if (!validateCSRF()) {
        $error = 'Token de seguridad inválido';
    } else {
        $ticket_number_format = trim((string)($_POST['ticket_number_format'] ?? ''));
        $ticket_sequence_id = (string)($_POST['ticket_sequence_id'] ?? '0');

        $queue_bucket_counts = isset($_POST['queue_bucket_counts']) ? '1' : '0';

        $default_ticket_status_id = (string)($_POST['default_ticket_status_id'] ?? '');
        $default_priority_id = (string)($_POST['default_priority_id'] ?? '');
        $default_sla_id = (string)($_POST['default_sla_id'] ?? '0');
        $default_help_topic = (string)($_POST['default_help_topic'] ?? '0');

        $ticket_lock = (string)($_POST['ticket_lock'] ?? 'activity');
        if (!in_array($ticket_lock, ['disabled', 'view', 'activity'], true)) {
            $ticket_lock = 'activity';
        }

        $default_ticket_queue = (string)($_POST['default_ticket_queue'] ?? 'open');
        if (!in_array($default_ticket_queue, ['open'], true)) {
            $default_ticket_queue = 'open';
        }

        $max_open_tickets = (string)($_POST['max_open_tickets'] ?? '0');
        if ($max_open_tickets === '') $max_open_tickets = '0';
        $collaborator_ticket_visibility = isset($_POST['collaborator_ticket_visibility']) ? '1' : '0';
        $auto_claim_tickets = isset($_POST['auto_claim_tickets']) ? '1' : '0';
        $auto_refer_closed = isset($_POST['auto_refer_closed']) ? '1' : '0';
        $require_topic_to_close = isset($_POST['require_topic_to_close']) ? '1' : '0';
        $allow_external_images = isset($_POST['allow_external_images']) ? '1' : '0';

        $ticket_max_file_mb = (string)($_POST['ticket_max_file_mb'] ?? '10');
        $ticket_max_uploads = (string)($_POST['ticket_max_uploads'] ?? '5');

        if ($ticket_number_format === '') {
            $error = 'Formato de número de ticket es requerido.';
        } elseif (!ctype_digit($ticket_max_file_mb) || (int)$ticket_max_file_mb < 1 || (int)$ticket_max_file_mb > 256) {
            $error = 'Tamaño máximo de adjunto debe estar entre 1 y 256 MB.';
        } elseif (!ctype_digit($ticket_max_uploads) || (int)$ticket_max_uploads < 0 || (int)$ticket_max_uploads > 20) {
            $error = 'Máximas subidas debe estar entre 0 y 20.';
        } elseif ($max_open_tickets !== '0' && (!ctype_digit($max_open_tickets) || (int)$max_open_tickets < 0 || (int)$max_open_tickets > 999)) {
            $error = 'Máximo de tickets abiertos debe estar entre 0 y 999.';
        } else {
            setAppSetting('tickets.ticket_number_format', $ticket_number_format);
            setAppSetting('tickets.ticket_sequence_id', $ticket_sequence_id);
            setAppSetting('tickets.queue_bucket_counts', $queue_bucket_counts);
            setAppSetting('tickets.default_ticket_status_id', $default_ticket_status_id);
            setAppSetting('tickets.default_priority_id', $default_priority_id);
            setAppSetting('tickets.default_sla_id', $default_sla_id);
            setAppSetting('tickets.default_help_topic', $default_help_topic);
            setAppSetting('tickets.ticket_lock', $ticket_lock);
            setAppSetting('tickets.default_ticket_queue', $default_ticket_queue);
            setAppSetting('tickets.max_open_tickets', $max_open_tickets);
            setAppSetting('tickets.collaborator_ticket_visibility', $collaborator_ticket_visibility);
            setAppSetting('tickets.auto_claim_tickets', $auto_claim_tickets);
            setAppSetting('tickets.auto_refer_closed', $auto_refer_closed);
            setAppSetting('tickets.require_topic_to_close', $require_topic_to_close);
            setAppSetting('tickets.allow_external_images', $allow_external_images);
            setAppSetting('tickets.ticket_max_file_mb', $ticket_max_file_mb);
            setAppSetting('tickets.ticket_max_uploads', $ticket_max_uploads);

            if ($hasDeptDefaultStaff && !empty($departments)) {
                $deptDefaults = $_POST['dept_default_staff'] ?? [];
                if (!is_array($deptDefaults)) $deptDefaults = [];

                foreach ($departments as $d) {
                    $deptId = (int)($d['id'] ?? 0);
                    if ($deptId <= 0) continue;

                    $raw = $deptDefaults[$deptId] ?? '0';
                    $staffId = is_numeric($raw) ? (int)$raw : 0;
                    $staffId = $staffId > 0 ? $staffId : null;

                    if ($staffId !== null) {
                        $allowed = false;
                        $sqlSd = 'SELECT COALESCE(NULLIF(dept_id, 0), ?) AS dept_id FROM staff WHERE id = ? AND is_active = 1';
                        if ($staffHasEmpresaId) {
                            $sqlSd .= ' AND empresa_id = ?';
                        }
                        $sqlSd .= ' LIMIT 1';
                        $stmtSd = $mysqli->prepare($sqlSd);
                        if ($stmtSd) {
                            if ($staffHasEmpresaId) {
                                $stmtSd->bind_param('iii', $generalDeptId, $staffId, $eid);
                            } else {
                                $stmtSd->bind_param('ii', $generalDeptId, $staffId);
                            }
                            if ($stmtSd->execute()) {
                                $sdept = (int)($stmtSd->get_result()->fetch_assoc()['dept_id'] ?? 0);
                                $allowed = ($sdept === $deptId);
                            }
                        }
                        if (!$allowed) {
                            $error = 'El agente seleccionado no pertenece al departamento: ' . (string)($d['name'] ?? '');
                            break;
                        }
                    }

                    $sqlUp = 'UPDATE departments SET default_staff_id = ? WHERE id = ?';
                    if ($departmentsHasEmpresaId) {
                        $sqlUp .= ' AND empresa_id = ?';
                    }
                    $stmtUp = $mysqli->prepare($sqlUp);
                    if ($stmtUp) {
                        if ($departmentsHasEmpresaId) {
                            $stmtUp->bind_param('iii', $staffId, $deptId, $eid);
                        } else {
                            $stmtUp->bind_param('ii', $staffId, $deptId);
                        }
                        $stmtUp->execute();
                    }
                }
            }

            if ($error === '') {
                $msg = 'Cambios guardados correctamente.';
            }
        }
    }
}

if ($_POST) {
    $_SESSION['flash_msg'] = (string)$msg;
    $_SESSION['flash_error'] = (string)$error;
    header('Location: settings.php?t=tickets');
    exit;
}

$ticket_number_format = (string)getAppSetting('tickets.ticket_number_format', '######');
$ticket_sequence_id = (string)getAppSetting('tickets.ticket_sequence_id', '0');
$queue_bucket_counts = (string)getAppSetting('tickets.queue_bucket_counts', '1') === '1';
$default_ticket_status_id = (string)getAppSetting('tickets.default_ticket_status_id', '');
$default_priority_id = (string)getAppSetting('tickets.default_priority_id', '');
$default_sla_id = (string)getAppSetting('tickets.default_sla_id', '1');
$default_help_topic = (string)getAppSetting('tickets.default_help_topic', '0');
$ticket_lock = (string)getAppSetting('tickets.ticket_lock', 'activity');
$default_ticket_queue = (string)getAppSetting('tickets.default_ticket_queue', 'open');
$max_open_tickets = (string)getAppSetting('tickets.max_open_tickets', '0');
$collaborator_ticket_visibility = (string)getAppSetting('tickets.collaborator_ticket_visibility', '1') === '1';
$auto_claim_tickets = (string)getAppSetting('tickets.auto_claim_tickets', '1') === '1';
$auto_refer_closed = (string)getAppSetting('tickets.auto_refer_closed', '1') === '1';
$require_topic_to_close = (string)getAppSetting('tickets.require_topic_to_close', '0') === '1';
$allow_external_images = (string)getAppSetting('tickets.allow_external_images', '0') === '1';
$ticket_max_file_mb = (string)getAppSetting('tickets.ticket_max_file_mb', '10');
$ticket_max_uploads = (string)getAppSetting('tickets.ticket_max_uploads', '5');

if ($default_ticket_status_id === '' && !empty($statuses)) {
    foreach ($statuses as $st) {
        $name = strtolower(trim((string)($st['name'] ?? '')));
        if ($name === 'open' || str_contains($name, 'abiert')) {
            $default_ticket_status_id = (string)($st['id'] ?? '');
            break;
        }
    }
}

if ($default_priority_id === '' && !empty($priorities)) {
    foreach ($priorities as $p) {
        $name = strtolower(trim((string)($p['name'] ?? '')));
        if ($name === 'normal') {
            $default_priority_id = (string)($p['id'] ?? '');
            break;
        }
    }
}

$formatExample = '';
try {
    $digits = '';
    $n = 6;
    if (preg_match('/^#+$/', $ticket_number_format)) {
        $n = strlen($ticket_number_format);
    }
    for ($i = 0; $i < $n; $i++) {
        $digits .= (string)random_int(0, 9);
    }
    $formatExample = $digits;
} catch (Throwable $e) {
    $formatExample = '610781';
}

if ($hasSequences && $ticket_sequence_id !== '0') {
    foreach ($sequences as $seq) {
        if ((string)$seq['id'] === $ticket_sequence_id) {
            $next = (int)($seq['next'] ?? 1);
            $padding = (int)($seq['padding'] ?? 0);
            if ($padding > 0) {
                $formatExample = str_pad((string)$next, $padding, '0', STR_PAD_LEFT);
            } else {
                $formatExample = (string)$next;
            }
            break;
        }
    }
}

ob_start();
?>
<div class="settings-hero">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-ticket-perforated"></i></span>
            <div>
                <h1>Solicitudes</h1>
                <p>Ajustes de Ticket y opciones</p>
            </div>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo html($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo html($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form method="post" class="row g-3">
    <?php csrfField(); ?>

    <div class="col-12">
        <div class="card settings-card">
            <div class="card-header"><strong>Todo el sistema por defecto</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label class="form-label">Formato de número de ticket por defecto</label>
                        <input type="text" class="form-control" name="ticket_number_format" value="<?php echo html($ticket_number_format); ?>">
                        <div class="form-text">Ej. <?php echo html($formatExample); ?></div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Secuencia del número de ticket por defecto</label>
                        <div class="input-group">
                            <select class="form-select" name="ticket_sequence_id" id="ticket_sequence_id">
                                <option value="0" <?php echo $ticket_sequence_id === '0' ? 'selected' : ''; ?>>— Aleatorio —</option>
                                <?php if ($hasSequences): ?>
                                    <?php foreach ($sequences as $seq): $sid = (string)($seq['id'] ?? ''); ?>
                                        <option value="<?php echo html($sid); ?>" <?php echo $ticket_sequence_id === $sid ? 'selected' : ''; ?>><?php echo html((string)($seq['name'] ?? '')); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <?php $seqManageUrl = (defined('APP_URL') ? rtrim((string)APP_URL, '/') : '') . '/upload/scp/sequences.php?embed=1'; ?>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#sequenceManageModal" title="Gestionar secuencias">
                                <i class="bi bi-gear"></i> Gestionar
                            </button>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="queue_bucket_counts" name="queue_bucket_counts" value="1" <?php echo $queue_bucket_counts ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="queue_bucket_counts">Recuento de boletos de nivel superior: Habilitar</label>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Estado predeterminado</label>
                        <select class="form-select" name="default_ticket_status_id">
                            <?php foreach ($statuses as $st): $sid = (string)($st['id'] ?? ''); ?>
                                <option value="<?php echo html($sid); ?>" <?php echo $default_ticket_status_id === $sid ? 'selected' : ''; ?>><?php echo html((string)($st['name'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Prioridad predeterminada</label>
                        <select class="form-select" name="default_priority_id">
                            <?php foreach ($priorities as $p): $pid = (string)($p['id'] ?? ''); ?>
                                <option value="<?php echo html($pid); ?>" <?php echo $default_priority_id === $pid ? 'selected' : ''; ?>><?php echo html((string)($p['name'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">SLA por defecto</label>
                        <select class="form-select" name="default_sla_id">
                            <option value="1" <?php echo $default_sla_id === '1' ? 'selected' : ''; ?>>Default SLA (18 horas - Activo)</option>
                            <option value="0" <?php echo $default_sla_id === '0' ? 'selected' : ''; ?>>— Ninguno —</option>
                        </select>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Tema de ayuda por defecto</label>
                        <select class="form-select" name="default_help_topic" <?php echo $hasHelpTopics ? '' : 'disabled'; ?>>
                            <option value="0" <?php echo $default_help_topic === '0' ? 'selected' : ''; ?>>— Ninguno —</option>
                            <?php if ($hasHelpTopics): ?>
                                <?php foreach ($helpTopics as $t): $tid0 = (string)($t['id'] ?? '0'); ?>
                                    <option value="<?php echo html($tid0); ?>" <?php echo $default_help_topic === $tid0 ? 'selected' : ''; ?>><?php echo html((string)($t['name'] ?? '')); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Semántica de bloqueo</label>
                        <select class="form-select" name="ticket_lock">
                            <option value="activity" <?php echo $ticket_lock === 'activity' ? 'selected' : ''; ?>>Bloqueo en actividad</option>
                            <option value="view" <?php echo $ticket_lock === 'view' ? 'selected' : ''; ?>>Bloqueo al ver</option>
                            <option value="disabled" <?php echo $ticket_lock === 'disabled' ? 'selected' : ''; ?>>Deshabilitado</option>
                        </select>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Cola de Ticket por defecto</label>
                        <select class="form-select" name="default_ticket_queue">
                            <option value="open" <?php echo $default_ticket_queue === 'open' ? 'selected' : ''; ?>>Open</option>
                        </select>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Máximo número de Tickets abierto</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="max_open_tickets" value="<?php echo html($max_open_tickets); ?>" min="0" max="999">
                            <span class="input-group-text">por usuario final</span>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="collaborator_ticket_visibility" name="collaborator_ticket_visibility" value="1" <?php echo $collaborator_ticket_visibility ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="collaborator_ticket_visibility">Visibilidad de colaboradores de tickets: Habilitar</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="auto_claim_tickets" name="auto_claim_tickets" value="1" <?php echo $auto_claim_tickets ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="auto_claim_tickets">Demande respuesta: Habilitar</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="auto_refer_closed" name="auto_refer_closed" value="1" <?php echo $auto_refer_closed ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="auto_refer_closed">Referencia automática al cerrar: Habilitar</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="require_topic_to_close" name="require_topic_to_close" value="1" <?php echo $require_topic_to_close ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="require_topic_to_close">Es necesario seleccionar un tema de ayuda para cerrar.: Habilitar</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="allow_external_images" name="allow_external_images" value="1" <?php echo $allow_external_images ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="allow_external_images">Permitir imágenes externas: Habilitar</label>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card settings-card">
            <div class="card-header"><strong>Adjuntos</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <label class="form-label">Tamaño máximo por adjunto</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="ticket_max_file_mb" value="<?php echo html($ticket_max_file_mb); ?>" min="1" max="256">
                            <span class="input-group-text">MB</span>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label">Máximas subidas por mensaje</label>
                        <input type="number" class="form-control" name="ticket_max_uploads" value="<?php echo html($ticket_max_uploads); ?>" min="0" max="20">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card settings-card">
            <div class="card-header"><strong>Asignación por departamento</strong></div>
            <div class="card-body">
                <?php if (!$hasDeptDefaultStaff): ?>
                    <div class="alert alert-warning mb-0">No se pudo habilitar la asignación por departamento (columna departments.default_staff_id).</div>
                <?php elseif (empty($departments)): ?>
                    <div class="text-muted">No hay departamentos activos.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($departments as $d): ?>
                            <?php
                            $deptId = (int)($d['id'] ?? 0);
                            $current = (int)($d['default_staff_id'] ?? 0);
                            ?>
                            <div class="col-12 col-lg-6">
                                <label class="form-label">Agente por defecto — <?php echo html((string)($d['name'] ?? '')); ?></label>
                                <select class="form-select" name="dept_default_staff[<?php echo (int)$deptId; ?>]">
                                    <option value="0" <?php echo $current <= 0 ? 'selected' : ''; ?>>— Ninguno —</option>
                                    <?php foreach ($agents as $a): ?>
                                        <?php
                                        $aid = (int)($a['id'] ?? 0);
                                        $aDept = (int)($a['dept_id'] ?? 0);
                                        $allowed = ($aDept === $deptId) || ($generalDeptId > 0 && $aDept === 0 && $generalDeptId === $deptId);
                                        if (!$allowed) continue;
                                        $label = trim((string)($a['firstname'] ?? '') . ' ' . (string)($a['lastname'] ?? ''));
                                        if ($label === '') $label = 'Agente #' . (string)$aid;
                                        ?>
                                        <option value="<?php echo $aid; ?>" <?php echo $current === $aid ? 'selected' : ''; ?>><?php echo html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Si está vacío, los tickets nuevos quedarán sin asignar.</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
        <a class="btn btn-outline-secondary" href="settings.php?t=tickets">Restaurar</a>
    </div>
</form>

<div class="modal fade" id="sequenceManageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gestionar las secuencias</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="height:min(60vh, 520px);">
                <iframe
                    id="sequenceManageFrame"
                    src="<?php echo html($seqManageUrl ?: 'sequences.php?embed=1'); ?>"
                    style="width:100%;height:100%;border:0;display:block;"
                    loading="lazy"
                    referrerpolicy="no-referrer"
                ></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="sequenceManageRefresh">Recargar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    var btn = document.getElementById('sequenceManageRefresh');
    var frame = document.getElementById('sequenceManageFrame');
    if (btn && frame) {
        btn.addEventListener('click', function(){
            try { frame.contentWindow.location.reload(); } catch(e) { frame.src = frame.src; }
        });
    }
})();
</script>
<?php
$content = ob_get_clean();
