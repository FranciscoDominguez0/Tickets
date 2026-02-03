<?php
// tasks.inc.php - Lista de tareas
?>

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
                <h5 class="card-title text-warning"><?php echo $stats['pending'] ?? 0; ?></h5>
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
                    <a href="tasks.php?status=pending" class="btn btn-outline-warning <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">Pendientes</a>
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
                            <tr>
                                <td><?php echo $t['id']; ?></td>
                                <td>
                                    <a href="tasks.php?id=<?php echo $t['id']; ?>" class="text-decoration-none">
                                        <?php echo html($t['title']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php
                                    $status_labels = [
                                        'pending' => 'Pendiente',
                                        'in_progress' => 'En Progreso',
                                        'completed' => 'Completada',
                                        'cancelled' => 'Cancelada'
                                    ];
                                    $status_colors = [
                                        'pending' => 'warning',
                                        'in_progress' => 'primary',
                                        'completed' => 'success',
                                        'cancelled' => 'secondary'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $status_colors[$t['status']]; ?>">
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
                                        'high' => 'warning',
                                        'urgent' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $priority_colors[$t['priority']]; ?>">
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
                                            <i class="bi bi-exclamation-triangle text-danger" title="Vencida"></i>
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