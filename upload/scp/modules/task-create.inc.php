<?php
// task-create.inc.php - Formulario para crear nueva tarea
?>

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
                        <label for="assigned_to" class="form-label">Asignar a</label>
                        <select class="form-select" id="assigned_to" name="assigned_to">
                            <option value="">Sin asignar</option>
                            <?php foreach ($staff_list as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>" <?php echo (isset($_POST['assigned_to']) && $_POST['assigned_to'] == $staff['id']) ? 'selected' : ''; ?>>
                                    <?php echo html($staff['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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

<script>
// Auto-resize textarea
document.getElementById('description').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = this.scrollHeight + 'px';
});

// Auto-ocultar alertas de éxito después de 4 segundos
document.addEventListener('DOMContentLoaded', function() {
    const successAlerts = document.querySelectorAll('.alert-success');
    successAlerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 4000); // 4 segundos
    });
});
</script>