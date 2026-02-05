<?php
if (!isset($viewUser) || !is_array($viewUser)) return;
$uid = (int) $viewUser['id'];
$statusKey = $viewUser['status'] ?? 'active';
$statusLabel = $statusLabels[$statusKey] ?? ucfirst($statusKey);
?>

<div class="user-view-wrap">
    <header class="user-view-header">
        <h1 class="user-view-title">
            <a href="users.php?id=<?php echo $uid; ?>" title="Recargar">
                <i class="bi bi-arrow-clockwise"></i>
                <?php echo html($viewUserName); ?>
            </a>
        </h1>
        <div class="user-view-actions">
            <a href="#" class="btn btn-register"><i class="bi bi-person-check"></i> Registrarse</a>
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalDeleteUser"><i class="bi bi-trash"></i> Eliminar usuario</button>
            <div class="dropdown">
                <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="bi bi-gear"></i> Más <i class="bi bi-chevron-down" style="font-size:0.7rem;"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#"><i class="bi bi-envelope"></i> Enviar restablecer contraseña</a></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-pencil"></i> Editar perfil</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Modal: cambiar estado del usuario -->
    <div class="modal fade" id="modalUserStatus" tabindex="-1" aria-labelledby="modalUserStatusLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title" id="modalUserStatusLabel"><i class="bi bi-person-gear me-2"></i>Cambiar estado de usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="post" action="users.php?id=<?php echo $uid; ?>">
                    <div class="modal-body">
                        <input type="hidden" name="do" value="update_status">
                        <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                        <div class="mb-2 text-muted small">Selecciona el estado del usuario. "Bloqueado" impide iniciar sesión y operar en el sistema.</div>
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select">
                            <option value="active" <?php echo $statusKey === 'active' ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactive" <?php echo $statusKey === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                            <option value="banned" <?php echo $statusKey === 'banned' ? 'selected' : ''; ?>>Bloqueado</option>
                        </select>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="user-view-card">
        <div class="user-view-profile">
            <div class="user-view-avatar">
                <i class="bi bi-person-fill"></i>
            </div>
            <div class="user-view-details">
                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'status_updated'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert" style="grid-column: 1 / -1;">
                        Estado de usuario actualizado correctamente.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                <?php endif; ?>
                <div class="user-view-detail">
                    <label>Nombre</label>
                    <div class="value">
                        <a href="#"><?php echo html($viewUserName); ?></a>
                        <i class="bi bi-pencil edit-icon" title="Editar nombre"></i>
                    </div>
                </div>
                <div class="user-view-detail">
                    <label>Email</label>
                    <div class="value"><?php echo html($viewUser['email']); ?></div>
                </div>
                <div class="user-view-detail">
                    <label>Organización</label>
                    <div class="value">
                        <?php if (!empty($viewUser['company'])): ?>
                            <span><?php echo html($viewUser['company']); ?></span>
                            <a href="#" class="ms-2 text-danger" data-bs-toggle="modal" data-bs-target="#removeOrgModal"><i class="bi bi-x-circle"></i> Remover</a>
                        <?php else: ?>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#assignOrgModal">Asignar organización</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="user-view-detail">
                    <label>Estado</label>
                    <div class="value">
                        <span class="user-view-status-badge <?php echo html($statusKey); ?>"><?php echo html($statusLabel); ?></span>
                        <a href="#" class="ms-2" data-bs-toggle="modal" data-bs-target="#modalUserStatus"><i class="bi bi-pencil-square"></i> Cambiar</a>
                    </div>
                </div>
                <div class="user-view-detail">
                    <label>Creado</label>
                    <div class="value"><?php echo $viewUser['created'] ? date('d/m/y H:i:s', strtotime($viewUser['created'])) : '—'; ?></div>
                </div>
                <div class="user-view-detail">
                    <label>Actualizado</label>
                    <div class="value"><?php echo $viewUser['updated'] ? date('d/m/y H:i:s', strtotime($viewUser['updated'])) : '—'; ?></div>
                </div>
            </div>
        </div>

<?php $activeTab = $_GET['t'] ?? 'tickets'; ?>
        <ul class="user-view-tabs" role="tablist">
            <li><a class="tab <?php echo $activeTab === 'tickets' ? 'active' : ''; ?>" href="users.php?id=<?php echo $uid; ?>&t=tickets"><i class="bi bi-ticket-perforated"></i> Tickets</a></li>
            <li><a class="tab <?php echo $activeTab === 'notes' ? 'active' : ''; ?>" href="users.php?id=<?php echo $uid; ?>&t=notes"><i class="bi bi-pin-angle"></i> Notas</a></li>
        </ul>

        <div class="user-view-tab-content" id="tab-tickets" style="display:<?php echo $activeTab === 'tickets' ? 'block' : 'none'; ?>">
            <?php if (empty($userTickets)): ?>
                <div class="empty-state">
                    <div class="icon"><i class="bi bi-inbox"></i></div>
                    <p class="mb-0">Usuario no tiene ningún Ticket</p>
                    <a href="tickets.php?a=open&uid=<?php echo $uid; ?>" class="btn btn-primary btn-create"><i class="bi bi-plus-lg"></i> Crear un nuevo Ticket</a>
                </div>
            <?php else: ?>
                <table class="user-view-tickets-table">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Asunto</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userTickets as $t): ?>
                            <tr>
                                <td><a href="tickets.php?id=<?php echo (int)$t['id']; ?>"><?php echo html($t['ticket_number']); ?></a></td>
                                <td><a href="tickets.php?id=<?php echo (int)$t['id']; ?>"><?php echo html($t['subject']); ?></a></td>
                                <td><?php echo html($t['status_name'] ?? '—'); ?></td>
                                <td><?php echo $t['created'] ? date('d/m/y H:i', strtotime($t['created'])) : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="mt-3 mb-0">
                    <a href="tickets.php?a=open&uid=<?php echo $uid; ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Crear un nuevo Ticket</a>
                </p>
            <?php endif; ?>
        </div>

        <div class="user-view-tab-content" id="tab-notes" style="display:<?php echo $activeTab === 'notes' ? 'block' : 'none'; ?>">
            <div class="empty-state">
                <div class="icon"><i class="bi bi-pin-angle"></i></div>
                <p class="mb-0">No hay notas para este usuario</p>
            </div>
        </div>
    </div>

    <!-- Modal: confirmar eliminar usuario -->
    <div class="modal fade" id="modalDeleteUser" tabindex="-1" aria-labelledby="modalDeleteUserLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title" id="modalDeleteUserLabel"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Eliminar usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">¿Está seguro de que desea eliminar a <strong><?php echo html($viewUserName); ?></strong> (<?php echo html($viewUser['email']); ?>)?</p>
                    <p class="text-muted small mt-2 mb-0">Se eliminarán también todos sus tickets y datos asociados. Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="post" action="users.php" class="d-inline">
                        <input type="hidden" name="do" value="delete">
                        <input type="hidden" name="id" value="<?php echo $uid; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i> Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: asignar organización -->
    <div class="modal fade" id="assignOrgModal" tabindex="-1" aria-labelledby="assignOrgModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title" id="assignOrgModalLabel"><i class="bi bi-building me-2"></i>Asignar organización</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="post" action="users.php?id=<?php echo $uid; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="orgSearch" class="form-label">Buscar organización</label>
                            <input type="text" class="form-control" id="orgSearch" name="org_name" placeholder="Escribe el nombre de la organización..." autocomplete="off">
                            <div id="orgSuggestions" class="list-group mt-2" style="max-height: 200px; overflow-y: auto;"></div>
                        </div>
                        <input type="hidden" name="do" value="assign_org">
                        <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check me-1"></i> Asignar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: confirmar remover organización -->
    <div class="modal fade" id="removeOrgModal" tabindex="-1" aria-labelledby="removeOrgModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title" id="removeOrgModalLabel"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Remover organización</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">¿Está seguro de que desea remover la organización <strong><?php echo html($viewUser['company']); ?></strong> de este usuario?</p>
                    <p class="text-muted small mt-2 mb-0">El usuario quedará sin organización asignada.</p>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="post" action="users.php?id=<?php echo $uid; ?>" class="d-inline">
                        <input type="hidden" name="do" value="remove_org">
                        <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                        <button type="submit" class="btn btn-warning"><i class="bi bi-x-circle me-1"></i> Remover</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
