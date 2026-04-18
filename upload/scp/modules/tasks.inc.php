<?php
// tasks.inc.php - Lista de tareas
?>

<style>
/* ── tasks.inc.php – Vista de tarjetas móvil ── */
.task-card-list { display: none; padding: 12px; flex-direction: column; gap: 12px; }

.task-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.task-card-accent { height: 4px; }
.task-card-accent.prio-low      { background: linear-gradient(90deg, #94a3b8, #cbd5e1); }
.task-card-accent.prio-normal   { background: linear-gradient(90deg, #2563eb, #3b82f6); }
.task-card-accent.prio-high     { background: linear-gradient(90deg, #f59e0b, #fb923c); }
.task-card-accent.prio-urgent   { background: linear-gradient(90deg, #ef4444, #dc2626); }

.task-card-body { padding: 14px 16px 12px; }

.task-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
    gap: 8px;
}
.task-card-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
    flex: 1;
    line-height: 1.3;
    text-decoration: none;
}
.task-card-title:hover { color: #2563eb; }

.task-card-rows { display: flex; flex-direction: column; gap: 7px; margin-bottom: 14px; }
.task-card-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.83rem;
    color: #334155;
}
.task-card-row i { color: #64748b; width: 16px; text-align: center; flex-shrink: 0; }
.task-card-row .tc-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #94a3b8;
    font-weight: 700;
    min-width: 68px;
}
.task-card-row .tc-val { font-weight: 600; color: #0f172a; flex: 1; }
.task-card-row .tc-val.overdue { color: #ef4444; font-weight: 700; }

.task-card-footer {
    border-top: 1px solid #f1f5f9;
    padding-top: 10px;
    display: flex;
    gap: 8px;
}
.task-card-footer .btn { flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; }

.tc-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 20px;
    white-space: nowrap;
}
.tc-status-badge.s-pending     { background: #f1f5f9; color: #475569; }
.tc-status-badge.s-in_progress { background: #dbeafe; color: #1e40af; }
.tc-status-badge.s-completed   { background: #dcfce7; color: #166534; }
.tc-status-badge.s-cancelled   { background: #f1f5f9; color: #94a3b8; text-decoration: line-through; }

@media (max-width: 640px) {
    .task-desktop-table { display: none !important; }
    .task-card-list { display: flex; }

    .tasks-stats-row {
        display: grid !important;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .tasks-stats-row > div { padding: 0 !important; }

    .tasks-filter-wrap .row { flex-direction: column; gap: 10px; }
    .tasks-filter-wrap .col-md-6 { width: 100% !important; }
    .tasks-filter-wrap .btn-group { flex-wrap: wrap; gap: 4px; }
    .tasks-filter-wrap .btn-group a { flex: 1 1 auto; text-align: center; font-size: 0.8rem; padding: 5px 8px; }
    .tasks-filter-wrap .text-end { text-align: left !important; }
}
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
<div class="row mb-4 tasks-stats-row">
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
<div class="card mb-4 tasks-filter-wrap">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="btn-group" role="group">
                    <a href="tasks.php<?php echo $assigned_filter !== '' ? ('?assigned=' . urlencode($assigned_filter)) : ''; ?>" class="btn btn-outline-primary <?php echo !$status_filter ? 'active' : ''; ?>">Todas</a>
                    <a href="tasks.php?<?php echo http_build_query(array_filter(['status' => 'pending', 'assigned' => $assigned_filter])); ?>" class="btn btn-outline-secondary <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">Pendientes</a>
                    <a href="tasks.php?<?php echo http_build_query(array_filter(['status' => 'in_progress', 'assigned' => $assigned_filter])); ?>" class="btn btn-outline-primary <?php echo $status_filter === 'in_progress' ? 'active' : ''; ?>">En Progreso</a>
                    <a href="tasks.php?<?php echo http_build_query(array_filter(['status' => 'completed', 'assigned' => $assigned_filter])); ?>" class="btn btn-outline-success <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">Completadas</a>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <div class="btn-group" role="group">
                    <a href="tasks.php?<?php echo http_build_query(array_filter(['assigned' => 'me', 'status' => $status_filter])); ?>" class="btn btn-outline-secondary <?php echo $assigned_filter === 'me' ? 'active' : ''; ?>">Mis Tareas</a>
                    <a href="tasks.php?<?php echo http_build_query(array_filter(['assigned' => 'unassigned', 'status' => $status_filter])); ?>" class="btn btn-outline-secondary <?php echo $assigned_filter === 'unassigned' ? 'active' : ''; ?>">Sin Asignar</a>
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

            <!-- === DESKTOP: Tabla === -->
            <div class="table-responsive task-desktop-table">
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
                            $rowStatus   = $t['status'] ?? 'pending';
                            $rowPriority = $t['priority'] ?? 'normal';
                            $rowClass    = 'task-row status-' . $rowStatus;
                            $status_labels = ['pending' => 'Pendiente', 'in_progress' => 'En Progreso', 'completed' => 'Completada', 'cancelled' => 'Cancelada'];
                            $priority_labels = ['low' => 'Baja', 'normal' => 'Normal', 'high' => 'Alta', 'urgent' => 'Urgente'];
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
                                    <span class="chip chip-status">
                                        <i class="bi bi-activity"></i>
                                        <?php echo $status_labels[$rowStatus]; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="chip chip-priority">
                                        <span class="prio-dot prio-<?php echo html($rowPriority); ?>"></span>
                                        <?php echo $priority_labels[$rowPriority]; ?>
                                    </span>
                                </td>
                                <td><?php echo html($t['assigned_name'] ?: 'Sin asignar'); ?></td>
                                <td><?php echo html($t['created_name']); ?></td>
                                <td>
                                    <?php if ($t['due_date']): ?>
                                        <?php
                                        $due_ts = strtotime($t['due_date']);
                                        $is_ov  = $due_ts < time() && $rowStatus !== 'completed';
                                        ?>
                                        <span class="<?php echo $is_ov ? 'text-danger fw-bold' : ''; ?>">
                                            <?php echo date('d/m/Y H:i', $due_ts); ?>
                                        </span>
                                        <?php if ($is_ov): ?>
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

            <!-- === MÓVIL: Tarjetas === -->
            <div class="task-card-list">
                <?php foreach ($tasks as $t): ?>
                    <?php
                    $rowStatus   = $t['status'] ?? 'pending';
                    $rowPriority = $t['priority'] ?? 'normal';
                    $due_date    = !empty($t['due_date']) ? strtotime($t['due_date']) : null;
                    $is_overdue  = $due_date && $due_date < time() && $rowStatus !== 'completed';
                    $status_labels   = ['pending' => 'Pendiente', 'in_progress' => 'En Progreso', 'completed' => 'Completada', 'cancelled' => 'Cancelada'];
                    $priority_labels = ['low' => 'Baja', 'normal' => 'Normal', 'high' => 'Alta', 'urgent' => 'Urgente'];
                    $priority_icons  = ['low' => 'bi-arrow-down', 'normal' => 'bi-dash', 'high' => 'bi-arrow-up', 'urgent' => 'bi-lightning-charge-fill'];
                    ?>
                    <div class="task-card">
                        <div class="task-card-accent prio-<?php echo html($rowPriority); ?>"></div>
                        <div class="task-card-body">
                            <div class="task-card-top">
                                <a href="tasks.php?id=<?php echo $t['id']; ?>" class="task-card-title">
                                    <?php echo html($t['title']); ?>
                                </a>
                                <span class="tc-status-badge s-<?php echo html($rowStatus); ?>">
                                    <?php if ($rowStatus === 'completed'): ?><i class="bi bi-check-circle"></i>
                                    <?php elseif ($rowStatus === 'in_progress'): ?><i class="bi bi-play-circle"></i>
                                    <?php elseif ($rowStatus === 'cancelled'): ?><i class="bi bi-x-circle"></i>
                                    <?php else: ?><i class="bi bi-clock"></i>
                                    <?php endif; ?>
                                    <?php echo $status_labels[$rowStatus]; ?>
                                </span>
                            </div>

                            <div class="task-card-rows">
                                <div class="task-card-row">
                                    <i class="bi <?php echo $priority_icons[$rowPriority]; ?>"></i>
                                    <span class="tc-label">Prioridad</span>
                                    <span class="tc-val"><?php echo $priority_labels[$rowPriority]; ?></span>
                                </div>
                                <div class="task-card-row">
                                    <i class="bi bi-person"></i>
                                    <span class="tc-label">Asignado</span>
                                    <span class="tc-val"><?php echo html($t['assigned_name'] ?: 'Sin asignar'); ?></span>
                                </div>
                                <?php if (!empty($tasksHasDept) && !empty($t['dept_name'])): ?>
                                <div class="task-card-row">
                                    <i class="bi bi-building"></i>
                                    <span class="tc-label">Dpto</span>
                                    <span class="tc-val">
                                        <span class="badge bg-secondary fw-normal"><?php echo html($t['dept_name']); ?></span>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <div class="task-card-row">
                                    <i class="bi bi-calendar-event"></i>
                                    <span class="tc-label">Límite</span>
                                    <span class="tc-val <?php echo $is_overdue ? 'overdue' : ''; ?>">
                                        <?php if ($due_date): ?>
                                            <?php echo date('d/m/Y H:i', $due_date); ?>
                                            <?php if ($is_overdue): ?>
                                                <span class="badge bg-danger ms-1" style="font-size:0.65rem;">Vencida</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Sin límite</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="task-card-footer">
                                <a href="tasks.php?id=<?php echo $t['id']; ?>"
                                   class="btn btn-sm btn-primary"
                                   style="background:linear-gradient(135deg,#2563eb,#1d4ed8); border:none;">
                                    <i class="bi bi-eye"></i> Ver Tarea
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php
            $basePaging = array_filter([
                'status'   => $status_filter !== '' ? $status_filter : null,
                'assigned' => $assigned_filter !== '' ? $assigned_filter : null,
            ], function ($v) { return $v !== null && $v !== ''; });
            $prevUrl = '';
            $nextUrl = '';
            if (($pageNum ?? 1) > 1) {
                $prevParams = $basePaging;
                $prevParams['p'] = $pageNum - 1;
                $prevUrl = 'tasks.php?' . http_build_query($prevParams);
            }
            if (($pageNum ?? 1) < ($totalPages ?? 1)) {
                $nextParams = $basePaging;
                $nextParams['p'] = $pageNum + 1;
                $nextUrl = 'tasks.php?' . http_build_query($nextParams);
            }
            $showStart = ((int)($totalRows ?? 0) > 0) ? (((int)($offset ?? 0)) + 1) : 0;
            $showEnd   = min(((int)($offset ?? 0) + (int)($perPage ?? 10)), (int)($totalRows ?? 0));
            ?>
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3">
                <div class="text-muted" style="font-size:.9rem;">
                    Página <?php echo (int)($pageNum ?? 1); ?> de <?php echo (int)($totalPages ?? 1); ?>
                    <?php if (isset($totalRows)): ?>
                        · Mostrando <?php echo $showStart; ?>-<?php echo $showEnd; ?> de <?php echo (int)($totalRows ?? 0); ?>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($prevUrl !== ''): ?>
                        <a class="btn btn-outline-secondary btn-sm" href="<?php echo html($prevUrl); ?>"><i class="bi bi-chevron-left"></i> Anterior</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled><i class="bi bi-chevron-left"></i> Anterior</button>
                    <?php endif; ?>
                    <?php if ($nextUrl !== ''): ?>
                        <a class="btn btn-outline-secondary btn-sm" href="<?php echo html($nextUrl); ?>">Siguiente <i class="bi bi-chevron-right"></i></a>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled>Siguiente <i class="bi bi-chevron-right"></i></button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>