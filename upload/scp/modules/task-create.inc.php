<?php
// task-create.inc.php - Formulario para crear nueva tarea
?>

<?php
$agentsByDeptB64 = base64_encode((string)($agentsJson ?: '{}'));
?>

<div id="tasks-data" hidden data-agents-by-dept-b64="<?php echo html($agentsByDeptB64); ?>" data-current-assigned=""></div>

<div class="page-header">
    <h1>Crear Nueva Tarea</h1>
    <p>Asigna una nueva tarea a un agente</p>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo html($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5>Detalles de la Tarea</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="do" value="create">

            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label for="title" class="form-label">Título <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required
                               value="<?php echo html($_POST['title'] ?? ''); ?>" maxlength="255">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Descripción</label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo html($_POST['description'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="dept_id" class="form-label">Departamento <span class="text-danger">*</span></label>
                        <select class="form-select" id="dept_id" name="dept_id" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo (int) $d['id']; ?>" <?php echo (isset($_POST['dept_id']) && (int)$_POST['dept_id'] === (int)$d['id']) ? 'selected' : ''; ?>>
                                    <?php echo html($d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="assigned_to" class="form-label">Asignar a</label>
                        <select class="form-select" id="assigned_to" name="assigned_to" disabled>
                            <option value="">Sin asignar</option>
                        </select>
                        <div id="select-dept-hint" class="form-text" style="display:none; color:#64748b;">Debe seleccionar un departamento primero.</div>
                        <div id="no-agents-hint" class="form-text" style="display:none; color:#b91c1c;">No hay agentes disponibles para este departamento.</div>
                    </div>

                    <div class="mb-3">
                        <label for="priority" class="form-label">Prioridad</label>
                        <select class="form-select" id="priority" name="priority">
                            <option value="low" <?php echo (($_POST['priority'] ?? 'normal') === 'low') ? 'selected' : ''; ?>>Baja</option>
                            <option value="normal" <?php echo (($_POST['priority'] ?? 'normal') === 'normal') ? 'selected' : ''; ?>>Normal</option>
                            <option value="high" <?php echo (($_POST['priority'] ?? 'normal') === 'high') ? 'selected' : ''; ?>>Alta</option>
                            <option value="urgent" <?php echo (($_POST['priority'] ?? 'normal') === 'urgent') ? 'selected' : ''; ?>>Urgente</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="due_date" class="form-label">Fecha Límite</label>
                        <input type="datetime-local" class="form-control" id="due_date" name="due_date"
                               value="<?php echo html($_POST['due_date'] ?? ''); ?>">
                        <div class="form-text">Opcional. Deja vacío si no hay fecha límite.</div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Crear Tarea
                </button>
                <a href="tasks.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>