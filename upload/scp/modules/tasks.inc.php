<?php
// tasks.inc.php - Lista de tareas
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

.card { border: 1px solid rgba(2, 6, 23, 0.08); border-radius: 10px; }
.card-header { background: #f8fafc; border-bottom: 1px solid rgba(2, 6, 23, 0.06); font-weight: 700; }
.table thead th { background: #f8fafc; color: #475569; font-size: 0.85rem; }
.table-hover tbody tr:hover { background: #f8fafc; }

/* Chips suaves (estilo osTicket) */
.chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.82rem;
    line-height: 1;
}
.chip-status { background: rgba(37, 99, 235, 0.10); color: #1d4ed8; }
.chip-priority { background: rgba(15, 23, 42, 0.06); color: #0f172a; }
.chip-danger { background: rgba(239, 68, 68, 0.12); color: #b91c1c; }

/* Acento de fila por estado */
.task-row { transition: transform .06s ease, box-shadow .12s ease; }
.task-row:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(2, 6, 23, 0.06); }
.task-row.status-pending { box-shadow: inset 4px 0 0 rgba(100, 116, 139, 0.75); }
.task-row.status-in_progress { box-shadow: inset 4px 0 0 rgba(37, 99, 235, 0.85); }
.task-row.status-completed { box-shadow: inset 4px 0 0 rgba(22, 163, 74, 0.85); }
.task-row.status-cancelled { box-shadow: inset 4px 0 0 rgba(148, 163, 184, 0.85); }

/* Prioridad: pequeño indicador */
.prio-dot { width: 9px; height: 9px; border-radius: 999px; display: inline-block; margin-right: 6px; }
.prio-low { background: #94a3b8; }
.prio-normal { background: #2563eb; }
.prio-high { background: #0ea5e9; }
.prio-urgent { background: #ef4444; }

.btn-primary { background: #2563eb; border-color: #2563eb; }
.btn-outline-primary { color: #2563eb; border-color: rgba(37, 99, 235, 0.35); }
.btn-outline-primary:hover { background: #2563eb; border-color: #2563eb; }
</style>

<div class="page-header">
    <h1><i class="bi bi-check2-square"></i> Tareas</h1>
    <p>Gestiona las tareas asignadas y pendientes</p>
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

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <div class="alert alert-success alert-dismissible fade show">
        Tarea eliminada exitosamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-secondary"><?php echo $stats['pending'] ?? 0; ?></h5>
                <p class="card-text">Pendientes</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-primary"><?php echo $stats['in_progress'] ?? 0; ?></h5>
                <p class="card-text">En Progreso</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-success"><?php echo $stats['completed'] ?? 0; ?></h5>
                <p class="card-text">Completadas</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-secondary"><?php echo $stats['cancelled'] ?? 0; ?></h5>
                <p class="card-text">Canceladas</p>
            </div>
        </div>
    </div>
</div>

<!-- Filtros y acciones -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="btn-group" role="group">
                    <a href="tasks.php" class="btn btn-outline-primary <?php echo !$status_filter ? 'active' : ''; ?>">Todas</a>
                    <a href="tasks.php?status=pending" class="btn btn-outline-secondary <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">Pendientes</a>
                    <a href="tasks.php?status=in_progress" class="btn btn-outline-primary <?php echo $status_filter === 'in_progress' ? 'active' : ''; ?>">En Progreso</a>
                    <a href="tasks.php?status=completed" class="btn btn-outline-success <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">Completadas</a>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <div class="btn-group" role="group">
                    <a href="tasks.php?assigned=me" class="btn btn-outline-secondary <?php echo $assigned_filter === 'me' ? 'active' : ''; ?>">Mis Tareas</a>
                    <a href="tasks.php?assigned=unassigned" class="btn btn-outline-secondary <?php echo $assigned_filter === 'unassigned' ? 'active' : ''; ?>">Sin Asignar</a>
                    <a href="tasks.php?a=create" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Nueva Tarea
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lista de tareas -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Lista de Tareas</h5>
    </div>
    <div class="card-body">
        <?php if (empty($tasks)): ?>
            <div class="text-center py-5">
                <i class="bi bi-check2-square" style="font-size: 3rem; color: #ccc;"></i>
                <h5 class="mt-3">No hay tareas</h5>
                <p class="text-muted">No se encontraron tareas con los filtros aplicados.</p>
                <a href="tasks.php?a=create" class="btn btn-primary">Crear Primera Tarea</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <?php if (!empty($tasksHasDept)): ?>
                                <th>Departamento</th>
                            <?php endif; ?>
                            <th>Estado</th>
                            <th>Prioridad</th>
                            <th>Asignado a</th>
                            <th>Creado por</th>
                            <th>Fecha Límite</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $t): ?>
                            <?php
                            $rowStatus = $t['status'] ?? 'pending';
                            $rowPriority = $t['priority'] ?? 'normal';
                            $rowClass = 'task-row status-' . $rowStatus;
                            ?>
                            <tr class="<?php echo html($rowClass); ?>">
                                <td><?php echo $t['id']; ?></td>
                                <td>
                                    <a href="tasks.php?id=<?php echo $t['id']; ?>" class="text-decoration-none">
                                        <?php echo html($t['title']); ?>
                                    </a>
                                </td>
                                <?php if (!empty($tasksHasDept)): ?>
                                    <td><?php echo html($t['dept_name'] ?? ''); ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php
                                    $status_labels = [
                                        'pending' => 'Pendiente',
                                        'in_progress' => 'En Progreso',
                                        'completed' => 'Completada',
                                        'cancelled' => 'Cancelada'
                                    ];
                                    $status_colors = [
                                        'pending' => 'secondary',
                                        'in_progress' => 'primary',
                                        'completed' => 'success',
                                        'cancelled' => 'secondary'
                                    ];
                                    ?>
                                    <span class="chip chip-status">
                                        <i class="bi bi-activity"></i>
                                        <?php echo $status_labels[$t['status']]; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
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
                                    ?>
                                    <span class="chip chip-priority">
                                        <span class="prio-dot prio-<?php echo html($rowPriority); ?>"></span>
                                        <?php echo $priority_labels[$t['priority']]; ?>
                                    </span>
                                </td>
                                <td><?php echo html($t['assigned_name'] ?: 'Sin asignar'); ?></td>
                                <td><?php echo html($t['created_name']); ?></td>
                                <td>
                                    <?php if ($t['due_date']): ?>
                                        <?php
                                        $due_date = strtotime($t['due_date']);
                                        $is_overdue = $due_date < time() && $t['status'] !== 'completed';
                                        ?>
                                        <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                            <?php echo date('d/m/Y H:i', $due_date); ?>
                                        </span>
                                        <?php if ($is_overdue): ?>
                                            <span class="chip chip-danger ms-2"><i class="bi bi-exclamation-triangle"></i> Vencida</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Sin límite</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="tasks.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
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