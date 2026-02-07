<?php
if (!isset($ticketView) || !is_array($ticketView)) return;
 $t = $ticketView;
$tid = (int) $t['id'];
$entries = $t['thread_entries'] ?? [];
$countPublic = count(array_filter($entries, function ($e) { return (int)($e['is_internal'] ?? 0) === 0; }));

$printCompanyName = trim((string)getAppSetting('company.name', ''));
if ($printCompanyName === '') $printCompanyName = (string)APP_NAME;
$printCompanyWebsite = trim((string)getAppSetting('company.website', ''));
if ($printCompanyWebsite === '') $printCompanyWebsite = (string)APP_URL;
$printLogoUrl = (string)getCompanyLogoUrl('publico/img/vigitec-logo.png');
?>

<div class="ticket-view-wrap">
    <div id="assign-loading" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.45); z-index: 2000;">
        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:14px; padding:16px 18px; border:1px solid #e2e8f0; box-shadow:0 16px 40px rgba(0,0,0,0.25); min-width: 220px; text-align:center;">
            <div class="spinner-border text-primary" role="status" style="width:2.25rem; height:2.25rem;"></div>
            <div style="margin-top:10px; font-weight:800; color:#0f172a;">Asignando…</div>
            <div style="margin-top:4px; color:#64748b; font-size:0.9rem;">Enviando notificación</div>
        </div>
    </div>
    <header class="ticket-view-header">
        <h1 class="ticket-view-title">
            <a href="tickets.php?id=<?php echo $tid; ?>" title="Recargar">
                <i class="bi bi-arrow-clockwise"></i>
            </a>
            Ticket #<?php echo html($t['ticket_number']); ?>
        </h1>
        <div class="ticket-view-actions">
            <a href="users.php?id=<?php echo (int)$t['user_id']; ?>" class="btn-icon" title="Volver al usuario"><i class="bi bi-arrow-left"></i></a>
            <a href="users.php?id=<?php echo (int)$t['user_id']; ?>" class="btn-icon" title="Guardar"><i class="bi bi-save"></i></a>
            <div class="dropdown d-inline-block">
                <button class="btn-icon dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Estado"><i class="bi bi-flag"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php
                    $st = $mysqli->query("SELECT id, name FROM ticket_status ORDER BY order_by, id");
                    while ($row = $st->fetch_assoc()): ?>
                        <li><a class="dropdown-item <?php echo (int)$row['id'] === (int)$t['status_id'] ? 'active' : ''; ?>" href="tickets.php?id=<?php echo $tid; ?>&action=status&status_id=<?php echo (int)$row['id']; ?>"><?php echo html($row['name']); ?></a></li>
                    <?php endwhile; ?>
                </ul>
            </div>
            <div class="dropdown d-inline-block">
                <button class="btn-icon dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Asignar"><i class="bi bi-person"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item <?php echo empty($t['staff_id']) ? 'active' : ''; ?>" href="tickets.php?id=<?php echo $tid; ?>&action=assign&staff_id=0">— Sin asignar —</a></li>
                    <?php
                    $tdept = (int) ($t['dept_id'] ?? 0);
                    $gd = isset($generalDeptId) ? (int) $generalDeptId : 0;

                    // Regla: General NO es comodín. Solo listar agentes del mismo dept_id.
                    // Ticket General => solo agentes General.
                    // Ticket de otro dept => solo agentes de ese dept.
                    if ($tdept > 0) {
                        if ($gd > 0) {
                            $st = $mysqli->query(
                                "SELECT id, firstname, lastname FROM staff "
                                . "WHERE is_active = 1 "
                                . "AND COALESCE(NULLIF(dept_id, 0), $gd) = $tdept "
                                . "ORDER BY firstname, lastname"
                            );
                        } else {
                            $st = $mysqli->query(
                                "SELECT id, firstname, lastname FROM staff "
                                . "WHERE is_active = 1 AND dept_id = $tdept "
                                . "ORDER BY firstname, lastname"
                            );
                        }
                    } else {
                        $st = $mysqli->query("SELECT id, firstname, lastname FROM staff WHERE is_active = 1 ORDER BY firstname, lastname");
                    }

                    while ($st && $row = $st->fetch_assoc()): ?>
                        <li><a class="dropdown-item <?php echo (int)$row['id'] === (int)($t['staff_id'] ?? 0) ? 'active' : ''; ?>" href="tickets.php?id=<?php echo $tid; ?>&action=assign&staff_id=<?php echo (int)$row['id']; ?>"><?php echo html(trim($row['firstname'] . ' ' . $row['lastname'])); ?></a></li>
                    <?php endwhile; ?>
                </ul>
            </div>
            <button class="btn-icon" title="Transferir" type="button" data-bs-toggle="modal" data-bs-target="#modalTransfer"><i class="bi bi-arrow-left-right"></i></button>
            <button class="btn-icon" title="Imprimir" type="button" data-action="print"><i class="bi bi-printer"></i></button>
            <div class="dropdown d-inline-block">
                <button class="btn-icon dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Configuración"><i class="bi bi-gear"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalOwner"><i class="bi bi-person-badge me-2"></i>Cambiar Propietario</a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalMerge"><i class="bi bi-link-45deg me-2"></i>Unir Tiquetes</a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalLinked"><i class="bi bi-link me-2"></i>Tickets vinculados</a></li>
                    <li><a class="dropdown-item" href="tickets.php?id=<?php echo $tid; ?>&action=mark_answered"><i class="bi bi-check-circle me-2"></i>Marcar como contestados</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-share me-2"></i>Administrar referidos</a></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-file-text me-2"></i>Gestionar formularios</a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalCollaborators"><i class="bi bi-people me-2"></i>Gestionar Colaboradores</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#modalBlockEmail"><i class="bi bi-envelope-x me-2"></i>Bloquear Email &lt;<?php echo html($t['user_email']); ?>&gt;</a></li>
                    <li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#modalDelete"><i class="bi bi-trash me-2"></i>Borrar Ticket</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Modales: Propietario, Transferir, Unir, Vinculados, Colaboradores, Bloquear, Borrar -->
    <div class="modal fade" id="modalOwner" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="tickets.php?id=<?php echo $tid; ?>">
                    <input type="hidden" name="action" value="owner">
                    <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="modal-header"><h5 class="modal-title">Cambiar Propietario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <label class="form-label">Nuevo propietario (usuario)</label>
                        <select name="user_id" class="form-select" required>
                            <?php
                            $users = $mysqli->query("SELECT id, firstname, lastname, email FROM users ORDER BY firstname, lastname");
                            while ($u = $users->fetch_assoc()): ?>
                                <option value="<?php echo (int)$u['id']; ?>" <?php echo (int)$u['id'] === (int)$t['user_id'] ? 'selected' : ''; ?>><?php echo html(trim($u['firstname'].' '.$u['lastname']).' ('.$u['email'].')'); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Cambiar</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalTransfer" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="tickets.php?id=<?php echo $tid; ?>">
                    <input type="hidden" name="action" value="transfer">
                    <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="modal-header"><h5 class="modal-title">Transferir Ticket</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p class="text-muted small mb-2">Mueve este ticket a otro departamento. Si el agente asignado no pertenece al nuevo departamento, el ticket quedará sin asignar.</p>
                        <label class="form-label">Nuevo departamento</label>
                        <select name="dept_id" class="form-select" required>
                            <?php
                            $depts = $mysqli->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
                            while ($depts && $d = $depts->fetch_assoc()):
                            ?>
                                <option value="<?php echo (int)$d['id']; ?>" <?php echo (int)$d['id'] === (int)($t['dept_id'] ?? 0) ? 'selected' : ''; ?>><?php echo html($d['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Transferir</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalMerge" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="tickets.php?id=<?php echo $tid; ?>">
                    <input type="hidden" name="action" value="merge">
                    <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="modal-header"><h5 class="modal-title">Unir Tiquetes</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p class="text-muted small">Este ticket se unirá al ticket destino (todas las entradas se copiarán y este ticket se cerrará).</p>
                        <label class="form-label">Ticket destino (ID o número)</label>
                        <input type="text" name="target_ticket_id" class="form-control" placeholder="Ej: 5 o TKT-20250126-0001" required>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-warning">Unir y cerrar este ticket</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalLinked" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Tickets vinculados</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <?php $linked = $t['linked_tickets'] ?? []; ?>
                    <?php if (empty($linked)): ?>
                        <p class="text-muted mb-3">No hay tickets vinculados.</p>
                    <?php else: ?>
                        <ul class="list-group mb-3">
                            <?php foreach ($linked as $lt): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <a href="tickets.php?id=<?php echo (int)$lt['id']; ?>">#<?php echo html($lt['ticket_number']); ?> — <?php echo html($lt['subject']); ?></a>
                                    <a href="tickets.php?id=<?php echo $tid; ?>&action=unlink&linked_id=<?php echo (int)$lt['id']; ?>" class="btn btn-sm btn-outline-danger">Quitar</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <form method="post" action="tickets.php?id=<?php echo $tid; ?>" class="mt-2">
                        <input type="hidden" name="action" value="link">
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                        <div class="input-group">
                            <input type="number" name="linked_ticket_id" class="form-control" placeholder="ID del ticket a vincular" min="1" required>
                            <button type="submit" class="btn btn-primary">Vincular</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalCollaborators" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Gestionar Colaboradores</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <?php $collabs = $t['collaborators'] ?? []; ?>
                    <?php if (!empty($collabs)): ?>
                        <ul class="list-group mb-3">
                            <?php foreach ($collabs as $c): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo html(trim($c['firstname'].' '.$c['lastname']).' ('.$c['email'].')'); ?>
                                    <a href="tickets.php?id=<?php echo $tid; ?>&action=collab_remove&user_id=<?php echo (int)$c['user_id']; ?>" class="btn btn-sm btn-outline-danger">Quitar</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <form method="post" action="tickets.php?id=<?php echo $tid; ?>">
                        <input type="hidden" name="action" value="collab_add">
                        <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                        <div class="input-group">
                            <select name="user_id" class="form-select" required>
                                <option value="">— Añadir usuario —</option>
                                <?php
                                $users = $mysqli->query("SELECT id, firstname, lastname, email FROM users ORDER BY firstname, lastname");
                                while ($u = $users->fetch_assoc()):
                                    if ((int)$u['id'] === (int)$t['user_id']) continue;
                                    $inCollab = false;
                                    foreach ($collabs as $c) { if ((int)$c['user_id'] === (int)$u['id']) { $inCollab = true; break; } }
                                    if ($inCollab) continue;
                                ?>
                                    <option value="<?php echo (int)$u['id']; ?>"><?php echo html(trim($u['firstname'].' '.$u['lastname']).' ('.$u['email'].')'); ?></option>
                                <?php endwhile; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">Añadir</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalBlockEmail" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="tickets.php?id=<?php echo $tid; ?>">
                    <input type="hidden" name="action" value="block_email">
                    <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="modal-header"><h5 class="modal-title">Bloquear Email</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p>¿Bloquear el email <strong><?php echo html($t['user_email']); ?></strong>? El usuario no podrá iniciar sesión ni crear tickets.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Bloquear</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalDelete" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="tickets.php?id=<?php echo $tid; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="confirm" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="modal-header"><h5 class="modal-title">Borrar Ticket</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p class="text-danger">¿Eliminar este ticket y todo su historial? Esta acción no se puede deshacer.</p>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Borrar Ticket</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- Resumen del ticket -->
    <div class="ticket-view-overview">
        <div>
            <div class="field">
                <label>Estado</label>
                <div class="value">
                    <span class="badge-status" style="background: <?php echo html($t['status_color'] ?? '#e2e8f0'); ?>; color: #0f172a;"><?php echo html($t['status_name']); ?></span>
                </div>
            </div>
            <div class="field">
                <label>Prioridad</label>
                <div class="value"><?php echo html($t['priority_name']); ?></div>
            </div>
            <div class="field">
                <label>Departamento</label>
                <div class="value"><?php echo html($t['dept_name']); ?></div>
            </div>
            <div class="field">
                <label>Creado en</label>
                <div class="value"><?php echo $t['created'] ? date('m/d/y H:i:s', strtotime($t['created'])) : '—'; ?></div>
            </div>
        </div>
        <div>
            <div class="field">
                <label>Usuario</label>
                <div class="value">
                    <a href="users.php?id=<?php echo (int)$t['user_id']; ?>"><?php echo html($t['user_name']); ?> (<?php echo (int)$t['user_id']; ?>)</a>
                </div>
            </div>
            <div class="field">
                <label>Email</label>
                <div class="value"><?php echo html($t['user_email']); ?></div>
            </div>
            <div class="field">
                <label>Fuente</label>
                <div class="value">Web</div>
            </div>
        </div>
        <div>
            <div class="field">
                <label>Asignado a</label>
                <div class="value"><?php echo html($t['staff_name']); ?></div>
            </div>
            <div class="field">
                <label>Último mensaje</label>
                <div class="value"><?php echo $t['last_message'] ? date('m/d/y H:i:s', strtotime($t['last_message'])) : '—'; ?></div>
            </div>
            <div class="field">
                <label>Última respuesta</label>
                <div class="value"><?php echo $t['last_response'] ? date('m/d/y H:i:s', strtotime($t['last_response'])) : '—'; ?></div>
            </div>
        </div>
    </div>

    <!-- Pestañas: Hilo del ticket / Tareas -->
    <ul class="ticket-view-tabs" role="tablist">
        <li><a class="tab active" href="#thread"><i class="bi bi-chat-left-text"></i> Hilo del Ticket (<?php echo $countPublic; ?>)</a></li>
        <li><a class="tab" href="#tasks"><i class="bi bi-check2-square"></i> Tareas</a></li>
    </ul>

    <div class="ticket-view-tab-content" id="thread" data-print-area="thread">
        <div class="ticket-print-header">
            <div class="tph-left">
                <?php if ($printLogoUrl !== ''): ?>
                    <div class="tph-logo"><img src="<?php echo html($printLogoUrl); ?>" alt="<?php echo html($printCompanyName); ?>"></div>
                <?php endif; ?>
                <div class="tph-brand">
                    <div class="tph-company"><?php echo html($printCompanyName); ?></div>
                    <div class="tph-website"><?php echo html($printCompanyWebsite); ?></div>
                </div>
            </div>
            <div class="tph-right">
                <div class="tph-ticket">Ticket <?php echo html($t['ticket_number']); ?></div>
                <div class="tph-subject"><?php echo html((string)($t['subject'] ?? '')); ?></div>
                <div class="tph-meta">
                    <?php echo html((string)($t['user_name'] ?? '')); ?> · <?php echo html((string)($t['user_email'] ?? '')); ?>
                </div>
                <div class="tph-meta">
                    Impreso: <?php echo date('d/m/Y H:i'); ?>
                </div>
            </div>
        </div>
        <?php
        $msg = $_GET['msg'] ?? '';
        $msgText = ['reply_sent' => 'Respuesta publicada correctamente.', 'created' => 'Ticket creado correctamente.', 'updated' => 'Estado actualizado.', 'assigned' => 'Asignación actualizada.', 'marked' => 'Marcado como contestado.', 'owner' => 'Propietario cambiado.', 'transferred' => 'Ticket transferido correctamente.', 'blocked' => 'Email bloqueado.', 'linked' => 'Ticket vinculado.', 'unlinked' => 'Vinculación eliminada.', 'collab_added' => 'Colaborador añadido.', 'collab_removed' => 'Colaborador quitado.', 'merged' => 'Tickets unidos correctamente.'];
        if ($msg && isset($msgText[$msg])): ?>
            <div class="alert alert-success alert-dismissible fade show"><?php echo html($msgText[$msg]); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <script>
          (function () {
            try {
              var url = new URL(window.location.href);
              if (url.searchParams.has('msg')) {
                url.searchParams.delete('msg');
                window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : '') + url.hash);
              }
            } catch (e) {}

            var cleanupModals = function () {
              try {
                document.querySelectorAll('.modal.show').forEach(function (el) {
                  if (window.bootstrap && window.bootstrap.Modal) {
                    var inst = window.bootstrap.Modal.getInstance(el);
                    if (inst) inst.hide();
                  }
                  el.classList.remove('show');
                  el.style.display = 'none';
                  el.setAttribute('aria-hidden', 'true');
                });
                document.querySelectorAll('.modal-backdrop').forEach(function (b) { b.remove(); });
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('padding-right');
              } catch (e2) {}
            };

            window.addEventListener('pageshow', function (ev) {
              if (ev && ev.persisted) {
                cleanupModals();
              }
            });
          })();
        </script>

        <?php if (!empty($reply_errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo implode(' ', array_map('htmlspecialchars', $reply_errors)); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($entries)): ?>
            <p class="text-muted mb-0">Aún no hay mensajes en el hilo.</p>
        <?php else: ?>
            <?php foreach ($entries as $e): ?>
                <?php
                $isInternal = (int)($e['is_internal'] ?? 0) === 1;
                $isStaff = !empty($e['staff_id']);
                $author = $isStaff
                    ? (trim($e['staff_first'] . ' ' . $e['staff_last']) ?: 'Agente')
                    : (trim($e['user_first'] . ' ' . $e['user_last']) ?: 'Usuario');
                $cssClass = $isInternal ? 'internal' : ($isStaff ? 'staff' : 'user');
                $initials = '';
                $parts = preg_split('/\s+/', trim($author));
                $sub1 = function ($str) {
                    if ($str === null) return '';
                    $str = (string) $str;
                    if ($str === '') return '';
                    return function_exists('mb_substr') ? mb_substr($str, 0, 1) : substr($str, 0, 1);
                };
                if (!empty($parts[0])) $initials .= $sub1($parts[0]);
                if (!empty($parts[1])) $initials .= $sub1($parts[1]);
                $initials = strtoupper($initials ?: 'U');
                ?>
                <div class="ticket-view-entry <?php echo $cssClass; ?>">
                    <div class="entry-row">
                        <div class="entry-avatar" aria-hidden="true">
                            <span class="entry-avatar-inner"><?php echo html($initials); ?></span>
                        </div>
                        <div class="entry-content">
                            <div class="entry-meta">
                                <span class="author"><?php echo html($author); ?></span>
                                <span><?php echo $e['created'] ? date('m/d/y H:i:s', strtotime($e['created'])) : ''; ?></span>
                            </div>
                            <div class="entry-body"><?php
                                echo sanitizeRichText((string)($e['body'] ?? ''));
                            ?></div>

                            <?php $eid = (int) ($e['id'] ?? 0); ?>
                            <?php if (!empty($attachmentsByEntry[$eid])): ?>
                                <div class="att-list">
                                    <?php foreach ($attachmentsByEntry[$eid] as $a): ?>
                                        <div class="att-item">
                                            <div>
                                                <i class="bi bi-paperclip"></i>
                                                <a href="tickets.php?id=<?php echo (int)$tid; ?>&download=<?php echo (int)$a['id']; ?>"><?php echo html($a['original_filename'] ?? 'archivo'); ?></a>
                                            </div>
                                            <div class="size"><?php echo isset($a['size']) ? number_format((int)$a['size'] / 1024, 0) . ' KB' : ''; ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="entry-footer">
                        Creado por <?php echo html($author); ?> <?php echo $e['created'] ? date('m/d/y H:i:s', strtotime($e['created'])) : ''; ?>
                        <?php if ($isInternal): ?> <span class="badge bg-warning text-dark">Nota interna</span><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($ticket_closed_info)): ?>
            <div class="ticket-closed-banner">
                <div class="ticket-closed-icon"><i class="bi bi-hand-thumbs-up"></i></div>
                <div class="ticket-closed-text">
                    Cerrado por
                    <span class="ticket-closed-avatar" aria-hidden="true"><i class="bi bi-person-fill"></i></span>
                    <strong><?php echo html($ticket_closed_info['by'] ?? 'Agente'); ?></strong>
                    con el estado de
                    <strong><?php echo html($ticket_closed_info['status'] ?? 'Cerrado'); ?></strong>
                    <?php echo !empty($ticket_closed_info['at']) ? date('d/m/y H:i', strtotime($ticket_closed_info['at'])) : ''; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Responder -->
    <div class="ticket-view-reply">
        <form method="post" action="tickets.php?id=<?php echo $tid; ?>" enctype="multipart/form-data" id="form-reply">
            <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
            <div class="mb-3">
                <label class="form-label fw-bold">Respuesta</label>
                <textarea name="body" id="reply_body" class="form-control" placeholder="Empezar escribiendo su respuesta aquí. Usa respuestas predefinidas del menú desplegable de arriba si lo desea."></textarea>
            </div>
            <div class="attach-zone" id="attach-zone" data-action="attachments-browse">
                <input type="file" name="attachments[]" id="attachments" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.txt">
                <div class="attach-text">Agregar archivos aquí o <a href="#" data-action="attachments-browse">elegirlos</a></div>
                <div class="attach-list" id="attach-list"></div>
            </div>
            <div class="reply-options">
                <div class="opt-group">
                    <label>Firma:</label>
                    <label class="me-3"><input type="radio" name="signature" value="none" class="form-radio" <?php echo empty($staff_has_signature) ? 'checked' : ''; ?>> Ninguno</label>
                    <?php if (!empty($staff_has_signature)): ?>
                        <label class="me-3"><input type="radio" name="signature" value="staff" class="form-radio" checked> Mi firma</label>
                    <?php endif; ?>
                    <label><input type="radio" name="signature" value="dept" class="form-radio"> Firma del Departamento (<?php echo html($t['dept_name'] ?? 'Soporte'); ?>)</label>
                </div>
                <div class="opt-group">
                    <label>Estado del Ticket:</label>
                    <select name="status_id" class="form-select form-control">
                        <?php
                        $statusList = $ticket_status_list ?? [];
                        foreach ($statusList as $st): ?>
                            <option value="<?php echo (int)$st['id']; ?>" <?php echo (int)$st['id'] === (int)$t['status_id'] ? 'selected' : ''; ?>><?php echo html($st['name']); ?><?php echo (int)$st['id'] === (int)$t['status_id'] ? ' (actual)' : ''; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="reply-buttons">
                <button type="submit" name="do" value="reply" class="btn btn-reply btn-publish">
                    <i class="bi bi-send"></i> Publicar Respuesta
                </button>
                <button type="submit" name="do" value="internal" class="btn btn-reply btn-internal">
                    <i class="bi bi-lock"></i> publicar nota interna
                </button>
                <button type="button" class="btn btn-reset" id="btn-reset">Restablecer</button>
            </div>
            <div class="reply-from">
                <strong>De:</strong> <?php echo html($staff['name'] ?? 'Agente'); ?> &lt;<?php echo html($staff['email'] ?? ''); ?>&gt;<br>
                <strong>Destinatarios:</strong> <?php echo html($t['user_name']); ?> &lt;<?php echo html($t['user_email']); ?>&gt;
            </div>
        </form>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-es-ES.min.js"></script>
<style>
.note-editor .note-editable {
    position: relative;
}
.ticket-view-actions .dropdown-item.is-loading {
    opacity: 0.6;
    pointer-events: none;
}
.note-editor.signature-preview-on .note-editable {
    padding-bottom: 160px;
}
.note-editor.signature-preview-on .note-editable::after {
    content: attr(data-signature);
    white-space: pre-line;
    position: absolute;
    left: 12px;
    right: 12px;
    bottom: 10px;
    color: #6b7280;
    font-weight: 600;
    font-size: 12px;
    line-height: 1.4;
    border-top: 1px dashed #e5e7eb;
    padding-top: 12px;
    pointer-events: none;
    opacity: 0.95;
}

.ticket-view-entry .entry-body img { max-width: 100%; height: auto; display: block; }
.ticket-view-entry .entry-body iframe { max-width: 100%; width: 100%; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var staffHasSignature = <?php echo !empty($staff_has_signature) ? 'true' : 'false'; ?>;
    var staffSignatureText = <?php echo json_encode((string)($staff_signature ?? '')); ?>;

    var assignOverlay = document.getElementById('assign-loading');
    function showAssignOverlay() {
        if (assignOverlay) assignOverlay.style.display = 'block';
    }
    var assignLinks = document.querySelectorAll('.ticket-view-actions a[href*="action=assign"]');
    if (assignLinks && assignLinks.length) {
        assignLinks.forEach(function (a) {
            a.addEventListener('click', function () {
                try { this.classList.add('is-loading'); } catch (e) {}
                showAssignOverlay();
            });
        });
    }

    function setSignaturePreview(enabled) {
        var editor = document.querySelector('.note-editor');
        var editable = document.querySelector('.note-editor .note-editable');
        if (!editor || !editable) return;
        if (enabled && staffHasSignature) {
            editor.classList.add('signature-preview-on');
            editable.setAttribute('data-signature', staffSignatureText);
            var lines = (staffSignatureText || '').split(/\r?\n/).length;
            var pad = 70 + (lines * 18);
            if (pad < 160) pad = 160;
            if (pad > 360) pad = 360;
            editable.style.paddingBottom = pad + 'px';
        } else {
            editor.classList.remove('signature-preview-on');
            editable.removeAttribute('data-signature');
            editable.style.paddingBottom = '';
        }
    }

    if (typeof jQuery !== 'undefined' && jQuery().summernote) {
        jQuery('#reply_body').summernote({
            height: 260,
            lang: 'es-ES',
            placeholder: 'Empezar escribiendo su respuesta aquí. Usa respuestas predefinidas del menú desplegable de arriba si lo desea.',
            toolbar: [
                ['style', ['style', 'paragraph']],
                ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
                ['fontname', ['fontname']],
                ['color', ['color']],
                ['fontsize', ['fontsize']],
                ['insert', ['link', 'picture', 'video', 'table', 'hr']],
                ['view', ['codeview', 'fullscreen']],
                ['para', ['ul', 'ol', 'paragraph']]
            ],
            fontNames: ['Arial', 'Arial Black', 'Comic Sans MS', 'Courier New', 'Helvetica', 'Impact', 'Tahoma', 'Times New Roman', 'Verdana'],
            fontSizes: ['8', '9', '10', '11', '12', '14', '16', '18', '24', '36']
        });

        // Aplicar previsualización inicial según radio seleccionado
        setTimeout(function () {
            var checked = document.querySelector('input[name="signature"]:checked');
            setSignaturePreview(checked && checked.value === 'staff');
        }, 0);
    }

    // Toggle de previsualización al cambiar la opción de firma
    var sigRadios = document.querySelectorAll('input[name="signature"]');
    if (sigRadios && sigRadios.length) {
        sigRadios.forEach(function (r) {
            r.addEventListener('change', function () {
                setSignaturePreview(this.value === 'staff');
            });
        });
    }

    var zone = document.getElementById('attach-zone');
    var input = document.getElementById('attachments');
    var list = document.getElementById('attach-list');
    function updateList() {
        list.innerHTML = '';
        if (input.files.length) {
            for (var i = 0; i < input.files.length; i++) {
                list.innerHTML += '<span class="d-inline-block me-2 mb-1"><i class="bi bi-paperclip"></i> ' + input.files[i].name + '</span> ';
            }
        }
    }
    input.addEventListener('change', updateList);
    zone.addEventListener('dragover', function(e) { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', function() { zone.classList.remove('dragover'); });
    zone.addEventListener('drop', function(e) {
        e.preventDefault();
        zone.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            updateList();
        }
    });
    document.getElementById('btn-reset').addEventListener('click', function() {
        if (typeof jQuery !== 'undefined' && jQuery('#reply_body').length && jQuery('#reply_body').summernote('code')) {
            jQuery('#reply_body').summernote('reset');
        }
        input.value = '';
        list.innerHTML = '';
    });
});
</script>
