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
    'high' => 'info',
    'urgent' => 'danger'
];

$status_colors = [
    'pending' => 'secondary',
    'in_progress' => 'primary',
    'completed' => 'success',
    'cancelled' => 'secondary'
];
?>

<style>
.page-header {
    background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 55%, #0ea5e9 100%);
    color: #fff;
    border-radius: 10px;
    padding: 18px 20px;
    margin-bottom: 18px;
    box-shadow: 0 6px 18px rgba(2, 6, 23, 0.12);
}
.page-header h1 { margin: 0; font-size: 1.6rem; font-weight: 800; }
.page-header p { margin: 6px 0 0 0; opacity: 0.92; }
.page-header .meta { margin-top: 10px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    font-weight: 800;
    font-size: 0.82rem;
    line-height: 1;
    background: rgba(255,255,255,0.14);
    border: 1px solid rgba(255,255,255,0.22);
}
.chip-danger { background: rgba(239, 68, 68, 0.22); border-color: rgba(239, 68, 68, 0.35); }
.card { border: 1px solid rgba(2, 6, 23, 0.08); border-radius: 10px; }
.card-header { background: #f8fafc; border-bottom: 1px solid rgba(2, 6, 23, 0.06); font-weight: 700; }
.task-side .btn { font-weight: 700; }
.detail-accent {
    position: relative;
}
.detail-accent:before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    border-top-left-radius: 10px;
    border-bottom-left-radius: 10px;
    background: #2563eb;
}
.detail-accent.status-pending:before { background: #64748b; }
.detail-accent.status-in_progress:before { background: #2563eb; }
.detail-accent.status-completed:before { background: #16a34a; }
.detail-accent.status-cancelled:before { background: #94a3b8; }

.task-center {
    max-width: 980px;
    margin: 0 auto;
}
.details-grid strong { color: #0f172a; }
.details-grid .col-sm-3 { color: #475569; }
</style>

<?php
$due_ts = $taskView['due_date'] ? strtotime($taskView['due_date']) : null;
$is_overdue_header = $due_ts && $due_ts < time() && $taskView['status'] !== 'completed' && $taskView['status'] !== 'cancelled';
?>

<div class="page-header">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
        <div>
            <h1>Tarea #<?php echo $taskView['id']; ?></h1>
            <p><?php echo html($taskView['title']); ?></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="tasks.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
            <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#editModal"><i class="bi bi-pencil"></i> Editar</button>
            <div class="dropdown">
                <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-list"></i> Más
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" onclick="changeStatus(<?php echo $taskView['id']; ?>, 'pending')">Marcar Pendiente</a></li>
                    <li><a class="dropdown-item" href="#" onclick="changeStatus(<?php echo $taskView['id']; ?>, 'in_progress')">Marcar En Progreso</a></li>
                    <li><a class="dropdown-item" href="#" onclick="changeStatus(<?php echo $taskView['id']; ?>, 'completed')">Marcar Completada</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteTask(<?php echo $taskView['id']; ?>)">Eliminar</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="meta">
        <span class="chip"><i class="bi bi-activity"></i> <?php echo $status_labels[$taskView['status']]; ?></span>
        <span class="chip"><i class="bi bi-flag"></i> <?php echo $priority_labels[$taskView['priority']]; ?></span>
        <?php if ($is_overdue_header): ?>
            <span class="chip chip-danger"><i class="bi bi-exclamation-triangle"></i> Vencida</span>
        <?php endif; ?>
    </div>
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

<div class="task-center">
    <div class="row justify-content-center">
        <!-- Información de la tarea -->
        <div class="col-12">
            <div class="card detail-accent status-<?php echo html($taskView['status']); ?>">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Detalles de la Tarea</h5>
                </div>
                <div class="card-body details-grid" style="padding: 18px 20px;">
                    <div class="row mb-3">
                        <div class="col-sm-3"><strong>Estado:</strong></div>
                        <div class="col-sm-9">
                            <span class="badge bg-<?php echo $status_colors[$taskView['status']]; ?> fs-6">
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
                    <input type="hidden" name="do" value="update">
                    <input type="hidden" name="task_id" value="<?php echo $taskView['id']; ?>">

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

                            <div class="mb-3">
                                <label for="edit_status" class="form-label">Estado</label>
                                <select class="form-select" id="edit_status" name="status">
                                    <option value="pending" <?php echo $taskView['status'] === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="in_progress" <?php echo $taskView['status'] === 'in_progress' ? 'selected' : ''; ?>>En Progreso</option>
                                    <option value="completed" <?php echo $taskView['status'] === 'completed' ? 'selected' : ''; ?>>Completada</option>
                                    <option value="cancelled" <?php echo $taskView['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelada</option>
                                </select>
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

<!-- Modal confirmación cambiar estado -->
<div class="modal fade" id="statusConfirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar acción</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="do" value="update_status">
                    <input type="hidden" name="task_id" value="<?php echo $taskView['id']; ?>">
                    <input type="hidden" name="status" id="confirm_status_value" value="">
                    <p class="mb-0">¿Deseas cambiar el estado de esta tarea a <strong id="confirm_status_label"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Confirmar</button>
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
    const labelMap = {
        pending: 'Pendiente',
        in_progress: 'En Progreso',
        completed: 'Completada'
    };
    const statusValueEl = document.getElementById('confirm_status_value');
    const statusLabelEl = document.getElementById('confirm_status_label');
    if (statusValueEl) statusValueEl.value = status;
    if (statusLabelEl) statusLabelEl.textContent = labelMap[status] || status;

    const modalEl = document.getElementById('statusConfirmModal');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

function deleteTask(taskId) {
    const modalEl = document.getElementById('deleteModal');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

// Auto-resize textarea
document.getElementById('edit_description')?.addEventListener('input', function() {
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