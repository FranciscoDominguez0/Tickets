<?php
// tasks.inc.php - Lista de tareas
?>

<style>
/* ── tasks.inc.php – Modern Professional Design ── */

/* Stats cards profesionales minimalistas */
.task-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}
.task-stat-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 18px 18px 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    transition: transform 0.15s, box-shadow 0.15s;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.task-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.06);
    border-color: #cbd5e1;
}
.task-stat-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: #e2e8f0;
}
.task-stat-card.stat-pending::after { background: #94a3b8; }
.task-stat-card.stat-progress::after { background: #2563eb; }
.task-stat-card.stat-completed::after { background: #16a34a; }
.task-stat-card.stat-cancelled::after { background: #cbd5e1; }

.task-stat-card .stat-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}
.task-stat-card .stat-top i {
    font-size: 1.1rem;
    color: #94a3b8;
}
.task-stat-card .stat-number {
    font-size: 1.7rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1;
    margin-bottom: 6px;
}
.task-stat-card .stat-label {
    font-size: 0.78rem;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Filtro profesional tipo dropdown */
.tasks-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    justify-content: space-between;
}
.tasks-toolbar .filter-dd .btn {
    border-radius: 10px;
    border: 1px solid rgba(2, 6, 23, 0.10);
    background: #fff;
    font-weight: 700;
    color: #0f172a;
    padding: 8px 16px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.tasks-toolbar .filter-dd .btn:hover {
    background: #f8fafc;
}
.tasks-toolbar .filter-dd .dropdown-menu {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    padding: 8px;
    min-width: 220px;
}
.tasks-toolbar .filter-dd .dropdown-item {
    border-radius: 8px;
    padding: 8px 14px;
    font-weight: 600;
    font-size: 0.9rem;
    color: #334155;
    display: flex;
    align-items: center;
    gap: 8px;
}
.tasks-toolbar .filter-dd .dropdown-item:hover {
    background: #eff6ff;
    color: #2563eb;
}
.tasks-toolbar .filter-dd .dropdown-item.active {
    background: #2563eb;
    color: #fff;
}
.tasks-toolbar .filter-dd .dropdown-divider {
    margin: 6px 8px;
    border-color: #e2e8f0;
}
.tasks-toolbar .filter-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #eff6ff;
    color: #1e40af;
    border: 1px solid #bfdbfe;
    border-radius: 20px;
    padding: 4px 12px;
    font-size: 0.8rem;
    font-weight: 700;
}

/* Chips */
.chip-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 700;
}
.chip-priority {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 700;
}
.prio-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    display: inline-block;
}
.prio-dot.prio-low { background: #94a3b8; }
.prio-dot.prio-normal { background: #2563eb; }
.prio-dot.prio-high { background: #f59e0b; }
.prio-dot.prio-urgent { background: #ef4444; }

/* Status backgrounds */
.status-pending .chip-status { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.status-in_progress .chip-status { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
.status-completed .chip-status { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.status-cancelled .chip-status { background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0; text-decoration: line-through; }

/* Priority backgrounds */
.prio-low { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
.prio-normal { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
.prio-high { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.prio-urgent { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

/* Mobile cards */
@media (max-width: 767px) {
    .task-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .tasks-toolbar { flex-direction: column; align-items: stretch; }
    .tasks-toolbar .filter-group { width: 100%; }
    .tasks-toolbar .filter-group .btn { flex: 1; text-align: center; }

    #tasksTable thead { display: none; }
    #tasksTable tbody tr {
        display: block;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 14px 16px;
        margin-bottom: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    #tasksTable tbody td {
        display: block;
        border: none;
        padding: 0 !important;
        width: 100% !important;
    }
    #tasksTable tbody td:last-child {
        text-align: right;
        margin-top: 10px;
    }
    .task-mobile-meta {
        display: flex !important;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
    }
    .task-card-accent {
        height: 4px;
        border-radius: 14px 14px 0 0;
        margin: -14px -16px 10px;
    }
    .task-card-accent.prio-low { background: linear-gradient(90deg, #94a3b8, #cbd5e1); }
    .task-card-accent.prio-normal { background: linear-gradient(90deg, #2563eb, #3b82f6); }
    .task-card-accent.prio-high { background: linear-gradient(90deg, #f59e0b, #fb923c); }
    .task-card-accent.prio-urgent { background: linear-gradient(90deg, #ef4444, #dc2626); }
}
@media (min-width: 768px) {
    .task-mobile-meta { display: none !important; }
    .task-card-accent { display: none !important; }
}
</style>

<div class="tickets-shell">
    <div class="tickets-header" style="background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 55%, #0ea5e9 100%); border-radius: 14px; padding: 28px 22px; margin-bottom: 20px; box-shadow: 0 8px 24px rgba(2, 6, 23, 0.15);">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h1 style="margin:0; font-size:1.5rem; font-weight:800; letter-spacing:-0.01em; color:#fff;">Tareas</h1>
                <div style="margin-top:6px; opacity:0.92; font-size:0.95rem; font-weight:500; color:#fff;">Gestiona las tareas asignadas y pendientes</div>
            </div>
            <a href="tasks.php?a=create" style="display:inline-flex; align-items:center; gap:8px; background: rgba(255,255,255,0.18); border:1px solid rgba(255,255,255,0.30); color:#fff; padding:10px 18px; border-radius:12px; text-decoration:none; font-weight:700; font-size:0.95rem; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.28)';" onmouseout="this.style.background='rgba(255,255,255,0.18)';">
                <i class="bi bi-plus-lg"></i> Nueva Tarea
            </a>
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

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="alert alert-success alert-dismissible fade show">
            Tarea eliminada exitosamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Estadísticas profesionales -->
    <div class="task-stats-grid">
        <div class="task-stat-card stat-pending">
            <div class="stat-top"><i class="bi bi-clock"></i></div>
            <div class="stat-number"><?php echo (int)($stats['pending'] ?? 0); ?></div>
            <div class="stat-label">Pendientes</div>
        </div>
        <div class="task-stat-card stat-progress">
            <div class="stat-top"><i class="bi bi-play-circle"></i></div>
            <div class="stat-number"><?php echo (int)($stats['in_progress'] ?? 0); ?></div>
            <div class="stat-label">En Progreso</div>
        </div>
        <div class="task-stat-card stat-completed">
            <div class="stat-top"><i class="bi bi-check-circle"></i></div>
            <div class="stat-number"><?php echo (int)($stats['completed'] ?? 0); ?></div>
            <div class="stat-label">Completadas</div>
        </div>
        <div class="task-stat-card stat-cancelled">
            <div class="stat-top"><i class="bi bi-x-circle"></i></div>
            <div class="stat-number"><?php echo (int)($stats['cancelled'] ?? 0); ?></div>
            <div class="stat-label">Canceladas</div>
        </div>
    </div>

    <!-- Toolbar: filtros profesionales con dropdown -->
    <div class="tickets-panel" style="margin-bottom: 16px;">
        <div class="tasks-toolbar">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="dropdown filter-dd">
                    <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-funnel"></i>
                        <?php
                        $statusLabels = ['' => 'Todos los estados', 'pending' => 'Pendientes', 'in_progress' => 'En Progreso', 'completed' => 'Completadas'];
                        echo $statusLabels[$status_filter] ?? 'Estado';
                        ?>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?php echo !$status_filter ? 'active' : ''; ?>" href="tasks.php<?php echo $assigned_filter !== '' ? ('?assigned=' . urlencode($assigned_filter)) : ''; ?>"><i class="bi bi-list"></i> Todos los estados</a></li>
                        <li><a class="dropdown-item <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" href="tasks.php?<?php echo http_build_query(array_filter(['status' => 'pending', 'assigned' => $assigned_filter])); ?>"><i class="bi bi-clock"></i> Pendientes</a></li>
                        <li><a class="dropdown-item <?php echo $status_filter === 'in_progress' ? 'active' : ''; ?>" href="tasks.php?<?php echo http_build_query(array_filter(['status' => 'in_progress', 'assigned' => $assigned_filter])); ?>"><i class="bi bi-play-circle"></i> En Progreso</a></li>
                        <li><a class="dropdown-item <?php echo $status_filter === 'completed' ? 'active' : ''; ?>" href="tasks.php?<?php echo http_build_query(array_filter(['status' => 'completed', 'assigned' => $assigned_filter])); ?>"><i class="bi bi-check-circle"></i> Completadas</a></li>
                    </ul>
                </div>

                <div class="dropdown filter-dd">
                    <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person"></i>
                        <?php
                        $assignLabels = ['' => 'Todos los agentes', 'me' => 'Mis Tareas', 'unassigned' => 'Sin Asignar'];
                        echo $assignLabels[$assigned_filter] ?? 'Asignación';
                        ?>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?php echo !$assigned_filter ? 'active' : ''; ?>" href="tasks.php<?php echo $status_filter !== '' ? ('?status=' . urlencode($status_filter)) : ''; ?>"><i class="bi bi-people"></i> Todos los agentes</a></li>
                        <li><a class="dropdown-item <?php echo $assigned_filter === 'me' ? 'active' : ''; ?>" href="tasks.php?<?php echo http_build_query(array_filter(['assigned' => 'me', 'status' => $status_filter])); ?>"><i class="bi bi-person-fill"></i> Mis Tareas</a></li>
                        <li><a class="dropdown-item <?php echo $assigned_filter === 'unassigned' ? 'active' : ''; ?>" href="tasks.php?<?php echo http_build_query(array_filter(['assigned' => 'unassigned', 'status' => $status_filter])); ?>"><i class="bi bi-person-x"></i> Sin Asignar</a></li>
                    </ul>
                </div>

                <?php if ($status_filter !== '' || $assigned_filter !== ''): ?>
                    <a href="tasks.php" class="filter-badge" title="Limpiar filtros"><i class="bi bi-funnel-fill"></i> Filtrado · <i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Lista de tareas -->
    <div class="tickets-table-wrap">
        <table class="table table-hover tickets-table mb-0" id="tasksTable">
            <thead class="table-light" style="border-bottom: 2px solid #e2e8f0; background-color: #f8fafc;">
                <tr>
                    <th style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; padding-left: 20px;">Tarea</th>
                    <?php if (!empty($tasksHasDept)): ?>
                        <th class="d-none d-lg-table-cell" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Departamento</th>
                    <?php endif; ?>
                    <th style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Estado</th>
                    <th class="d-none d-md-table-cell" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Prioridad</th>
                    <th class="d-none d-lg-table-cell" style="font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Fecha Límite</th>
                    <th style="width: 80px; text-align: right; font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; padding-right: 20px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tasks)): ?>
                    <tr>
                        <td colspan="<?php echo !empty($tasksHasDept) ? '6' : '5'; ?>">
                            <div class="empty-state">
                                <i class="bi bi-check2-square" style="font-size: 2rem; opacity: 0.6;"></i>
                                <div class="mt-2">No hay tareas con los filtros aplicados.</div>
                                <a href="tasks.php?a=create" class="btn btn-primary btn-sm mt-3">Crear Primera Tarea</a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tasks as $t): ?>
                        <?php
                        $rowStatus   = $t['status'] ?? 'pending';
                        $rowPriority = $t['priority'] ?? 'normal';
                        $rowClass    = 'task-row status-' . $rowStatus;
                        $status_labels = ['pending' => 'Pendiente', 'in_progress' => 'En Progreso', 'completed' => 'Completada', 'cancelled' => 'Cancelada'];
                        $priority_labels = ['low' => 'Baja', 'normal' => 'Normal', 'high' => 'Alta', 'urgent' => 'Urgente'];
                        $priority_icons  = ['low' => 'bi-arrow-down', 'normal' => 'bi-dash', 'high' => 'bi-arrow-up', 'urgent' => 'bi-lightning-charge-fill'];

                        $due_date    = !empty($t['due_date']) ? strtotime($t['due_date']) : null;
                        $is_overdue  = $due_date && $due_date < time() && $rowStatus !== 'completed';
                        ?>
                        <tr class="<?php echo html($rowClass); ?>" style="background: #fff; cursor: pointer; transition: all 0.2s;" onclick="if(!event.target.closest('a') && !event.target.closest('button')) window.location='tasks.php?id=<?php echo (int)$t['id']; ?>';">
                            <td style="vertical-align: middle; padding: 16px 12px 16px 20px;">
                                <!-- Mobile accent bar -->
                                <div class="task-card-accent prio-<?php echo html($rowPriority); ?>"></div>

                                <div style="display: flex; align-items: baseline; gap: 8px; margin-bottom: 6px;">
                                    <a href="tasks.php?id=<?php echo (int)$t['id']; ?>" class="ticket-title" style="font-weight: 800; font-size: 1.05rem; color: #2563eb; text-decoration: none;" onclick="event.stopPropagation();">
                                        <i class="bi bi-hash" style="opacity: 0.5;"></i><?php echo (int)$t['id']; ?>
                                    </a>
                                    <?php if ($is_overdue): ?>
                                        <span class="badge" style="background:#ef4444; color: #fff; font-size: 0.65rem; padding: 4px 6px; letter-spacing: 0.05em; text-transform: uppercase; border-radius: 6px;">Vencida</span>
                                    <?php endif; ?>
                                </div>
                                <div class="ticket-subject" style="font-weight: 600; color: #1e293b; font-size: 0.95rem; margin-bottom: 4px; line-height: 1.4;">
                                    <?php echo html($t['title']); ?>
                                </div>
                                <div style="font-size: 0.8rem; color: #64748b; display: flex; align-items: center; gap: 5px;">
                                    <span><i class="bi bi-person-badge" style="color:#94a3b8;"></i> Asignado a: <strong style="color: #475569;"><?php echo html($t['assigned_name'] ?: 'Sin asignar'); ?></strong></span>
                                </div>

                                <!-- Mobile meta -->
                                <div class="task-mobile-meta">
                                    <?php if (!empty($tasksHasDept) && !empty($t['dept_name'])): ?>
                                        <span class="chip" style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; font-size:0.7rem; border-radius:6px; padding:3px 8px; font-weight:700;">
                                            <i class="bi bi-building"></i> <?php echo html($t['dept_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="chip chip-status <?php echo html($rowStatus); ?>" style="font-size:0.7rem; border-radius:6px; padding:3px 8px; font-weight:700;">
                                        <?php echo html($status_labels[$rowStatus]); ?>
                                    </span>
                                    <span class="chip <?php echo html('prio-' . $rowPriority); ?>" style="font-size:0.7rem; border-radius:6px; padding:3px 8px; font-weight:700;">
                                        <i class="bi <?php echo html($priority_icons[$rowPriority]); ?>"></i> <?php echo html($priority_labels[$rowPriority]); ?>
                                    </span>
                                    <span class="chip" style="background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; font-size:0.7rem; border-radius:6px; padding:3px 8px; font-weight:700;">
                                        <i class="bi bi-person-badge"></i> <?php echo html($t['assigned_name'] ?: 'Sin asignar'); ?>
                                    </span>
                                    <?php if ($due_date): ?>
                                        <span class="chip" style="background: <?php echo $is_overdue ? '#fef2f2' : '#fffbeb'; ?>; color: <?php echo $is_overdue ? '#991b1b' : '#92400e'; ?>; border: 1px solid <?php echo $is_overdue ? '#fecaca' : '#fde68a'; ?>; font-size:0.7rem; border-radius:6px; padding:3px 8px; font-weight:700;">
                                            <i class="bi bi-calendar-event"></i> <?php echo date('d/m/Y', $due_date); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php if (!empty($tasksHasDept)): ?>
                                <td class="d-none d-lg-table-cell" style="vertical-align: middle;">
                                    <?php if (!empty($t['dept_name'])): ?>
                                        <span class="chip" style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 6px 14px; font-weight: 700; font-size: 0.8rem; border-radius: 8px;">
                                            <i class="bi bi-building" style="margin-right: 4px;"></i><?php echo html($t['dept_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size: 0.85rem;">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td style="vertical-align: middle;">
                                <div class="d-none d-md-flex flex-column gap-2 align-items-start">
                                    <span class="chip chip-status <?php echo html($rowStatus); ?>">
                                        <i class="bi bi-activity"></i> <?php echo html($status_labels[$rowStatus]); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell" style="vertical-align: middle;">
                                <span class="chip chip-priority <?php echo html('prio-' . $rowPriority); ?>">
                                    <span class="prio-dot prio-<?php echo html($rowPriority); ?>"></span>
                                    <?php echo html($priority_labels[$rowPriority]); ?>
                                </span>
                            </td>
                            <td class="d-none d-lg-table-cell" style="vertical-align: middle;">
                                <?php if ($due_date): ?>
                                    <div style="display:flex; align-items:center; gap:6px; color: <?php echo $is_overdue ? '#ef4444' : '#64748b'; ?>; font-size: 0.85rem; font-weight: 600;">
                                        <i class="bi bi-calendar-event" style="color:<?php echo $is_overdue ? '#ef4444' : '#94a3b8'; ?>"></i>
                                        <?php echo date('d/m/Y H:i', $due_date); ?>
                                        <?php if ($is_overdue): ?>
                                            <span class="badge bg-danger" style="font-size:0.65rem;">Vencida</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 0.85rem;">Sin límite</span>
                                <?php endif; ?>
                            </td>
                            <td style="vertical-align: middle; text-align: right; padding-right: 20px;">
                                <button type="button" class="btn btn-sm" style="background: transparent; color: #94a3b8; border: none; font-size: 1.2rem; transition: all 0.2s;" onmouseover="this.style.color='#2563eb'" onmouseout="this.style.color='#94a3b8'">
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
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
    <?php if ($totalPages > 1): ?>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3">
        <div class="text-muted" style="font-size:0.9rem;">
            Mostrando <?php echo $showStart; ?>-<?php echo $showEnd; ?> de <?php echo (int)($totalRows ?? 0); ?>
            · Página <?php echo (int)($pageNum ?? 1); ?> de <?php echo (int)($totalPages ?? 1); ?>
        </div>
        <div class="d-flex gap-2">
            <?php if ($prevUrl !== ''): ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo html($prevUrl); ?>"><i class="bi bi-chevron-left"></i> Anterior</a>
            <?php else: ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" disabled><i class="bi bi-chevron-left"></i> Anterior</button>
            <?php endif; ?>

            <?php
            $range = 2;
            $start = max(1, $pageNum - $range);
            $end   = min($totalPages, $pageNum + $range);
            ?>
            <div class="d-none d-sm-flex gap-1">
                <?php if ($start > 1): ?>
                    <a href="tasks.php?<?php echo http_build_query(array_merge($basePaging, ['p' => 1])); ?>" class="btn btn-sm btn-outline-secondary">1</a>
                    <?php if ($start > 2): ?><span class="text-muted small px-1" style="align-self:center;">&hellip;</span><?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="tasks.php?<?php echo http_build_query(array_merge($basePaging, ['p' => $i])); ?>"
                       class="btn btn-sm <?php echo $i === $pageNum ? 'btn-primary' : 'btn-outline-secondary'; ?>"
                       <?php echo $i === $pageNum ? 'style="background:linear-gradient(135deg,#2563eb,#1d4ed8);border:none;"' : ''; ?>>
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?><span class="text-muted small px-1" style="align-self:center;">&hellip;</span><?php endif; ?>
                    <a href="tasks.php?<?php echo http_build_query(array_merge($basePaging, ['p' => $totalPages])); ?>" class="btn btn-sm btn-outline-secondary"><?php echo $totalPages; ?></a>
                <?php endif; ?>
            </div>

            <?php if ($nextUrl !== ''): ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo html($nextUrl); ?>">Siguiente <i class="bi bi-chevron-right"></i></a>
            <?php else: ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" disabled>Siguiente <i class="bi bi-chevron-right"></i></button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
