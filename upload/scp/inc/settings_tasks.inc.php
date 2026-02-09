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
        $task_number_format = trim((string)($_POST['task_number_format'] ?? ''));
        $task_sequence_id = (string)($_POST['task_sequence_id'] ?? '0');
        $default_task_priority_id = (string)($_POST['default_task_priority_id'] ?? '');

        if ($task_number_format === '') {
            $error = 'Formato de número de tarea es requerido.';
        } else {
            setAppSetting('tasks.task_number_format', $task_number_format);
            setAppSetting('tasks.task_sequence_id', $task_sequence_id);
            setAppSetting('tasks.default_task_priority_id', $default_task_priority_id);

            $msg = 'Cambios guardados correctamente.';
        }
    }
}

$task_number_format = (string)getAppSetting('tasks.task_number_format', '#');
$task_sequence_id = (string)getAppSetting('tasks.task_sequence_id', '0');
$default_task_priority_id = (string)getAppSetting('tasks.default_task_priority_id', '');

if ($default_task_priority_id === '' && !empty($priorities)) {
    foreach ($priorities as $p) {
        $name = strtolower(trim((string)($p['name'] ?? '')));
        if ($name === 'low' || $name === 'baja') {
            $default_task_priority_id = (string)($p['id'] ?? '');
            break;
        }
    }
    if ($default_task_priority_id === '') {
        $default_task_priority_id = (string)($priorities[0]['id'] ?? '');
    }
}

$formatExample = '';
if ($task_sequence_id !== '0' && $hasSequences) {
    foreach ($sequences as $seq) {
        if ((string)($seq['id'] ?? '') === $task_sequence_id) {
            $next = (int)($seq['next'] ?? 1);
            $padding = (int)($seq['padding'] ?? 0);
            $formatExample = $padding > 0
                ? str_pad((string)$next, $padding, '0', STR_PAD_LEFT)
                : (string)$next;
            break;
        }
    }
}
if ($formatExample === '') {
    $formatExample = str_replace('#', '2', $task_number_format);
}

ob_start();
?>

<div class="settings-hero" id="settings">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="settings-hero-icon"><i class="bi bi-check2-square"></i></span>
            <div>
                <h1>Tareas</h1>
                <p>Ajustes de Tareas y opciones</p>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo html($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($msg)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo html($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form method="post" class="row g-3">
    <?php csrfField(); ?>

    <div class="col-12">
        <div class="card settings-card">
            <div class="card-header"><strong>Configuración</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label class="form-label">Formato por defecto del número de las tareas</label>
                        <input type="text" class="form-control" name="task_number_format" value="<?php echo html($task_number_format); ?>">
                        <div class="form-text">Ej. <?php echo html($formatExample); ?></div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Secuencia por defecto del número de la tarea</label>
                        <div class="input-group">
                            <select class="form-select" name="task_sequence_id" id="task_sequence_id">
                                <option value="0" <?php echo $task_sequence_id === '0' ? 'selected' : ''; ?>>— Aleatorio —</option>
                                <?php if ($hasSequences): ?>
                                    <?php foreach ($sequences as $seq): $sid = (string)($seq['id'] ?? ''); ?>
                                        <option value="<?php echo html($sid); ?>" <?php echo $task_sequence_id === $sid ? 'selected' : ''; ?>><?php echo html((string)($seq['name'] ?? '')); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <?php $seqManageUrl = 'sequences.php?embed=1'; ?>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#taskSequenceManageModal" title="Administrar secuencias">
                                <i class="bi bi-gear"></i> Administrar
                            </button>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Prioridad predeterminada</label>
                        <select class="form-select" name="default_task_priority_id">
                            <?php foreach ($priorities as $p): $pid = (string)($p['id'] ?? ''); ?>
                                <option value="<?php echo html($pid); ?>" <?php echo $default_task_priority_id === $pid ? 'selected' : ''; ?>><?php echo html((string)($p['name'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
        <a class="btn btn-outline-secondary" href="settings.php?t=tasks#settings">Restaurar</a>
    </div>
</form>

<div class="modal fade" id="taskSequenceManageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Administrar secuencias</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="height:min(60vh, 520px);">
                <iframe
                    id="taskSequenceManageFrame"
                    src="<?php echo html($seqManageUrl); ?>"
                    style="width:100%;height:100%;border:0;display:block;"
                    loading="lazy"
                    referrerpolicy="no-referrer"
                ></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="taskSequenceManageRefresh">Recargar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    var btn = document.getElementById('taskSequenceManageRefresh');
    var frame = document.getElementById('taskSequenceManageFrame');
    if (btn && frame) {
        btn.addEventListener('click', function(){
            try { frame.contentWindow.location.reload(); } catch(e) { frame.src = frame.src; }
        });
    }
})();
</script>

<?php
$content = ob_get_clean();
