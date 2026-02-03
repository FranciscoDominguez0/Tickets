<?php
// task-view.inc.php - Vista detallada de una tarea

// Definir etiquetas y colores
$status_labels = [
    'pending' => 'Pendiente',
    'in_progress' => 'En Progreso',
    'completed' => 'Completada',
    'cancelled' => 'Cancelada'
];

$priority_labels = [
    'low' => 'Baja',
    'normal' => 'Normal',
    'high' => 'Alta',
    'urgent' => 'Urgente'
];

$priority_colors = [
    'low' => 'secondary',
    'normal' => 'primary',
    'high' => 'warning',
    'urgent' => 'danger'
];
?>

<div class="page-header">
    <h1>Tarea #<?php echo $taskView['id']; ?></h1>
    <p><?php echo html($taskView['title']); ?></p>
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

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo html($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Información de la tarea -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Detalles de la Tarea</h5>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal">
                        <i class="bi bi-pencil"></i> Editar
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            Acciones
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="changeStatus(<?php echo $taskView['id']; ?>, 'pending')">Marcar Pendiente</a></li>
                            <li><a class="dropdown-item" href="#" onclick="changeStatus(<?php echo $taskView['id']; ?>, 'in_progress')">Marcar En Progreso</a></li>
                            <li><a class="dropdown-item" href="#" onclick="changeStatus(<?php echo $taskView['id']; ?>, 'completed')">Marcar Completada</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteTask(<?php echo $taskView['id']; ?>)">Eliminar Tarea</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-sm-3"><strong>Estado:</strong></div>
                    <div class="col-sm-9">
                        <span class="badge bg-<?php
                            echo $taskView['status'] === 'completed' ? 'success' :
                                 ($taskView['status'] === 'in_progress' ? 'warning' : 'secondary');
                        ?> fs-6">
                            <?php echo $status_labels[$taskView['status']]; ?>
                        </span>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-sm-3"><strong>Prioridad:</strong></div>
                    <div class="col-sm-9">
                        <span class="badge bg-<?php echo $priority_colors[$taskView['priority']]; ?>">
                            <?php echo $priority_labels[$taskView['priority']]; ?>
                        </span>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-sm-3"><strong>Asignado a:</strong></div>
                    <div class="col-sm-9"><?php echo html($taskView['assigned_name'] ?: 'Sin asignar'); ?></div>
                </div>

                <div class="row mb-3">
                    <div class="col-sm-3"><strong>Creado por:</strong></div>
                    <div class="col-sm-9"><?php echo html($taskView['created_name']); ?></div>
                </div>

                <div class="row mb-3">
                    <div class="col-sm-3"><strong>Fecha de creación:</strong></div>
                    <div class="col-sm-9"><?php echo date('d/m/Y H:i', strtotime($taskView['created'])); ?></div>
                </div>

                <div class="row mb-3">
                    <div class="col-sm-3"><strong>Última actualización:</strong></div>
                    <div class="col-sm-9"><?php echo date('d/m/Y H:i', strtotime($taskView['updated'])); ?></div>
                </div>

                <?php if ($taskView['due_date']): ?>
                    <div class="row mb-3">
                        <div class="col-sm-3"><strong>Fecha límite:</strong></div>
                        <div class="col-sm-9">
                            <?php
                            $due_date = strtotime($taskView['due_date']);
                            $is_overdue = $due_date < time() && $taskView['status'] !== 'completed';
                            ?>
                            <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                <?php echo date('d/m/Y H:i', $due_date); ?>
                            </span>
                            <?php if ($is_overdue): ?>
                                <span class="badge bg-danger ms-2">VENCIDA</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-sm-3"><strong>Descripción:</strong></div>
                    <div class="col-sm-9">
                        <?php if ($taskView['description']): ?>
                            <div class="task-description">
                                <?php echo nl2br(html($taskView['description'])); ?>
                            </div>
                        <?php else: ?>
                            <em class="text-muted">Sin descripción</em>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Acciones rápidas -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Acciones Rápidas</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-success" onclick="changeStatus(<?php echo $taskView['id']; ?>, 'completed')">
                        <i class="bi bi-check-circle"></i> Marcar como Completada
                    </button>
                    <button type="button" class="btn btn-outline-warning" onclick="changeStatus(<?php echo $taskView['id']; ?>, 'in_progress')">
                        <i class="bi bi-play-circle"></i> Marcar En Progreso
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="changeStatus(<?php echo $taskView['id']; ?>, 'pending')">
                        <i class="bi bi-pause-circle"></i> Marcar como Pendiente
                    </button>
                </div>

                <hr>

                <div class="d-grid gap-2">
                    <a href="tasks.php?a=create" class="btn btn-outline-primary">
                        <i class="bi bi-plus-lg"></i> Crear Nueva Tarea
                    </a>
                    <a href="tasks.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Volver al Listado
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para editar tarea -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Tarea</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="do" value="edit">

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="edit_title" class="form-label">Título <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_title" name="title" required
                                       value="<?php echo html($taskView['title']); ?>" maxlength="255">
                            </div>

                            <div class="mb-3">
                                <label for="edit_description" class="form-label">Descripción</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="4"><?php echo html($taskView['description']); ?></textarea>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_assigned_to" class="form-label">Asignar a</label>
                                <select class="form-select" id="edit_assigned_to" name="assigned_to">
                                    <option value="">Sin asignar</option>
                                    <?php foreach ($staff_list as $staff): ?>
                                        <option value="<?php echo $staff['id']; ?>" <?php echo $taskView['assigned_to'] == $staff['id'] ? 'selected' : ''; ?>>
                                            <?php echo html($staff['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="edit_priority" class="form-label">Prioridad</label>
                                <select class="form-select" id="edit_priority" name="priority">
                                    <option value="low" <?php echo $taskView['priority'] === 'low' ? 'selected' : ''; ?>>Baja</option>
                                    <option value="normal" <?php echo $taskView['priority'] === 'normal' ? 'selected' : ''; ?>>Normal</option>
                                    <option value="high" <?php echo $taskView['priority'] === 'high' ? 'selected' : ''; ?>>Alta</option>
                                    <option value="urgent" <?php echo $taskView['priority'] === 'urgent' ? 'selected' : ''; ?>>Urgente</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="edit_due_date" class="form-label">Fecha Límite</label>
                                <input type="datetime-local" class="form-control" id="edit_due_date" name="due_date"
                                       value="<?php echo $taskView['due_date'] ? date('Y-m-d\TH:i', strtotime($taskView['due_date'])) : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para cambiar estado -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cambiar Estado de Tarea</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="do" value="update_status">
                    <input type="hidden" name="task_id" value="<?php echo $taskView['id']; ?>">

                    <div class="mb-3">
                        <label for="status_select" class="form-label">Nuevo Estado</label>
                        <select name="status" id="status_select" class="form-select" required>
                            <option value="pending" <?php echo $taskView['status'] === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="in_progress" <?php echo $taskView['status'] === 'in_progress' ? 'selected' : ''; ?>>En Progreso</option>
                            <option value="completed" <?php echo $taskView['status'] === 'completed' ? 'selected' : ''; ?>>Completada</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Actualizar Estado</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para eliminar tarea -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Eliminar Tarea</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="do" value="delete">
                    <input type="hidden" name="task_id" value="<?php echo $taskView['id']; ?>">

                    <p>¿Estás seguro de que quieres eliminar la tarea "<strong><?php echo html($taskView['title']); ?></strong>"? Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar Tarea</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function changeStatus(taskId, status) {
    if (confirm('¿Estás seguro de cambiar el estado de esta tarea?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="do" value="update_status">
            <input type="hidden" name="status" value="${status}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteTask(taskId) {
    if (confirm('¿Estás seguro de eliminar esta tarea? Esta acción no se puede deshacer.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="do" value="delete">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Auto-resize textarea
document.getElementById('edit_description')?.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = this.scrollHeight + 'px';
});
</script>

<style>
.task-description {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    border-left: 4px solid #007bff;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.card-header .btn-group .btn {
    border-radius: 0.375rem !important;
}

.card-header .btn-group .btn:not(:last-child) {
    border-top-right-radius: 0 !important;
    border-bottom-right-radius: 0 !important;
}

.card-header .btn-group .btn:not(:first-child) {
    border-top-left-radius: 0 !important;
    border-bottom-left-radius: 0 !important;
}
</style>