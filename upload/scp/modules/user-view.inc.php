<?php
if (!isset($viewUser) || !is_array($viewUser)) return;
$uid = (int) $viewUser['id'];
$statusKey = $viewUser['status'] ?? 'active';
$statusLabel = $statusLabels[$statusKey] ?? ucfirst($statusKey);

$mobileName = (string)($viewUserName ?? '');
$mobileEmail = (string)($viewUser['email'] ?? '');
$mobileCompany = trim((string)($viewUser['company'] ?? ''));
$nameForInitials = trim($mobileName !== '' ? $mobileName : $mobileEmail);
$parts = preg_split('/\s+/', $nameForInitials);
$i1 = strtoupper((string)($parts[0][0] ?? ''));
$i2 = '';
if (is_array($parts) && count($parts) > 1) {
    $i2 = strtoupper((string)($parts[1][0] ?? ''));
} elseif (strlen($nameForInitials) > 1) {
    $i2 = strtoupper(substr($nameForInitials, 1, 1));
}
$mobileInitials = trim($i1 . $i2);
if ($mobileInitials === '') $mobileInitials = 'U';
?>

<div class="user-view-wrap">
    <!-- Vista móvil (solo teléfonos) -->
    <?php $activeTab = $_GET['t'] ?? 'tickets'; ?>
    <div class="user-view-mobile d-md-none">
        <div class="user-view-mobile-head">
            <a class="btn btn-light btn-sm user-view-mobile-back" href="users.php" title="Volver">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div class="user-view-mobile-ident">
                <div class="user-view-mobile-avatar" aria-hidden="true"><?php echo html($mobileInitials); ?></div>
                <div class="user-view-mobile-title">
                    <div class="user-view-mobile-name"><?php echo html($mobileName !== '' ? $mobileName : $mobileEmail); ?></div>
                    <div class="user-view-mobile-sub"><?php echo html($mobileEmail); ?></div>
                </div>
            </div>
            <span class="badge user-view-mobile-status <?php echo html($statusKey); ?>"><?php echo html($statusLabel); ?></span>
        </div>

        <div class="user-view-mobile-actions">
            <button type="button" class="btn btn-outline-primary btn-sm flex-grow-1" data-bs-toggle="modal" data-bs-target="#modalEditUser">
                <i class="bi bi-pencil"></i> Editar
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm flex-grow-1" data-bs-toggle="modal" data-bs-target="#modalUserStatus">
                <i class="bi bi-person-gear"></i> Estado
            </button>
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalDeleteUser" title="Eliminar">
                <i class="bi bi-trash"></i>
            </button>
        </div>

        <div class="user-view-mobile-cards">
            <div class="user-view-mobile-card">
                <div class="uvm-row">
                    <div class="uvm-k">Organización</div>
                    <div class="uvm-v">
                        <?php if ($mobileCompany !== ''): ?>
                            <?php echo html($mobileCompany); ?>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-2 text-danger" data-bs-toggle="modal" data-bs-target="#removeOrgModal">Remover</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="modal" data-bs-target="#assignOrgModal">Asignar organización</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($viewUser['phone'])): ?>
                    <div class="uvm-row">
                        <div class="uvm-k">Teléfono</div>
                        <div class="uvm-v"><?php echo html((string)$viewUser['phone']); ?></div>
                    </div>
                <?php endif; ?>
                <div class="uvm-row">
                    <div class="uvm-k">Creado</div>
                    <div class="uvm-v"><?php echo $viewUser['created'] ? date('d/m/y H:i', strtotime($viewUser['created'])) : '—'; ?></div>
                </div>
                <div class="uvm-row">
                    <div class="uvm-k">Actualizado</div>
                    <div class="uvm-v"><?php echo $viewUser['updated'] ? date('d/m/y H:i', strtotime($viewUser['updated'])) : '—'; ?></div>
                </div>
            </div>

            <div class="user-view-mobile-card">
                <form method="post" action="users.php?id=<?php echo $uid; ?>" class="d-grid gap-2">
                    <input type="hidden" name="do" value="send_user_reset">
                    <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                    <input type="hidden" name="tab" value="<?php echo html((string)($activeTab ?? 'tickets')); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    <button type="submit" class="btn btn-outline-dark btn-sm">
                        <i class="bi bi-envelope"></i> Enviar restablecer contraseña
                    </button>
                </form>
            </div>
        </div>

        <div class="user-view-mobile-tabs">
            <a class="uvm-tab <?php echo $activeTab === 'tickets' ? 'active' : ''; ?>" href="users.php?id=<?php echo $uid; ?>&t=tickets">
                <i class="bi bi-ticket-perforated"></i> Tickets
            </a>
            <a class="uvm-tab <?php echo $activeTab === 'notes' ? 'active' : ''; ?>" href="users.php?id=<?php echo $uid; ?>&t=notes">
                <i class="bi bi-pin-angle"></i> Notas
            </a>
        </div>

        <?php if ($activeTab === 'notes'): ?>
            <div class="user-view-mobile-panel">
                <div class="uvm-panel-head">
                    <div class="uvm-panel-title">Notas</div>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddUserNote">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
                <?php if (empty($userNotes)): ?>
                    <div class="uvm-empty">
                        <div class="icon"><i class="bi bi-pin-angle"></i></div>
                        <div>No hay notas para este usuario.</div>
                    </div>
                <?php else: ?>
                    <div class="uvm-notes">
                        <?php foreach ($userNotes as $n): ?>
                            <?php
                                $noteId = (int)($n['id'] ?? 0);
                                $noteText = (string)($n['note'] ?? '');
                                $noteWhen = (string)($n['updated'] ?? $n['created'] ?? '');
                                $noteStaff = trim((string)($n['staff_name'] ?? ''));
                            ?>
                            <div class="uvm-note">
                                <div class="uvm-note-body"><?php echo nl2br(html($noteText)); ?></div>
                                <div class="uvm-note-foot">
                                    <span><?php echo $noteStaff !== '' ? html($noteStaff) : '—'; ?></span>
                                    <span class="dot">·</span>
                                    <span><?php echo $noteWhen !== '' ? html(formatDate($noteWhen)) : '—'; ?></span>
                                    <span class="spacer"></span>
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            data-bs-toggle="modal" data-bs-target="#modalEditUserNote"
                                            data-note-id="<?php echo $noteId; ?>"
                                            data-note-text="<?php echo html($noteText); ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm"
                                            data-bs-toggle="modal" data-bs-target="#modalDeleteUserNote"
                                            data-note-id="<?php echo $noteId; ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="user-view-mobile-panel">
                <div class="uvm-panel-head">
                    <div class="uvm-panel-title">Tickets</div>
                    <a href="tickets.php?a=open&uid=<?php echo $uid; ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg"></i>
                    </a>
                </div>
                <?php if (empty($userTickets)): ?>
                    <div class="uvm-empty">
                        <div class="icon"><i class="bi bi-inbox"></i></div>
                        <div>Usuario no tiene ningún ticket.</div>
                        <a href="tickets.php?a=open&uid=<?php echo $uid; ?>" class="btn btn-primary btn-sm mt-2">Crear ticket</a>
                    </div>
                <?php else: ?>
                    <div class="uvm-tickets">
                        <?php foreach ($userTickets as $t): ?>
                            <?php
                                $ticketId = (int)($t['id'] ?? 0);
                                $ticketHref = 'tickets.php?id=' . $ticketId . '&back=' . urlencode('users.php?id=' . (int)$uid . '&t=tickets');
                                $ticketNum = (string)($t['ticket_number'] ?? '');
                                $ticketSub = (string)($t['subject'] ?? '');
                                $ticketStatus = (string)($t['status_name'] ?? '—');
                                $ticketCreated = (string)($t['created'] ?? '');
                            ?>
                            <a class="uvm-ticket" href="<?php echo html($ticketHref); ?>">
                                <div class="uvm-ticket-top">
                                    <div class="uvm-ticket-num"><?php echo html($ticketNum); ?></div>
                                    <div class="uvm-ticket-status"><?php echo html($ticketStatus); ?></div>
                                </div>
                                <div class="uvm-ticket-subject"><?php echo html($ticketSub); ?></div>
                                <div class="uvm-ticket-foot">
                                    <i class="bi bi-clock"></i>
                                    <?php echo $ticketCreated !== '' ? html(formatDate($ticketCreated)) : '—'; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

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
                    <li>
                        <form method="post" action="users.php?id=<?php echo $uid; ?>" class="d-inline" id="formSendUserReset">
                            <input type="hidden" name="do" value="send_user_reset">
                            <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                            <input type="hidden" name="tab" value="<?php echo html((string)($_GET['t'] ?? 'tickets')); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                            <button type="submit" class="dropdown-item" id="btnSendUserReset"><i class="bi bi-envelope"></i> Enviar restablecer contraseña</button>
                        </form>
                    </li>
                    <li><a class="dropdown-item" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalEditUser"><i class="bi bi-pencil"></i> Editar perfil</a></li>
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

    <!-- Modal: editar perfil de usuario (debe estar fuera de contenedores ocultos en móvil) -->
    <div class="modal fade" id="modalEditUser" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar perfil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="post" action="users.php?id=<?php echo $uid; ?>">
                    <div class="modal-body">
                        <input type="hidden" name="do" value="update_profile">
                        <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required value="<?php echo html($viewUser['email']); ?>">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nombre</label>
                                    <input type="text" name="firstname" class="form-control" required value="<?php echo html($viewUser['firstname']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Apellido</label>
                                    <input type="text" name="lastname" class="form-control" required value="<?php echo html($viewUser['lastname']); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo html((string)($viewUser['phone'] ?? '')); ?>">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Dirección <span class="text-danger">*</span></label>
                            <input type="text" name="address" class="form-control" value="<?php echo html((string)($viewUser['address'] ?? '')); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function(){
        function forceCloseEditModal(){
            var el = document.getElementById('modalEditUser');
            if (el && window.bootstrap && window.bootstrap.Modal) {
                try {
                    if (typeof window.bootstrap.Modal.getInstance === 'function') {
                        var inst = window.bootstrap.Modal.getInstance(el);
                        if (inst) inst.hide();
                    }
                } catch (e) {}
            }

            try {
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('padding-right');
                document.querySelectorAll('.modal-backdrop').forEach(function(b){ b.remove(); });
            } catch (e) {}

            if (el) {
                el.classList.remove('show');
                el.style.display = 'none';
                el.setAttribute('aria-hidden', 'true');
            }
        }

        window.addEventListener('pageshow', function(ev){
            if (ev && ev.persisted) {
                forceCloseEditModal();
            }
        });
    })();
    </script>

    <div class="user-view-card">
        <div class="user-view-profile">
            <div class="user-view-avatar">
                <i class="bi bi-person-fill"></i>
            </div>
            <div class="user-view-details">
                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'reset_sent'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert" style="grid-column: 1 / -1;">
                        Se envió el correo de restablecer contraseña.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                    <script>
                    (function(){
                        try {
                            var url = new URL(window.location.href);
                            url.searchParams.delete('msg');
                            history.replaceState(null, '', url.toString());
                        } catch (e) {}
                    })();
                    </script>
                <?php endif; ?>
                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'status_updated'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert" style="grid-column: 1 / -1;">
                        Estado de usuario actualizado correctamente.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                    <script>
                    (function(){
                        try {
                            var url = new URL(window.location.href);
                            url.searchParams.delete('msg');
                            history.replaceState(null, '', url.toString());
                        } catch (e) {}
                    })();
                    </script>
                <?php endif; ?>
                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'profile_updated'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert" style="grid-column: 1 / -1;">
                        Perfil de usuario actualizado correctamente.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                    <script>
                    (function(){
                        try {
                            var url = new URL(window.location.href);
                            url.searchParams.delete('msg');
                            history.replaceState(null, '', url.toString());
                        } catch (e) {}
                    })();
                    </script>
                <?php endif; ?>
                <div class="user-view-detail">
                    <label>Nombre</label>
                    <div class="value">
                        <a href="#"><?php echo html($viewUserName); ?></a>
                        <a href="javascript:void(0)" class="edit-icon" data-bs-toggle="modal" data-bs-target="#modalEditUser" title="Editar perfil"><i class="bi bi-pencil"></i></a>
                    </div>
                </div>
                <div class="user-view-detail">
                    <label>Email</label>
                    <div class="value"><?php echo html($viewUser['email']); ?></div>
                </div>
                <div class="user-view-detail">
                    <label>Dirección</label>
                    <div class="value"><?php echo html($viewUser['address'] ?? '—'); ?></div>
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
                <?php
                    $backRel = 'users.php?id=' . (int)$uid;
                    if ($activeTab !== '') {
                        $backRel .= '&t=' . urlencode($activeTab);
                    }
                ?>
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
                                <?php $ticketHref = 'tickets.php?id=' . (int)$t['id'] . '&back=' . urlencode($backRel); ?>
                                <td><a href="<?php echo html($ticketHref); ?>"><?php echo html($t['ticket_number']); ?></a></td>
                                <td><a href="<?php echo html($ticketHref); ?>"><?php echo html($t['subject']); ?></a></td>
                                <td><?php echo html($t['status_name'] ?? '—'); ?></td>
                                <td><?php echo $t['created'] ? date('d/m/y H:i', strtotime($t['created'])) : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (isset($tTotalPages) && $tTotalPages > 1): ?>
                    <div class="table-footer-bar" style="border-top: none; padding: 15px;">
                        <div class="showing-text">
                            Mostrando <?php echo $userTicketTotal ? ($tOffset + 1) : 0; ?> – <?php echo min($tOffset + $perPageLimit, $userTicketTotal); ?> de <?php echo $userTicketTotal; ?>
                        </div>
                        <div class="pagination-wrap">
                            <?php if ($tp > 1): ?>
                                <a href="users.php?<?php echo http_build_query(['id' => $uid, 't' => 'tickets', 'tp' => $tp - 1]); ?>"><i class="bi bi-chevron-left"></i></a>
                            <?php endif; ?>
                            <?php for ($i = max(1, $tp - 2); $i <= min($tTotalPages, $tp + 2); $i++): ?>
                                <?php if ($i === $tp): ?>
                                    <strong style="margin: 0 4px; color:var(--bs-primary);"><?php echo $i; ?></strong>
                                <?php else: ?>
                                    <a href="users.php?<?php echo http_build_query(['id' => $uid, 't' => 'tickets', 'tp' => $i]); ?>" style="margin: 0 4px;"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <?php if ($tp < $tTotalPages): ?>
                                <a href="users.php?<?php echo http_build_query(['id' => $uid, 't' => 'tickets', 'tp' => $tp + 1]); ?>"><i class="bi bi-chevron-right"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <p class="mt-3 mb-0">
                    <a href="tickets.php?a=open&uid=<?php echo $uid; ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Crear un nuevo Ticket</a>
                </p>
            <?php endif; ?>
        </div>

        <div class="user-view-tab-content" id="tab-notes" style="display:<?php echo $activeTab === 'notes' ? 'block' : 'none'; ?>">
            <?php if (empty($userNotes)): ?>
                <div class="empty-state">
                    <div class="icon"><i class="bi bi-pin-angle"></i></div>
                    <p class="mb-0">No hay notas para este usuario</p>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($userNotes as $n): ?>
                        <?php
                        $noteId = (int)($n['id'] ?? 0);
                        $noteText = (string)($n['note'] ?? '');
                        $noteCreated = (string)($n['created'] ?? '');
                        $noteStaff = trim((string)($n['staff_name'] ?? ''));
                        $noteStaff = $noteStaff !== '' ? $noteStaff : '—';
                        ?>
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start" style="gap:10px;">
                                    <div class="text-muted small">
                                        <i class="bi bi-person"></i>
                                        <?php echo $noteCreated ? date('d/m/y H:i:s', strtotime($noteCreated)) : '—'; ?>
                                    </div>
                                    <div class="d-flex align-items-center" style="gap:10px;">
                                        <div class="small" style="white-space:nowrap;">
                                            <?php echo html($noteStaff); ?>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditUserNote" data-note-id="<?php echo $noteId; ?>" data-note-text="<?php echo html($noteText); ?>"><i class="bi bi-pencil"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalDeleteUserNote" data-note-id="<?php echo $noteId; ?>"><i class="bi bi-trash"></i></button>
                                    </div>
                                </div>
                                <div class="mt-2" style="white-space:pre-wrap;">
                                    <?php echo html($noteText); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="card mt-3">
                <div class="card-body">
                    <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalAddUserNote" style="text-decoration:none;">
                        <i class="bi bi-plus-lg"></i>
                        Haga clic para crear una nueva nota
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalAddUserNote" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title"><i class="bi bi-pin-angle me-2"></i>Nueva nota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="post" action="users.php?id=<?php echo $uid; ?>&t=notes">
                    <div class="modal-body">
                        <input type="hidden" name="do" value="add_user_note">
                        <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                        <label class="form-label">Nota</label>
                        <textarea name="note" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditUserNote" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar nota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="post" action="users.php?id=<?php echo $uid; ?>&t=notes" id="formEditUserNote">
                    <div class="modal-body">
                        <input type="hidden" name="do" value="update_user_note">
                        <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                        <input type="hidden" name="note_id" id="edit_note_id" value="">
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                        <label class="form-label">Nota</label>
                        <textarea name="note" class="form-control" rows="5" required id="edit_note_text"></textarea>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDeleteUserNote" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Eliminar nota?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="post" action="users.php?id=<?php echo $uid; ?>&t=notes" id="formDeleteUserNote">
                    <div class="modal-body">
                        <input type="hidden" name="do" value="delete_user_note">
                        <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                        <input type="hidden" name="note_id" id="delete_note_id" value="">
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                        <div>Esta acción no se puede deshacer.</div>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function(){
        var m = document.getElementById('modalEditUserNote');
        if (!m) return;
        m.addEventListener('show.bs.modal', function (ev) {
            try {
                var btn = ev.relatedTarget;
                if (!btn) return;
                var id = (btn.getAttribute('data-note-id') || '').toString();
                var text = (btn.getAttribute('data-note-text') || '').toString();
                var idEl = document.getElementById('edit_note_id');
                var txtEl = document.getElementById('edit_note_text');
                if (idEl) idEl.value = id;
                if (txtEl) txtEl.value = text;
            } catch (e) {}
        });
    })();
    </script>

    <div class="modal fade" id="modalSendResetLoading" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body" style="padding:18px 16px;">
                    <div class="d-flex align-items-center" style="gap:12px;">
                        <div class="spinner-border" role="status" aria-hidden="true"></div>
                        <div>
                            <div style="font-weight:700;">Enviando correo...</div>
                            <div class="text-muted small">Por favor espera un momento</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function(){
        var form = document.getElementById('formSendUserReset');
        var btn = document.getElementById('btnSendUserReset');
        var modalEl = document.getElementById('modalSendResetLoading');
        if (!form || !modalEl) return;
        form.addEventListener('submit', function(){
            try {
                if (btn) {
                    btn.disabled = true;
                    btn.setAttribute('aria-disabled', 'true');
                }
                if (window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            } catch (e) {}
        });
    })();
    </script>

    <script>
    (function(){
        var m = document.getElementById('modalDeleteUserNote');
        if (!m) return;
        m.addEventListener('show.bs.modal', function (ev) {
            try {
                var btn = ev.relatedTarget;
                if (!btn) return;
                var id = (btn.getAttribute('data-note-id') || '').toString();
                var idEl = document.getElementById('delete_note_id');
                if (idEl) idEl.value = id;
            } catch (e) {}
        });
    })();
    </script>

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
