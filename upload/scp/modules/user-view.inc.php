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
    <?php 
    $msg = $_GET['msg'] ?? '';
    $alertMsg = '';
    if ($msg) {
        switch($msg) {
            case 'reset_sent': $alertMsg = 'Se envió el correo de restablecer contraseña.'; break;
            case 'status_updated': $alertMsg = 'Estado de usuario actualizado correctamente.'; break;
            case 'user_updated':
            case 'profile_updated': $alertMsg = 'Perfil de usuario actualizado correctamente.'; break;
            case 'org_assigned': $alertMsg = 'Organización asignada correctamente.'; break;
            case 'org_removed': $alertMsg = 'Organización removida correctamente.'; break;
        }
    }
    if ($alertMsg): ?>
        <div class="alert alert-success alert-dismissible fade show mx-3 mt-3" role="alert" style="border-radius: 12px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo html($alertMsg); ?>
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
    <!-- Vista móvil (solo teléfonos) -->
    <?php $activeTab = $_GET['t'] ?? 'tickets'; ?>
    <div class="user-view-mobile d-md-none">
        <!-- Cabecera de Perfil Premium -->
        <div class="user-view-mobile-head uvm-status-<?php echo html($statusKey); ?>">
            <a class="uvm-back-btn" href="users.php" title="Volver">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div class="user-view-mobile-ident">
                <div class="user-view-mobile-avatar"><?php echo html($mobileInitials); ?></div>
                <div class="user-view-mobile-title">
                    <div class="user-view-mobile-name"><?php echo html($mobileName !== '' ? $mobileName : $mobileEmail); ?></div>
                    <div class="user-view-mobile-sub">
                        <i class="bi bi-envelope-at" style="opacity: 0.6; margin-right: 3px;"></i><?php echo html($mobileEmail); ?>
                    </div>
                </div>
            </div>
            <span class="badge uvm-status-badge <?php echo html($statusKey); ?>"><?php echo html($statusLabel); ?></span>
        </div>

        <!-- Acciones Rápidas -->
        <div class="user-view-mobile-actions-row">
            <button type="button" class="uvm-action-btn uvm-btn-edit" data-bs-toggle="modal" data-bs-target="#modalEditUser">
                <i class="bi bi-pencil-square"></i> Editar perfil
            </button>
            <!-- Tuerca de configuración con dropdown -->
            <div class="dropdown">
                <button type="button" class="uvm-action-btn uvm-btn-gear" data-bs-toggle="dropdown" aria-expanded="false" title="Más opciones">
                    <i class="bi bi-gear-fill"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end uvm-gear-dropdown">
                    <li>
                        <a class="dropdown-item uvm-dropdown-item-status" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalUserStatus">
                            <span class="uvm-item-icon-wrap"><i class="bi bi-person-gear"></i></span>
                            <span class="uvm-item-text-wrap">
                                <span class="uvm-item-title">Cambiar estado</span>
                                <span class="uvm-item-desc">Activar, suspender o bloquear</span>
                            </span>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="post" action="users.php?id=<?php echo $uid; ?>" id="formSendUserResetMobile">
                            <input type="hidden" name="do" value="send_user_reset">
                            <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                            <input type="hidden" name="tab" value="<?php echo html((string)($activeTab ?? 'tickets')); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                            <button type="submit" class="dropdown-item uvm-dropdown-item-reset" id="btnSendUserResetMobile">
                                <span class="uvm-item-icon-wrap"><i class="bi bi-shield-lock"></i></span>
                                <span class="uvm-item-text-wrap">
                                    <span class="uvm-item-title">Restablecer contraseña</span>
                                    <span class="uvm-item-desc">Enviar enlace por email</span>
                                </span>
                            </button>
                        </form>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <button type="button" class="dropdown-item text-danger uvm-dropdown-item-delete" data-bs-toggle="modal" data-bs-target="#modalDeleteUser">
                            <span class="uvm-item-icon-wrap"><i class="bi bi-trash"></i></span>
                            <span class="uvm-item-text-wrap">
                                <span class="uvm-item-title">Eliminar usuario</span>
                                <span class="uvm-item-desc">Borrar cuenta permanentemente</span>
                            </span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <div class="user-view-mobile-details-section">
            <div class="user-mobile-details-collapse">
                <button class="user-mobile-toggle-btn w-100 mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#userMobileCollapse" aria-expanded="false" aria-controls="userMobileCollapse" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; font-weight: 700; color: #475569; display: flex; justify-content: center; align-items: center; gap: 8px; box-shadow: 0 2px 4px rgba(15,23,42,0.02); transition: all 0.2s ease;">
                    <span class="toggle-text-show"><i class="bi bi-person-vcard me-1"></i> Ver Datos de Contacto</span>
                    <span class="toggle-text-hide d-none"><i class="bi bi-chevron-up me-1"></i> Ocultar Datos</span>
                </button>
                
                <div class="collapse" id="userMobileCollapse">
                    <div class="user-view-mobile-card-details mb-3">
                        <div class="uvm-detail-item">
                            <div class="uvm-detail-icon"><i class="bi bi-person"></i></div>
                            <div class="uvm-detail-content">
                                <span class="uvm-detail-label">Nombre Completo</span>
                                <span class="uvm-detail-val text-dark fw-bold"><?php echo html($mobileName !== '' ? $mobileName : '—'); ?></span>
                            </div>
                        </div>

                        <div class="uvm-detail-item">
                            <div class="uvm-detail-icon"><i class="bi bi-envelope-at"></i></div>
                            <div class="uvm-detail-content">
                                <span class="uvm-detail-label">Correo</span>
                                <span class="uvm-detail-val">
                                    <a href="mailto:<?php echo html($mobileEmail); ?>" class="text-dark" style="text-decoration:none;">
                                        <?php echo html($mobileEmail); ?>
                                    </a>
                                </span>
                            </div>
                        </div>
                        <div class="uvm-detail-item">
                            <div class="uvm-detail-icon"><i class="bi bi-building"></i></div>
                            <div class="uvm-detail-content">
                                <span class="uvm-detail-label">Organización</span>
                                <span class="uvm-detail-val">
                                    <?php if ($mobileCompany !== ''): ?>
                                        <strong class="text-dark"><?php echo html($mobileCompany); ?></strong>
                                        <button type="button" class="uvm-action-link text-danger" data-bs-toggle="modal" data-bs-target="#removeOrgModal">(Remover)</button>
                                    <?php else: ?>
                                        <button type="button" class="uvm-action-link" data-bs-toggle="modal" data-bs-target="#assignOrgModal">Asignar organización</button>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <?php if (!empty($viewUser['phone'])): ?>
                        <div class="uvm-detail-item">
                            <div class="uvm-detail-icon"><i class="bi bi-telephone"></i></div>
                            <div class="uvm-detail-content">
                                <span class="uvm-detail-label">Teléfono</span>
                                <span class="uvm-detail-val">
                                    <a href="tel:<?php echo html((string)$viewUser['phone']); ?>" class="text-dark fw-bold" style="text-decoration:none;">
                                        <?php echo html((string)$viewUser['phone']); ?>
                                    </a>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($viewUser['address'])): ?>
                        <div class="uvm-detail-item">
                            <div class="uvm-detail-icon"><i class="bi bi-geo-alt"></i></div>
                            <div class="uvm-detail-content">
                                <span class="uvm-detail-label">Dirección</span>
                                <span class="uvm-detail-val text-dark"><?php echo html((string)$viewUser['address']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="uvm-detail-item">
                            <div class="uvm-detail-icon"><i class="bi bi-calendar-plus"></i></div>
                            <div class="uvm-detail-content">
                                <span class="uvm-detail-label">Creado</span>
                                <span class="uvm-detail-val text-muted"><?php echo $viewUser['created'] ? date('d/m/y h:i A', strtotime($viewUser['created'])) : '—'; ?></span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <script>
            document.addEventListener("DOMContentLoaded", function() {
                var collUser = document.getElementById('userMobileCollapse');
                var btnUser = document.querySelector('.user-mobile-toggle-btn');
                if(collUser && btnUser) {
                    collUser.addEventListener('show.bs.collapse', function () {
                        btnUser.querySelector('.toggle-text-show').classList.add('d-none');
                        btnUser.querySelector('.toggle-text-hide').classList.remove('d-none');
                        btnUser.style.backgroundColor = '#f1f5f9';
                    });
                    collUser.addEventListener('hide.bs.collapse', function () {
                        btnUser.querySelector('.toggle-text-show').classList.remove('d-none');
                        btnUser.querySelector('.toggle-text-hide').classList.add('d-none');
                        btnUser.style.backgroundColor = '#ffffff';
                    });
                }
            });
            </script>

        </div>

        <!-- Pestañas de Navegación -->
        <div class="user-view-mobile-tabs-pill">
            <a class="uvm-tab-pill <?php echo $activeTab === 'tickets' ? 'active' : ''; ?>" href="users.php?id=<?php echo $uid; ?>&t=tickets">
                <i class="bi bi-ticket-perforated"></i> Tickets
                <?php if (!empty($userTicketTotal)): ?>
                    <span class="badge bg-danger ms-1" style="font-size: 0.65rem; padding: 2px 6px; border-radius: 20px;"><?php echo html($userTicketTotal); ?></span>
                <?php endif; ?>
            </a>
            <a class="uvm-tab-pill <?php echo $activeTab === 'notes' ? 'active' : ''; ?>" href="users.php?id=<?php echo $uid; ?>&t=notes">
                <i class="bi bi-pin-angle"></i> Notas
                <?php if (!empty($userNotes)): ?>
                    <span class="badge bg-secondary ms-1" style="font-size: 0.65rem; padding: 2px 6px; border-radius: 20px;"><?php echo count($userNotes); ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Paneles -->
        <?php if ($activeTab === 'notes'): ?>
            <div class="user-view-mobile-panel-modern">
                <div class="uvm-panel-head">
                    <div class="uvm-panel-title"><i class="bi bi-pin-angle-fill text-danger me-1"></i> Notas del usuario</div>
                    <button type="button" class="btn btn-dark btn-sm uvm-add-note-btn" data-bs-toggle="modal" data-bs-target="#modalAddUserNote">
                        <i class="bi bi-plus-lg"></i> Nueva nota
                    </button>
                </div>
                <?php if (empty($userNotes)): ?>
                    <div class="uvm-empty-state">
                        <div class="icon"><i class="bi bi-pin-angle"></i></div>
                        <div class="title">Sin notas registradas</div>
                        <div class="desc">Las notas son privadas y solo visibles para los agentes.</div>
                    </div>
                <?php else: ?>
                    <div class="uvm-notes-list">
                        <?php foreach ($userNotes as $n): ?>
                            <?php
                                $noteId = (int)($n['id'] ?? 0);
                                $noteText = (string)($n['note'] ?? '');
                                $noteWhen = (string)($n['updated'] ?? $n['created'] ?? '');
                                $noteStaff = trim((string)($n['staff_name'] ?? ''));
                            ?>
                            <div class="uvm-note-card-modern">
                                <div class="uvm-note-text-body"><?php echo nl2br(html($noteText)); ?></div>
                                <div class="uvm-note-meta-foot">
                                    <div class="uvm-note-author-info">
                                        <i class="bi bi-person-circle"></i>
                                        <span><?php echo $noteStaff !== '' ? html($noteStaff) : 'Sistema'; ?></span>
                                        <span class="dot">•</span>
                                        <span><?php echo $noteWhen !== '' ? html(formatDate($noteWhen)) : '—'; ?></span>
                                    </div>
                                    <div class="uvm-note-actions">
                                        <button type="button" class="uvm-note-action-icon-btn text-primary"
                                                data-bs-toggle="modal" data-bs-target="#modalEditUserNote"
                                                data-note-id="<?php echo $noteId; ?>"
                                                data-note-text="<?php echo html($noteText); ?>"
                                                title="Editar nota">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="uvm-note-action-icon-btn text-danger"
                                                data-bs-toggle="modal" data-bs-target="#modalDeleteUserNote"
                                                data-note-id="<?php echo $noteId; ?>"
                                                title="Eliminar nota">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="user-view-mobile-panel-modern">
                <div class="uvm-panel-head">
                    <div class="uvm-panel-title"><i class="bi bi-ticket-perforated-fill text-danger me-1"></i> Historial de Tickets</div>
                    <a href="tickets.php?a=open&uid=<?php echo $uid; ?>" class="btn btn-primary btn-sm uvm-add-ticket-btn">
                        <i class="bi bi-plus-lg"></i> Nuevo ticket
                    </a>
                </div>
                <?php if (empty($userTickets)): ?>
                    <div class="uvm-empty-state">
                        <div class="icon"><i class="bi bi-inbox"></i></div>
                        <div class="title">Sin tickets asociados</div>
                        <div class="desc">Este usuario no tiene tickets registrados actualmente.</div>
                        <a href="tickets.php?a=open&uid=<?php echo $uid; ?>" class="btn btn-primary btn-sm mt-3 px-4" style="border-radius:10px;"><i class="bi bi-plus-lg me-1"></i>Crear el primero</a>
                    </div>
                <?php else: ?>
                    <div class="uvm-tickets-list-modern">
                        <?php foreach ($userTickets as $t): ?>
                            <?php
                                $ticketId = (int)($t['id'] ?? 0);
                                $ticketHref = 'tickets.php?id=' . $ticketId . '&back=' . urlencode('users.php?id=' . (int)$uid . '&t=tickets');
                                $ticketNum = (string)($t['ticket_number'] ?? '');
                                $ticketSub = (string)($t['subject'] ?? '');
                                $ticketStatus = (string)($t['status_name'] ?? '—');
                                $ticketCreated = (string)($t['created'] ?? '');
                            ?>
                            <a class="uvm-ticket-item-modern" href="<?php echo html($ticketHref); ?>">
                                <div class="uvm-ticket-item-top">
                                    <span class="uvm-ticket-item-num">#<?php echo html($ticketNum); ?></span>
                                    <span class="badge uvm-ticket-status-badge"><?php echo html($ticketStatus); ?></span>
                                </div>
                                <div class="uvm-ticket-item-sub"><?php echo html($ticketSub); ?></div>
                                <div class="uvm-ticket-item-foot">
                                    <span class="uvm-ticket-date"><i class="bi bi-calendar-event"></i> <?php echo $ticketCreated !== '' ? html(formatDate($ticketCreated)) : '—'; ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php if (isset($tTotalPages) && $tTotalPages > 1): ?>
                        <div class="uvm-pagination-wrap">
                            <div class="uvm-showing-text text-center">
                                Mostrando <?php echo $userTicketTotal ? ($tOffset + 1) : 0; ?> – <?php echo min($tOffset + $perPageLimit, $userTicketTotal); ?> de <?php echo $userTicketTotal; ?>
                            </div>
                            <div class="uvm-pagination justify-content-center">
                                <?php if ($tp > 1): ?>
                                    <a class="uvm-page-btn" href="users.php?<?php echo http_build_query(['id' => $uid, 't' => 'tickets', 'tp' => $tp - 1]); ?>"><i class="bi bi-chevron-left"></i></a>
                                <?php else: ?>
                                    <span class="uvm-page-btn disabled"><i class="bi bi-chevron-left"></i></span>
                                <?php endif; ?>
                                
                                <?php 
                                $startPage = max(1, $tp - 1);
                                $endPage = min($tTotalPages, $tp + 1);
                                
                                if ($startPage > 1) {
                                    echo '<a class="uvm-page-btn" href="users.php?' . http_build_query(['id' => $uid, 't' => 'tickets', 'tp' => 1]) . '">1</a>';
                                    if ($startPage > 2) {
                                        echo '<span class="uvm-page-dots">...</span>';
                                    }
                                }
                                
                                for ($i = $startPage; $i <= $endPage; $i++) {
                                    if ($i === $tp) {
                                        echo '<strong class="uvm-page-btn active">' . $i . '</strong>';
                                    } else {
                                        echo '<a class="uvm-page-btn" href="users.php?' . http_build_query(['id' => $uid, 't' => 'tickets', 'tp' => $i]) . '">' . $i . '</a>';
                                    }
                                }
                                
                                if ($endPage < $tTotalPages) {
                                    if ($endPage < $tTotalPages - 1) {
                                        echo '<span class="uvm-page-dots">...</span>';
                                    }
                                    echo '<a class="uvm-page-btn" href="users.php?' . http_build_query(['id' => $uid, 't' => 'tickets', 'tp' => $tTotalPages]) . '">' . $tTotalPages . '</a>';
                                }
                                ?>
                                
                                <?php if ($tp < $tTotalPages): ?>
                                    <a class="uvm-page-btn" href="users.php?<?php echo http_build_query(['id' => $uid, 't' => 'tickets', 'tp' => $tp + 1]); ?>"><i class="bi bi-chevron-right"></i></a>
                                <?php else: ?>
                                    <span class="uvm-page-btn disabled"><i class="bi bi-chevron-right"></i></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
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
                    <label>Teléfono</label>
                    <div class="value">
                        <?php if (!empty($viewUser['phone'])): ?>
                            <a href="tel:<?php echo html((string)$viewUser['phone']); ?>" style="color:inherit;text-decoration:none;">
                                <i class="bi bi-telephone me-1"></i><?php echo html((string)$viewUser['phone']); ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted" style="font-size:0.88rem;">—</span>
                        <?php endif; ?>
                    </div>
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
                    <div class="value"><?php echo $viewUser['created'] ? date('d/m/y h:i A', strtotime($viewUser['created'])) : '—'; ?></div>
                </div>
                <div class="user-view-detail">
                    <label>Actualizado</label>
                    <div class="value"><?php echo $viewUser['updated'] ? date('d/m/y h:i A', strtotime($viewUser['updated'])) : '—'; ?></div>
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
                <table class="user-view-tickets-table uvt-premium">
                    <thead>
                        <tr>
                            <th class="uvt-col-num">Ticket</th>
                            <th class="uvt-col-subject">Asunto</th>
                            <th class="uvt-col-status">Estado</th>
                            <th class="uvt-col-date">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userTickets as $t): ?>
                            <?php
                                $ticketHref = 'tickets.php?id=' . (int)$t['id'] . '&back=' . urlencode($backRel);
                                $tStatusRaw = strtolower(trim($t['status_name'] ?? ''));
                                $tStatusClass = 'uvt-status-default';
                                if (in_array($tStatusRaw, ['abierto','open','nuevo','new'])) $tStatusClass = 'uvt-status-open';
                                elseif (in_array($tStatusRaw, ['cerrado','closed','resuelto','resolved'])) $tStatusClass = 'uvt-status-closed';
                                elseif (in_array($tStatusRaw, ['en camino','en curso','in progress','pendiente','pending','en espera'])) $tStatusClass = 'uvt-status-pending';
                                elseif (in_array($tStatusRaw, ['vencido','overdue','expirado'])) $tStatusClass = 'uvt-status-overdue';
                            ?>
                            <tr class="uvt-row" onclick="window.location.href='<?php echo html($ticketHref); ?>'" style="cursor:pointer;">
                                <td class="uvt-cell-num">
                                    <a href="<?php echo html($ticketHref); ?>" class="uvt-ticket-number">#<?php echo html($t['ticket_number']); ?></a>
                                </td>
                                <td class="uvt-cell-subject">
                                    <a href="<?php echo html($ticketHref); ?>" class="uvt-subject-link" title="<?php echo html($t['subject']); ?>"><?php echo html($t['subject']); ?></a>
                                </td>
                                <td class="uvt-cell-status">
                                    <span class="uvt-status-badge <?php echo $tStatusClass; ?>"><?php echo html($t['status_name'] ?? '—'); ?></span>
                                </td>
                                <td class="uvt-cell-date">
                                    <span class="uvt-date-text"><i class="bi bi-clock me-1" style="font-size:0.75rem;opacity:0.5;"></i><?php echo $t['created'] ? date('d/m/y h:i A', strtotime($t['created'])) : '—'; ?></span>
                                </td>
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
                                        <?php echo $noteCreated ? date('d/m/y h:i A', strtotime($noteCreated)) : '—'; ?>
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
        var modalEl = document.getElementById('modalSendResetLoading');
        if (!modalEl) return;

        function bindLoading(formId, btnId) {
            var form = document.getElementById(formId);
            var btn = document.getElementById(btnId);
            if (!form) return;
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
        }

        bindLoading('formSendUserReset', 'btnSendUserReset');
        bindLoading('formSendUserResetMobile', 'btnSendUserResetMobile');
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

<script>
(function(){
    function wireOrgAutocomplete(){
        try {
            var input = document.getElementById('orgSearch');
            var suggestions = document.getElementById('orgSuggestions');
            if (!input || !suggestions) return;

            var lastController = null;
            input.addEventListener('input', function(){
                var query = (input.value || '').toString().trim();
                if (query.length < 2) {
                    suggestions.innerHTML = '';
                    return;
                }

                if (lastController && typeof lastController.abort === 'function') {
                    lastController.abort();
                }
                lastController = (typeof AbortController !== 'undefined') ? new AbortController() : null;

                var url = 'users.php?ajax=search_orgs&q=' + encodeURIComponent(query);
                fetch(url, lastController ? { signal: lastController.signal } : undefined)
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        suggestions.innerHTML = '';
                        if (!Array.isArray(data)) return;
                        
                        data.forEach(function(org){
                            var item = document.createElement('a');
                            item.href = '#';
                            item.className = 'list-group-item list-group-item-action d-flex align-items-center gap-2';
                            item.innerHTML = '<i class="bi bi-building text-primary"></i> ' + (org && org.name ? org.name : '');
                            
                            item.addEventListener('click', function(ev){
                                ev.preventDefault();
                                input.value = (org && org.name ? org.name : '');
                                suggestions.innerHTML = '';
                            });
                            suggestions.appendChild(item);
                        });
                    })
                    .catch(function(err){
                        if (err && err.name !== 'AbortError') {
                            console.error('Error searching orgs:', err);
                        }
                    });
            });

            // Cerrar sugerencias al hacer clic fuera
            document.addEventListener('click', function(e){
                if (e.target !== input && e.target !== suggestions && !suggestions.contains(e.target)) {
                    suggestions.innerHTML = '';
                }
            });
        } catch (e) {
            console.error('Autocomplete error:', e);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wireOrgAutocomplete);
    } else {
        wireOrgAutocomplete();
    }
})();
</script>
