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

$backUrl = '';
if (isset($_GET['back'])) {
    $candidate = trim((string)$_GET['back']);
    if ($candidate !== '') {
        // Permitir 'tickets.php?...' o una URL/ruta completa que contenga '/upload/scp/'
        $path = (string)parse_url($candidate, PHP_URL_PATH);
        if ($path === '') {
            $path = $candidate;
        }
        $path = ltrim(str_replace('\\', '/', $path), '/');

        $needle = 'upload/scp/';
        $pos = strpos($path, $needle);
        if ($pos !== false) {
            $path = substr($path, $pos + strlen($needle));
        }
        $path = trim($path);

        $query = (string)parse_url($candidate, PHP_URL_QUERY);
        $rel = $path . ($query !== '' ? ('?' . $query) : '');
        $rel = trim($rel);
        if ($rel !== '') {
            $backUrl = (string)toAppAbsoluteUrl('upload/scp/' . $rel);
        }
    }
}

if ($backUrl === '') {
    $ref = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    if ($ref !== '') {
        $appBase = rtrim((string)(defined('APP_URL') ? APP_URL : ''), '/');
        if ($appBase !== '' && str_starts_with($ref, $appBase)) {
            $refPath = (string)parse_url($ref, PHP_URL_PATH);
            $refQuery = (string)parse_url($ref, PHP_URL_QUERY);
            $refRel = ltrim($refPath, '/');
            if (str_starts_with($refRel, 'upload/scp/')) {
                $candidate = substr($refRel, strlen('upload/scp/'));
                if ($refQuery !== '') {
                    $candidate .= '?' . $refQuery;
                }
                $candidate = trim($candidate);
                if ($candidate !== '' && !str_starts_with($candidate, 'tickets.php?id=' . (string)$tid)) {
                    $backUrl = (string)toAppAbsoluteUrl('upload/scp/' . $candidate);
                }
            }
        }
    }
}

$backUrlFinal = ($backUrl !== '' ? $backUrl : 'tickets.php');

$ticketClientSignatureUrl = '';
$ticketClientSignaturePath = trim((string)($t['client_signature'] ?? ''));
if ($ticketClientSignaturePath !== '') {
    $projectRoot = realpath(dirname(__DIR__, 3));
    $sigPath = ltrim(str_replace('\\', '/', $ticketClientSignaturePath), '/');
    if ($projectRoot !== false && str_starts_with($sigPath, 'firmas/')) {
        $fullSigPath = $projectRoot . '/' . $sigPath;
        if (is_file($fullSigPath)) {
            $ticketClientSignatureUrl = toAppAbsoluteUrl($sigPath) . '?v=' . (string)@filemtime($fullSigPath);
        }
    }
}
?>

<div class="ticket-view-wrap">
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'closed_report'): ?>
        <style>
            @keyframes tvIn {
                from { opacity: 0; transform: translate(-50%, -16px); }
                to   { opacity: 1; transform: translate(-50%, 0); }
            }
            #tv-billing-toast {
                position: fixed;
                top: 74px;
                left: 50%;
                transform: translateX(-50%);
                z-index: 9999;
                width: min(520px, 96vw);
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                animation: tvIn 0.38s cubic-bezier(.22, 1, .36, 1) both;
                pointer-events: auto;
            }
            #tv-billing-toast .tvb-card {
                background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 55%, #0ea5e9 100%);
                border-radius: 10px;
                box-shadow: 0 12px 40px rgba(29, 78, 216, 0.35), 0 2px 8px rgba(0, 0, 0, 0.14);
                overflow: hidden;
            }
            #tv-billing-toast .tvb-row {
                display: flex;
                align-items: center;
                gap: 14px;
                padding: 14px 16px;
            }
            #tv-billing-toast .tvb-dot {
                width: 8px;
                height: 8px;
                min-width: 8px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.9);
                box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
            }
            #tv-billing-toast .tvb-text {
                flex: 1;
                min-width: 0;
            }
            #tv-billing-toast .tvb-ticket-ref {
                display: inline-block;
                font-size: 0.7rem;
                font-weight: 700;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                color: rgba(255, 255, 255, 0.7);
                margin-bottom: 2px;
            }
            #tv-billing-toast .tvb-msg {
                font-size: 0.88rem;
                font-weight: 600;
                color: rgba(255, 255, 255, 0.92);
                line-height: 1.3;
                margin: 0;
            }
            #tv-billing-toast .tvb-msg strong {
                color: #ffffff;
                font-weight: 800;
            }
            #tv-billing-toast .tvb-actions {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-shrink: 0;
            }
            #tv-billing-toast .tvb-btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: rgba(255, 255, 255, 0.18);
                color: #fff;
                font-size: 0.78rem;
                font-weight: 700;
                padding: 6px 14px;
                border-radius: 6px;
                text-decoration: none;
                border: 1px solid rgba(255, 255, 255, 0.28);
                cursor: pointer;
                transition: background 0.15s;
                white-space: nowrap;
                letter-spacing: 0.01em;
            }
            #tv-billing-toast .tvb-btn:hover { background: rgba(255, 255, 255, 0.28); color: #fff; }
            #tv-billing-toast .tvb-dismiss {
                background: none;
                border: none;
                color: rgba(255, 255, 255, 0.55);
                cursor: pointer;
                padding: 4px;
                line-height: 1;
                display: flex;
                align-items: center;
                transition: color 0.15s;
            }
            #tv-billing-toast .tvb-dismiss:hover { color: rgba(255, 255, 255, 0.9); }
            #tv-billing-toast .tvb-bar {
                height: 2px;
                background: rgba(255, 255, 255, 0.12);
                position: relative;
                overflow: hidden;
            }
            #tv-billing-toast .tvb-bar-fill {
                position: absolute;
                inset: 0;
                background: rgba(255, 255, 255, 0.5);
                transform-origin: left;
                animation: tvBarShrink 12s linear forwards;
            }
            @keyframes tvBarShrink {
                from { transform: scaleX(1); }
                to   { transform: scaleX(0); }
            }
            @media (max-width: 520px) {
                #tv-billing-toast .tvb-row {
                    flex-wrap: wrap;
                    gap: 10px;
                }
                #tv-billing-toast .tvb-text {
                    flex: 1 1 100%;
                }
                #tv-billing-toast .tvb-actions {
                    margin-left: 22px;
                }
            }
        </style>

        <div id="tv-billing-toast" role="alert" aria-live="assertive">
            <div class="tvb-card">
                <div class="tvb-row">
                    <div class="tvb-dot"></div>
                    <div class="tvb-text">
                        <span class="tvb-ticket-ref">Ticket #<?php echo html($t['ticket_number'] ?? $tid); ?></span>
                        <p class="tvb-msg">Cerrado — <strong>registra el reporte de costos</strong></p>
                    </div>
                    <div class="tvb-actions">
                        <a href="reporte_costos.php?ticket_id=<?php echo (int)$tid; ?>" class="tvb-btn">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>
                            </svg>
                            Registrar
                        </a>
                        <button class="tvb-dismiss" onclick="tvBillingDismiss()" title="Cerrar" aria-label="Cerrar">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="tvb-bar"><div class="tvb-bar-fill"></div></div>
            </div>
        </div>
        <script>
            function tvBillingDismiss() {
                var el = document.getElementById('tv-billing-toast');
                if (!el) return;
                el.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                el.style.opacity = '0';
                el.style.transform = 'translateX(-50%) translateY(-10px)';
                setTimeout(function() { if (el && el.parentNode) el.parentNode.removeChild(el); }, 320);
            }
            setTimeout(tvBillingDismiss, 12000);
        </script>
    <?php endif; ?>

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
        <?php
        $canTicketEdit = roleHasPermission('ticket.edit');
        $canTicketClose = roleHasPermission('ticket.close');
        $canTicketAssign = roleHasPermission('ticket.assign');
        $canTicketTransfer = roleHasPermission('ticket.transfer');
        $canTicketMerge = roleHasPermission('ticket.merge');
        $canTicketLink = roleHasPermission('ticket.link');
        $canTicketMark = roleHasPermission('ticket.markanswered');
        $canTicketDelete = roleHasPermission('ticket.delete');
        $canTicketPost = roleHasPermission('ticket.post');
        $canTicketReply = roleHasPermission('ticket.reply');
        ?>

        <div class="ticket-view-actions">
            <a href="<?php echo html($backUrlFinal); ?>" class="btn-icon" title="Volver"><i class="bi bi-arrow-left"></i></a>
            <a href="users.php?id=<?php echo (int)$t['user_id']; ?>" class="btn-icon" title="Guardar"><i class="bi bi-save"></i></a>
            <div class="dropdown d-inline-block">
                <button class="btn-icon dropdown-toggle" type="button" data-bs-toggle="dropdown" title="<?php echo ($canTicketEdit || $canTicketClose) ? 'Estado' : 'Sin permiso'; ?>" <?php echo ($canTicketEdit || $canTicketClose) ? '' : 'disabled'; ?>><i class="bi bi-flag"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php
                    $st = $mysqli->query("SELECT id, name FROM ticket_status ORDER BY order_by, id");
                    while ($row = $st->fetch_assoc()): ?>
                        <?php
                        $stName = strtolower(trim((string)($row['name'] ?? '')));
                        $isClosing = ($stName !== '' && (str_contains($stName, 'cerrad') || str_contains($stName, 'resuelt') || str_contains($stName, 'closed') || str_contains($stName, 'resolved')));
                        $allowed = $isClosing ? $canTicketClose : $canTicketEdit;
                        ?>
                        <li>
                            <a class="dropdown-item <?php echo (int)$row['id'] === (int)$t['status_id'] ? 'active' : ''; ?> <?php echo $allowed ? '' : 'disabled'; ?> <?php echo ($allowed && $isClosing) ? 'js-status-close' : ''; ?>"
                               <?php
                               if ($allowed && $isClosing) {
                                   echo 'href="#" data-close-status-id="' . (int)$row['id'] . '" data-close-status-name="' . html($row['name']) . '"';
                               } elseif ($allowed) {
                                   echo 'href="tickets.php?id=' . $tid . '&action=status&status_id=' . (int)$row['id'] . '"';
                               } else {
                                   echo 'href="#" tabindex="-1" aria-disabled="true"';
                               }
                               ?>>
                                <?php echo html($row['name']); ?>
                            </a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>
            <div class="dropdown d-inline-block">
                <button class="btn-icon dropdown-toggle" type="button" data-bs-toggle="dropdown" title="<?php echo $canTicketAssign ? 'Asignar' : 'Sin permiso'; ?>" <?php echo $canTicketAssign ? '' : 'disabled'; ?>><i class="bi bi-person"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item <?php echo empty($t['staff_id']) ? 'active' : ''; ?>" href="tickets.php?id=<?php echo $tid; ?>&action=assign&staff_id=0">— Sin asignar —</a></li>
                    <?php
                    $tdept = (int) ($t['dept_id'] ?? 0);
                    $gd = isset($generalDeptId) ? (int) $generalDeptId : 0;
                    $empresaId = function_exists('empresaId') ? (int)empresaId() : (int)($_SESSION['empresa_id'] ?? 0);
                    $st = null;

                    // Check if staff_departments table exists
                    $hasStaffDepartmentsTable = false;
                    if (isset($mysqli) && $mysqli) {
                        try {
                            $rt = $mysqli->query("SHOW TABLES LIKE 'staff_departments'");
                            $hasStaffDepartmentsTable = ($rt && $rt->num_rows > 0);
                        } catch (Throwable $e) {
                            $hasStaffDepartmentsTable = false;
                        }
                    }

                    // Regla: General NO es comodín. Solo listar agentes del mismo dept_id.
                    // Ticket General => solo agentes General.
                    // Ticket de otro dept => solo agentes de ese dept.
                    if ($tdept > 0) {
                        if ($hasStaffDepartmentsTable) {
                            // New model: use staff_departments for multi-department support
                            $stmtSt = $mysqli->prepare(
                                "SELECT DISTINCT s.id, s.firstname, s.lastname FROM staff s "
                                . "JOIN staff_departments sd ON sd.staff_id = s.id "
                                . "WHERE s.empresa_id = ? AND s.is_active = 1 AND sd.dept_id = ? "
                                . "ORDER BY s.firstname, s.lastname"
                            );
                            if ($stmtSt) {
                                $stmtSt->bind_param('ii', $empresaId, $tdept);
                                $stmtSt->execute();
                                $st = $stmtSt->get_result();
                            }
                        } elseif ($gd > 0) {
                            // Legacy model with general dept fallback
                            $stmtSt = $mysqli->prepare(
                                "SELECT id, firstname, lastname FROM staff "
                                . "WHERE empresa_id = ? AND is_active = 1 "
                                . "AND COALESCE(NULLIF(dept_id, 0), ?) = ? "
                                . "ORDER BY firstname, lastname"
                            );
                            if ($stmtSt) {
                                $stmtSt->bind_param('iii', $empresaId, $gd, $tdept);
                                $stmtSt->execute();
                                $st = $stmtSt->get_result();
                            }
                        } else {
                            $stmtSt = $mysqli->prepare(
                                "SELECT id, firstname, lastname FROM staff "
                                . "WHERE empresa_id = ? AND is_active = 1 AND dept_id = ? "
                                . "ORDER BY firstname, lastname"
                            );
                            if ($stmtSt) {
                                $stmtSt->bind_param('ii', $empresaId, $tdept);
                                $stmtSt->execute();
                                $st = $stmtSt->get_result();
                            }
                        }
                    } else {
                        $stmtSt = $mysqli->prepare("SELECT id, firstname, lastname FROM staff WHERE empresa_id = ? AND is_active = 1 ORDER BY firstname, lastname");
                        if ($stmtSt) {
                            $stmtSt->bind_param('i', $empresaId);
                            $stmtSt->execute();
                            $st = $stmtSt->get_result();
                        }
                    }

                    while ($st && $row = $st->fetch_assoc()): ?>
                        <li><a class="dropdown-item <?php echo (int)$row['id'] === (int)($t['staff_id'] ?? 0) ? 'active' : ''; ?>" href="tickets.php?id=<?php echo $tid; ?>&action=assign&staff_id=<?php echo (int)$row['id']; ?>"><?php echo html(trim($row['firstname'] . ' ' . $row['lastname'])); ?></a></li>
                    <?php endwhile; ?>
                </ul>
            </div>
            <?php if ($canTicketTransfer): ?>
                <button class="btn-icon" title="Transferir" type="button" data-bs-toggle="modal" data-bs-target="#modalTransfer"><i class="bi bi-arrow-left-right"></i></button>
            <?php endif; ?>

            <button class="btn-icon" title="Imprimir" type="button" data-action="print"><i class="bi bi-printer"></i></button>

            <div class="dropdown d-inline-block">
                <button class="btn-icon dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Configuración"><i class="bi bi-gear"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item <?php echo $canTicketEdit ? '' : 'disabled'; ?>" href="#" <?php echo $canTicketEdit ? 'data-bs-toggle="modal" data-bs-target="#modalOwner"' : 'tabindex="-1" aria-disabled="true"'; ?>><i class="bi bi-person-badge me-2"></i>Cambiar Propietario</a></li>
                    <li><a class="dropdown-item <?php echo $canTicketMerge ? '' : 'disabled'; ?>" href="#" <?php echo $canTicketMerge ? 'data-bs-toggle="modal" data-bs-target="#modalMerge"' : 'tabindex="-1" aria-disabled="true"'; ?>><i class="bi bi-link-45deg me-2"></i>Unir Tiquetes</a></li>
                    <li><a class="dropdown-item <?php echo $canTicketLink ? '' : 'disabled'; ?>" href="#" <?php echo $canTicketLink ? 'data-bs-toggle="modal" data-bs-target="#modalLinked"' : 'tabindex="-1" aria-disabled="true"'; ?>><i class="bi bi-link me-2"></i>Tickets vinculados</a></li>
                    <li><a class="dropdown-item <?php echo $canTicketMark ? '' : 'disabled'; ?>" href="<?php echo $canTicketMark ? ('tickets.php?id=' . $tid . '&action=mark_answered') : '#'; ?>" <?php echo $canTicketMark ? '' : 'tabindex="-1" aria-disabled="true"'; ?>><i class="bi bi-check-circle me-2"></i>Marcar como contestados</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-share me-2"></i>Administrar referidos</a></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-file-text me-2"></i>Gestionar formularios</a></li>
                    <li><a class="dropdown-item <?php echo $canTicketEdit ? '' : 'disabled'; ?>" href="#" <?php echo $canTicketEdit ? 'data-bs-toggle="modal" data-bs-target="#modalCollaborators"' : 'tabindex="-1" aria-disabled="true"'; ?>><i class="bi bi-people me-2"></i>Gestionar Colaboradores</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger <?php echo $canTicketEdit ? '' : 'disabled'; ?>" href="#" <?php echo $canTicketEdit ? 'data-bs-toggle="modal" data-bs-target="#modalBlockEmail"' : 'tabindex="-1" aria-disabled="true"'; ?>><i class="bi bi-envelope-x me-2"></i>Bloquear Email &lt;<?php echo html($t['user_email']); ?>&gt;</a></li>
                    <li><a class="dropdown-item text-danger <?php echo $canTicketDelete ? '' : 'disabled'; ?>" href="#" <?php echo $canTicketDelete ? 'data-bs-toggle="modal" data-bs-target="#modalDelete"' : 'tabindex="-1" aria-disabled="true"'; ?>><i class="bi bi-trash me-2"></i>Borrar Ticket</a></li>
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
                        <input type="hidden" name="user_id" id="owner-user-id" value="">
                        <input type="text" class="form-control" id="owner-user-search" autocomplete="off" placeholder="Buscar por nombre o correo…" value="">
                        <div id="owner-user-results" class="list-group mt-2" style="max-height: 240px; overflow:auto; display:none;"></div>
                        <div class="form-text" id="owner-user-selected" style="display:none;"></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" id="owner-user-submit" disabled>Cambiar</button></div>
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
                            $empresaId = function_exists('empresaId') ? (int)empresaId() : (int)($_SESSION['empresa_id'] ?? 0);
                            $stmtD = $mysqli->prepare("SELECT id, name FROM departments WHERE empresa_id = ? AND is_active = 1 ORDER BY name");
                            $depts = null;
                            if ($stmtD) {
                                $stmtD->bind_param('i', $empresaId);
                                $stmtD->execute();
                                $depts = $stmtD->get_result();
                            }
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
                        <p class="text-muted small">Este ticket se cerrará y sus mensajes se copiarán al ticket destino (el destino mantiene su número y recibe una copia de los mensajes).</p>
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
                            <input type="text" name="linked_ticket_id" class="form-control" placeholder="ID o número del ticket a vincular" required>
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
                                $empresaId = function_exists('empresaId') ? (int)empresaId() : (int)($_SESSION['empresa_id'] ?? 0);
                                $stmtU = $mysqli->prepare("SELECT id, firstname, lastname, email FROM users WHERE empresa_id = ? ORDER BY firstname, lastname");
                                $users = null;
                                if ($stmtU) {
                                    $stmtU->bind_param('i', $empresaId);
                                    $stmtU->execute();
                                    $users = $stmtU->get_result();
                                }
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
    
    <div class="modal fade" id="modalPriority" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="tickets.php?id=<?php echo $tid; ?>">
                    <input type="hidden" name="action" value="priority_update">
                    <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="modal-header"><h5 class="modal-title">Cambiar Prioridad</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <label class="form-label">Nueva prioridad</label>
                        <select name="priority_id" class="form-select" required>
                            <?php
                            $stmtP = $mysqli->prepare("SELECT id, name FROM priorities ORDER BY level");
                            $prioritiesResult = null;
                            if ($stmtP) {
                                $stmtP->execute();
                                $prioritiesResult = $stmtP->get_result();
                            }
                            while ($prioritiesResult && $p = $prioritiesResult->fetch_assoc()):
                            ?>
                                <option value="<?php echo (int)$p['id']; ?>" <?php echo (int)$p['id'] === (int)($t['priority_id'] ?? 0) ? 'selected' : ''; ?>><?php echo html($p['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Cambiar Prioridad</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal 1: Elegir tipo de cierre -->
    <div class="modal fade" id="modalCloseChoiceScp" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered scp-modal-dialog-sm">
            <div class="modal-content scp-modal-content">
                <div class="scp-modal-header">
                    <div class="scp-modal-header-icon"><i class="bi bi-check2-circle"></i></div>
                    <div class="scp-modal-header-text">
                        <div class="scp-modal-header-title">Cerrar Ticket #<?php echo html($t['ticket_number']); ?></div>
                        <div class="scp-modal-header-sub" id="closeChoiceStatusLabelScp"></div>
                    </div>
                    <button type="button" class="scp-modal-close" data-bs-dismiss="modal" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="scp-modal-body scp-modal-body-choice">
                    <p class="scp-choice-label">¿Cómo deseas cerrar este ticket?</p>
                    <div class="scp-choice-buttons">
                        <button type="button" class="scp-btn-choice scp-btn-choice-primary" id="btnCloseWithSignatureScp">
                            <span class="scp-btn-choice-icon"><i class="bi bi-pen"></i></span>
                            <span class="scp-btn-choice-text">
                                <span class="scp-btn-choice-main">Con firma del cliente</span>
                                <span class="scp-btn-choice-sub">El cliente firmará digitalmente</span>
                            </span>
                            <i class="bi bi-chevron-right scp-btn-choice-arrow"></i>
                        </button>
                        <button type="button" class="scp-btn-choice scp-btn-choice-ghost" id="btnCloseWithoutSignatureScp">
                            <span class="scp-btn-choice-icon"><i class="bi bi-x-circle"></i></span>
                            <span class="scp-btn-choice-text">
                                <span class="scp-btn-choice-main">Sin firma</span>
                                <span class="scp-btn-choice-sub">Cerrar sin conformidad del cliente</span>
                            </span>
                            <i class="bi bi-chevron-right scp-btn-choice-arrow"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal 2: Cerrar sin firma -->
    <div class="modal fade" id="modalCloseNoSignatureScp" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered scp-modal-dialog-sm">
            <div class="modal-content scp-modal-content">
                <div class="scp-modal-header">
                    <div class="scp-modal-header-icon scp-modal-header-icon-warn"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="scp-modal-header-text">
                        <div class="scp-modal-header-title">Cerrar sin firma</div>
                        <div class="scp-modal-header-sub">El ticket se cerrará sin conformidad</div>
                    </div>
                    <button type="button" class="scp-modal-close" data-bs-dismiss="modal" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="scp-modal-body">
                    <div class="scp-nosig-info">
                        <div class="scp-nosig-info-icon"><i class="bi bi-info-circle-fill"></i></div>
                        <div>
                            <p class="scp-nosig-info-title">Este ticket se cerrará sin firma del cliente.</p>
                            <p class="scp-nosig-info-desc">Se enviará una notificación interna a los agentes configurados.</p>
                        </div>
                    </div>
                </div>
                <div class="scp-modal-footer">
                    <button type="button" class="scp-btn-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="scp-btn-confirm" id="btnConfirmCloseNoSigScp"><i class="bi bi-check2-circle"></i> Cerrar ticket</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal 3: Cerrar con firma -->
    <div class="modal fade" id="modalCloseWithSignatureScp" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered scp-modal-dialog-sig">
            <div class="modal-content scp-modal-content">
                <div class="scp-modal-header">
                    <div class="scp-modal-header-icon"><i class="bi bi-vector-pen"></i></div>
                    <div class="scp-modal-header-text">
                        <div class="scp-modal-header-title">Firma del cliente</div>
                        <div class="scp-modal-header-sub">Dibuje la firma en el área indicada</div>
                    </div>
                    <button type="button" class="scp-modal-close" data-bs-dismiss="modal" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="scp-modal-body">
                    <div class="mb-3">
                        <label class="scp-field-label">Motivo de cierre <span class="scp-field-optional">(opcional)</span></label>
                        <textarea id="closeMessageWithSigScp" class="scp-textarea" rows="2" placeholder="Describe brevemente el motivo del cierre..."></textarea>
                    </div>
                    <div class="scp-sig-section">
                        <div class="scp-sig-header">
                            <label class="scp-field-label mb-0">Firma del cliente</label>
                            <button type="button" class="scp-btn-clear" id="btnClearSignatureScp"><i class="bi bi-eraser"></i> Limpiar</button>
                        </div>
                        <div class="scp-sig-canvas-wrap" id="sigCanvasWrap">
                            <canvas id="signatureCanvasScp" width="700" height="240" class="scp-signature-canvas"></canvas>
                            <div class="scp-sig-hint"><i class="bi bi-pencil-fill"></i> Firme aquí con el dedo o el ratón</div>
                        </div>
                        <div class="scp-rotate-hint" id="scpRotateHint">
                            <i class="bi bi-phone-landscape"></i>
                            <span>Gira el teléfono horizontalmente para una mejor experiencia de firma</span>
                        </div>
                    </div>
                </div>
                <div class="scp-modal-footer">
                    <button type="button" class="scp-btn-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="scp-btn-confirm" id="btnConfirmCloseWithSigScp"><i class="bi bi-check2-circle"></i> Cerrar ticket</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Resumen del ticket -->

    <div class="ticket-view-overview">
        <div>
            <div class="field">
                <label>Estado</label>
                <div class="value" style="display:flex; flex-direction:column; gap:5px; align-items:flex-start;">
                    <span class="badge-status" style="background: <?php echo html($t['status_color'] ?? '#e2e8f0'); ?>; color: #0f172a;"><?php echo html($t['status_name']); ?></span>
                    <?php if (!empty($t['closed']) && (int)($t['has_report'] ?? 0) === 1): ?>
                        <?php if (($t['billing_status'] ?? 'pending') === 'confirmed'): ?>
                            <a href="reporte_costos.php?ticket_id=<?php echo $tid; ?>" class="badge-status" style="background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; display:inline-flex; align-items:center; gap:6px; text-decoration:none; cursor:pointer; padding: 4px 10px; border-radius: 8px; font-weight: 600; font-size: 0.82rem;" title="Ver reporte de facturación"><i class="bi bi-patch-check-fill"></i> Facturado</a>
                        <?php else: ?>
                            <div style="display:flex; flex-direction:column; gap:4px; margin-top: 4px;">
                                <a href="reporte_costos.php?ticket_id=<?php echo $tid; ?>" class="badge-status" style="background: #fef9c3; color: #854d0e; border: 1px solid #fef08a; display:inline-flex; align-items:center; gap:6px; text-decoration:none; cursor:pointer; padding: 4px 10px; border-radius: 8px; font-weight: 600; font-size: 0.82rem;" title="Ver reporte pendiente"><i class="bi bi-clock-history"></i> Pendiente Facturación</a>
                                <?php if (getCurrentStaffRoleName() === 'admin'): ?>
                                    <button type="button" class="btn btn-sm mt-1" data-bs-toggle="modal" data-bs-target="#modalConfirmBilling" style="background: #15803d; color: #ffffff !important; font-size: 0.75rem; border-radius: 8px; font-weight: 700; border: none; box-shadow: 0 4px 10px rgba(21, 128, 61, 0.2); display: inline-flex; align-items: center; gap: 6px; justify-content: center; padding: 6px 12px; transition: transform 0.2s, background 0.2s;" onmouseover="this.style.background='#166534'; this.style.transform='translateY(-1px)';" onmouseout="this.style.background='#15803d'; this.style.transform='translateY(0)';">
                                        <i class="bi bi-check2-circle"></i> Confirmar Facturación
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="field">
                <label>Prioridad</label>
                <div class="value">
                    <?php if ($canTicketEdit): ?>
                        <a href="#" style="text-decoration: none; border-bottom: 1px dashed currentColor; color: inherit;" data-bs-toggle="modal" data-bs-target="#modalPriority" title="Cambiar prioridad">
                            <?php echo html($t['priority_name']); ?>
                        </a>
                    <?php else: ?>
                        <?php echo html($t['priority_name']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="field">
                <label>Departamento</label>
                <div class="value"><?php echo html($t['dept_name']); ?></div>
            </div>
            <div class="field d-none d-md-block">
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
            <div class="field d-none d-md-block">
                <?php
                $topicName = trim((string)($t['topic_name'] ?? ''));
                $isRedesInformatica = (stripos($topicName, 'redes') !== false || stripos($topicName, 'informática') !== false || stripos($topicName, 'informatica') !== false);
                ?>
                <?php if ($isRedesInformatica): ?>
                    <label>AnyDesk</label>
                    <div class="value"><?php echo html($t['anydesk'] ?? '—'); ?></div>
                <?php else: ?>
                    <label>Fuente</label>
                    <div class="value">Web</div>
                <?php endif; ?>
            </div>
            <div class="field d-none d-md-block">
                <label>Tema</label>
                <div class="value">
                    <?php
                    $topicName = trim((string)($t['topic_name'] ?? ''));
                    $topicLabel = $topicName !== '' ? $topicName : 'General';
                    ?>
                    <?php echo html($topicLabel); ?>
                </div>
            </div>
        </div>
        <div>
            <div class="field">
                <label>Asignado a</label>
                <div class="value"><?php echo html($t['staff_name']); ?></div>
            </div>
            <div class="field d-none d-md-block">
                <label>Último mensaje</label>
                <div class="value"><?php echo $t['last_message'] ? date('m/d/y H:i:s', strtotime($t['last_message'])) : '—'; ?></div>
            </div>
            <div class="field d-none d-md-block">
                <label>Última respuesta</label>
                <div class="value"><?php echo $t['last_response'] ? date('m/d/y H:i:s', strtotime($t['last_response'])) : '—'; ?></div>
            </div>
        </div>
    </div>

    <!-- Pestañas: Hilo del ticket -->
    <ul class="ticket-view-tabs" role="tablist">
        <li><a class="tab active" href="#thread"><i class="bi bi-chat-left-text"></i> Hilo del Ticket (<?php echo $countPublic; ?>)</a></li>
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
                <div class="tph-subject"><?php echo html(function_exists('cleanPlainText') ? cleanPlainText((string)($t['subject'] ?? '')) : (string)($t['subject'] ?? '')); ?></div>
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
                var overlay = document.getElementById('assign-loading');
                if (overlay) overlay.style.display = 'none';
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
                                        <?php
                                            $mime = strtolower((string)($a['mimetype'] ?? ''));
                                            $filename = strtolower((string)($a['original_filename'] ?? ''));
                                            $isImage = str_starts_with($mime, 'image/');
                                            $isPdf = ($mime === 'application/pdf' || str_ends_with($filename, '.pdf'));
                                            $isDocx = ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || str_ends_with($filename, '.docx'));
                                            
                                            $type = 'unknown';
                                            if ($isImage) $type = 'image';
                                            elseif ($isPdf) $type = 'pdf';
                                            elseif ($isDocx) $type = 'docx';

                                            $previewUrl = "tickets.php?id=" . (int)$tid . "&download=" . (int)$a['id'] . "&inline=1";
                                        ?>
                                        <div class="att-item">
                                            <div>
                                                <i class="bi bi-paperclip"></i>
                                                <a href="tickets.php?id=<?php echo (int)$tid; ?>&download=<?php echo (int)$a['id']; ?>" 
                                                   <?php if ($type !== 'unknown'): ?>
                                                   class="att-preview-trigger" 
                                                   data-preview-url="<?php echo html($previewUrl); ?>"
                                                   data-preview-type="<?php echo $type; ?>"
                                                   <?php if ($type === 'image' || $type === 'pdf'): ?>
                                                   data-mobile-inline="1"
                                                   <?php endif; ?>
                                                   <?php endif; ?>
                                                ><?php echo html($a['original_filename'] ?? 'archivo'); ?></a>
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

            <?php if (!empty($t['close_message'])): ?>
                <div class="ticket-close-note">
                    <div class="ticket-close-note-title">Motivo de cierre</div>
                    <div class="ticket-close-note-body"><?php echo nl2br(html((string)$t['close_message'])); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($ticketClientSignatureUrl !== ''): ?>
                <div class="ticket-signature-print-box">
                    <div class="ticket-signature-print-title">Firma del cliente</div>
                    <div class="ticket-signature-print-body">
                        <img src="<?php echo html($ticketClientSignatureUrl); ?>" alt="Firma del cliente" class="ticket-signature-print-image">
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Responder -->
    <div class="ticket-view-reply">
        <form method="post" action="tickets.php?id=<?php echo $tid; ?>" enctype="multipart/form-data" id="form-reply">
            <input type="hidden" name="csrf_token" value="<?php echo html($_SESSION['csrf_token'] ?? ''); ?>">
            <div class="mb-3">
                <label class="form-label fw-bold">Respuesta</label>
                <textarea name="body" id="reply_body" class="form-control" placeholder="Escribe tu respuesta aquí..."></textarea>
            </div>
            <div class="attach-zone" id="attach-zone" data-action="attachments-browse">
                <input type="file" name="attachments[]" id="attachments" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.txt">
                <div class="attach-text">Agregar archivos aquí o <a href="#" data-action="attachments-browse">elegirlos</a></div>
                <div class="attach-list" id="attach-list"></div>
            </div>
            <div class="reply-buttons">
                <button type="submit" name="do" value="reply" class="btn btn-reply btn-publish">
                    <i class="bi bi-send"></i> Publicar Respuesta
                </button>
                <?php if (!empty($canTicketClose) && empty($t['closed'])): ?>
                <button type="button" class="btn btn-reply btn-primary-reply" id="btnCloseTicketBottom">
                    <i class="bi bi-check2-circle"></i> Cerrar ticket
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-es-ES.min.js"></script>
<div class="modal fade" id="vigitecImageInsertModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Insertar Imagen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="vigitecImageInsertFile" class="form-label">Subir archivo de imagen</label>
                    <input type="file" class="form-control" id="vigitecImageInsertFile" accept="image/png, image/jpeg, image/gif, image/webp">
                </div>
                <div class="text-center my-2 text-muted small">Ó</div>
                <div class="mb-3">
                    <label for="vigitecImageInsertUrl" class="form-label">Desde URL web</label>
                    <input type="url" class="form-control" id="vigitecImageInsertUrl" placeholder="https://ejemplo.com/imagen.jpg">
                </div>
                <div class="form-text mt-2 text-primary" style="font-size:0.85rem;"><i class="bi bi-info-circle"></i> Archivos grandes pueden tardar en subir. Tamaño máximo ~5MB.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="vigitecImageInsertConfirm">Insertar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="videoInsertModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Insertar video</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <label for="videoInsertUrl" class="form-label">URL (YouTube o Vimeo)</label>
                <input type="url" class="form-control" id="videoInsertUrl" placeholder="https://www.youtube.com/watch?v=...">
                <div class="form-text">Pega un enlace de YouTube/Vimeo y se insertará en tu respuesta.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="videoInsertConfirm">Insertar</button>
            </div>
        </div>
    </div>
</div>
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

.ticket-view-entry .entry-body img { max-width: 420px !important; max-height: 260px !important; width: auto !important; height: auto !important; display: block; object-fit: contain; }
.ticket-view-entry .entry-body iframe { max-width: 520px !important; width: 100% !important; aspect-ratio: 16 / 9; height: auto !important; display: block; }

.note-editor .note-editable img { max-width: 420px !important; max-height: 260px !important; width: auto !important; height: auto !important; display: block; object-fit: contain; }
.note-editor .note-editable iframe { max-width: 520px !important; width: 100% !important; aspect-ratio: 16 / 9; height: auto !important; display: block; }

/* Image Preview Styles */
.att-image-preview-container {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 10000;
    pointer-events: auto;
    display: none;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    padding: 8px;
    max-width: min(90vw, 520px);
    animation: attFadeIn 0.2s ease-out forwards;
}
@media (max-width: 768px) {
    .att-image-preview-container {
        margin-top: -20px; /* Leave space for bottom button */
    }
}
.att-image-preview-container img {
    max-width: 100%;
    max-height: 350px;
    display: block;
    border-radius: 8px;
    object-fit: contain;
}
@keyframes attFadeIn {
    from { opacity: 0; transform: translate(-50%, -50%) scale(0.95); }
    to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
}
.att-image-preview-container .preview-content-docx {
    padding: 15px;
    font-size: 13px;
    line-height: 1.5;
    color: #334155;
    background: #fdfdfd;
    max-height: 400px;
    overflow-y: auto;
}
.att-image-preview-container .preview-loading {
    padding: 20px;
    text-align: center;
    color: #64748b;
    font-size: 12px;
}
.att-image-preview-container .preview-error {
    padding: 15px;
    color: #ef4444;
    font-size: 12px;
    text-align: center;
}
.att-preview-close {
    position: absolute;
    top: -12px;
    right: -12px;
    width: 30px;
    height: 30px;
    background: #1e293b;
    color: #fff;
    border: 2px solid #fff;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10001;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    padding: 0;
    line-height: 1;
}
.att-preview-close i { font-size: 16px; pointer-events: none; }
@media (max-width: 768px) {
    .att-preview-close { display: flex; }
    .att-image-preview-container {
        width: 94vw !important;
        max-width: 94vw !important;
        padding: 6px;
    }
    .att-image-preview-container img {
        max-height: 70vh;
    }
}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
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
        var videoModalEl = document.getElementById('videoInsertModal');
        var videoUrlEl = document.getElementById('videoInsertUrl');
        var videoConfirmEl = document.getElementById('videoInsertConfirm');
        var videoModal = null;
        var onVideoSubmit = null;
        if (videoModalEl && window.bootstrap && bootstrap.Modal) {
            videoModal = new bootstrap.Modal(videoModalEl);
        }

        var imageModalEl = document.getElementById('vigitecImageInsertModal');
        var imageFileEl = document.getElementById('vigitecImageInsertFile');
        var imageUrlEl = document.getElementById('vigitecImageInsertUrl');
        var imageConfirmEl = document.getElementById('vigitecImageInsertConfirm');
        var imageModal = null;
        var onImageSubmit = null;
        if (imageModalEl && window.bootstrap && bootstrap.Modal) {
            imageModal = new bootstrap.Modal(imageModalEl);
        }

        function openVideoModal(cb) {
            onVideoSubmit = cb;
            if (!videoModal || !videoUrlEl) return;
            videoUrlEl.value = '';
            videoModal.show();
            setTimeout(function () { try { videoUrlEl.focus(); } catch (e) {} }, 100);
        }

        function openImageModal(cb) {
            onImageSubmit = cb;
            if (!imageModal || !imageFileEl) return;
            imageFileEl.value = '';
            if (imageUrlEl) imageUrlEl.value = '';
            imageModal.show();
            setTimeout(function () { try { imageFileEl.focus(); } catch (e) {} }, 100);
        }

        if (videoConfirmEl) {
            videoConfirmEl.addEventListener('click', function () {
                if (!onVideoSubmit || !videoUrlEl) return;
                var v = (videoUrlEl.value || '').trim();
                if (v === '') return;
                try { videoModal && videoModal.hide(); } catch (e) {}
                try { onVideoSubmit(v); } catch (e2) {}
            });
        }
        if (imageConfirmEl) {
            imageConfirmEl.addEventListener('click', function () {
                if (!onImageSubmit || !imageFileEl) return;
                var f = imageFileEl.files && imageFileEl.files[0] ? imageFileEl.files[0] : null;
                var url = imageUrlEl ? (imageUrlEl.value || '').trim() : '';
                if (!f && !url) return;
                try { imageModal && imageModal.hide(); } catch (e) {}
                try { onImageSubmit(f || url); } catch (e2) {}
            });
        }
        if (videoUrlEl) {
            videoUrlEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    videoConfirmEl && videoConfirmEl.click();
                }
            });
        }
        if (imageFileEl) {
            imageFileEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    imageConfirmEl && imageConfirmEl.click();
                }
            });
        }
        if (imageUrlEl) {
            imageUrlEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    imageConfirmEl && imageConfirmEl.click();
                }
            });
        }

        function toEmbedUrl(url) {
            url = (url || '').trim();
            if (!url) return '';
            if (url.indexOf('//') === 0) url = 'https:' + url;
            if (/^https?:\/\/(www\.)?(youtube\.com\/embed\/|youtube-nocookie\.com\/embed\/)/i.test(url)) return url;
            if (/^https?:\/\/(www\.)?player\.vimeo\.com\/video\//i.test(url)) return url;
            var m = url.match(/(?:youtube\.com\/watch\?v=|youtube\.com\/shorts\/|youtu\.be\/)([A-Za-z0-9_-]{6,})/i);
            if (m && m[1]) return 'https://www.youtube-nocookie.com/embed/' + m[1] + '?rel=0';
            var v = url.match(/vimeo\.com\/(?:video\/)?(\d+)/i);
            if (v && v[1]) return 'https://player.vimeo.com/video/' + v[1];
            return '';
        }

        var myVideoBtn = function (context) {
            var ui = jQuery.summernote.ui;
            return ui.button({
                contents: '<i class="note-icon-video"></i>',
                tooltip: 'Insertar video (YouTube/Vimeo)',
                click: function () {
                    openVideoModal(function (url) {
                        var embed = toEmbedUrl(url);
                        if (!embed) {
                            alert('Formato de enlace no soportado. Usa un enlace de YouTube o Vimeo.');
                            return;
                        }
                        var html = '<iframe src="' + embed.replace(/"/g, '') + '" width="560" height="315" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>';
                        context.invoke('editor.pasteHTML', html);
                    });
                }
            }).render();
        };

        var myImageBtn = function () {
            var ui = jQuery.summernote.ui;
            return ui.button({
                contents: '<i class="note-icon-picture"></i>',
                tooltip: 'Insertar imagen',
                click: function () {
                    openImageModal(function (fileOrUrl) {
                        if (!fileOrUrl) return;
                        if (typeof fileOrUrl === 'string') {
                            jQuery('#reply_body').summernote('insertImage', fileOrUrl);
                            return;
                        }
                        var file = fileOrUrl;
                        var data = new FormData();
                        data.append('file', file);
                        data.append('csrf_token', <?php echo json_encode((string)($_SESSION['csrf_token'] ?? '')); ?>);
                        fetch('../editor_image_upload.php', {
                            method: 'POST',
                            body: data,
                            credentials: 'same-origin'
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (json) {
                            if (!json || !json.ok || !json.url) throw new Error((json && json.error) ? json.error : 'Upload failed');
                            jQuery('#reply_body').summernote('insertImage', json.url);
                        })
                        .catch(function (err) {
                            alert('No se pudo subir la imagen. Intenta con otra o usa Adjuntar archivos.');
                            try { console.error(err); } catch (e) {}
                        });
                    });
                }
            }).render();
        };

        var isMobile = window.innerWidth <= 768;
        var toolbarConfig = isMobile ? [
            ['font', ['bold', 'italic', 'underline', 'clear']],
            ['insert', ['link', 'myImage']],
            ['view', ['fullscreen']]
        ] : [
            ['style', ['style', 'paragraph']],
            ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
            ['fontname', ['fontname']],
            ['color', ['color']],
            ['fontsize', ['fontsize']],
            ['insert', ['link', 'myImage', 'myVideo', 'table', 'hr']],
            ['view', ['codeview', 'fullscreen']],
            ['para', ['ul', 'ol', 'paragraph']]
        ];

        var placeholderText = isMobile 
            ? 'Empezar escribiendo su respuesta aquí...' 
            : 'Escribe tu respuesta aquí...';

        jQuery('#reply_body').summernote({
            height: isMobile ? 160 : 260,
            lang: 'es-ES',
            placeholder: placeholderText,
            toolbar: toolbarConfig,
            buttons: {
                myVideo: myVideoBtn,
                myImage: myImageBtn
            },
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
    if (zone && input) {
        zone.addEventListener('click', function (e) {
            try {
                var t = e && e.target ? e.target : null;
                if (t && t.tagName && t.tagName.toLowerCase() === 'a') {
                    e.preventDefault();
                }
            } catch (err) {}
            try {
                if (input && typeof input.showPicker === 'function') {
                    input.showPicker();
                } else if (input) {
                    input.click();
                }
            } catch (err2) {
                try { if (input) input.click(); } catch (err3) {}
            }
        });
    }
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
    var btnReset = document.getElementById('btn-reset');
    if (btnReset) {
        btnReset.addEventListener('click', function() {
            if (typeof jQuery !== 'undefined' && jQuery('#reply_body').length && jQuery('#reply_body').summernote('code')) {
                jQuery('#reply_body').summernote('reset');
            }
            input.value = '';
            list.innerHTML = '';
        });
    }

    var ownerSearch = document.getElementById('owner-user-search');
    var ownerResults = document.getElementById('owner-user-results');
    var ownerId = document.getElementById('owner-user-id');
    var ownerSelected = document.getElementById('owner-user-selected');
    var ownerSubmit = document.getElementById('owner-user-submit');
    if (ownerSearch && ownerResults && ownerId && ownerSelected && ownerSubmit) {
        var ownerTimer = null;
        var lastOwnerQuery = '';

        function hideOwnerResults() {
            ownerResults.style.display = 'none';
            ownerResults.innerHTML = '';
        }

        function setOwnerSelected(id, label) {
            ownerId.value = String(id || '');
            ownerSelected.textContent = label || '';
            ownerSelected.style.display = label ? '' : 'none';
            ownerSubmit.disabled = !(id && Number(id) > 0);
            hideOwnerResults();
        }

        ownerSearch.addEventListener('input', function () {
            var q = String(ownerSearch.value || '').trim();
            setOwnerSelected('', '');
            if (q.length < 2) {
                hideOwnerResults();
                return;
            }
            if (ownerTimer) window.clearTimeout(ownerTimer);
            ownerTimer = window.setTimeout(function () {
                if (q === lastOwnerQuery) return;
                lastOwnerQuery = q;
                fetch('tickets.php?action=user_search&q=' + encodeURIComponent(q), {
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    ownerResults.innerHTML = '';
                    if (!data || !data.ok || !data.items || !data.items.length) {
                        hideOwnerResults();
                        return;
                    }
                    data.items.forEach(function (u) {
                        var a = document.createElement('a');
                        a.href = '#';
                        a.className = 'list-group-item list-group-item-action';
                        var nm = String(u.name || '').trim();
                        var em = String(u.email || '').trim();
                        a.textContent = (nm ? nm : 'Usuario') + (em ? (' (' + em + ')') : '');
                        a.addEventListener('click', function (e) {
                            e.preventDefault();
                            setOwnerSelected(u.id, a.textContent);
                        });
                        ownerResults.appendChild(a);
                    });
                    ownerResults.style.display = '';
                })
                .catch(function () {
                    hideOwnerResults();
                });
            }, 250);
        });

        document.addEventListener('click', function (e) {
            if (!ownerResults.contains(e.target) && e.target !== ownerSearch) {
                hideOwnerResults();
            }
        });

        var modalOwner = document.getElementById('modalOwner');
        if (modalOwner) {
            modalOwner.addEventListener('shown.bs.modal', function () {
                ownerSearch.focus();
            });
            modalOwner.addEventListener('hidden.bs.modal', function () {
                ownerSearch.value = '';
                setOwnerSelected('', '');
                lastOwnerQuery = '';
            });
        }
    }

    var ticketId = <?php echo (int)$tid; ?>;
    var csrfToken = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
    var closingStatusId = 0;
    var closingStatusName = '';
    var isClosingRequestBusy = false;

    var modalChoiceEl = document.getElementById('modalCloseChoiceScp');
    var modalNoSigEl = document.getElementById('modalCloseNoSignatureScp');
    var modalWithSigEl = document.getElementById('modalCloseWithSignatureScp');
    var modalChoice = (modalChoiceEl && window.bootstrap) ? bootstrap.Modal.getOrCreateInstance(modalChoiceEl) : null;
    var modalNoSig = (modalNoSigEl && window.bootstrap) ? bootstrap.Modal.getOrCreateInstance(modalNoSigEl) : null;
    var modalWithSig = (modalWithSigEl && window.bootstrap) ? bootstrap.Modal.getOrCreateInstance(modalWithSigEl) : null;

    var closeStatusLabel = document.getElementById('closeChoiceStatusLabelScp');
    var btnWithSig = document.getElementById('btnCloseWithSignatureScp');
    var btnWithoutSig = document.getElementById('btnCloseWithoutSignatureScp');
    var btnConfirmNoSig = document.getElementById('btnConfirmCloseNoSigScp');
    var btnConfirmWithSig = document.getElementById('btnConfirmCloseWithSigScp');
    var btnCloseTicketBottom = document.getElementById('btnCloseTicketBottom');

    var canvas = document.getElementById('signatureCanvasScp');
    var ctx = canvas ? canvas.getContext('2d') : null;
    var drawing = false;
    var hasDrawn = false;
    var lastX = 0;
    var lastY = 0;

    function setBusy(isBusy) {
        isClosingRequestBusy = isBusy;
        if (btnConfirmNoSig) btnConfirmNoSig.disabled = isBusy;
        if (btnConfirmWithSig) btnConfirmWithSig.disabled = isBusy;
    }

    function closeByAjax(signatureData, closeMessage) {
        if (!closingStatusId || isClosingRequestBusy) return;
        setBusy(true);
        var formData = new FormData();
        formData.append('ticket_id', String(ticketId));
        formData.append('status_id', String(closingStatusId));
        formData.append('close_message', closeMessage || '');
        formData.append('signature_data', signatureData || '');
        formData.append('csrf_token', csrfToken || '');

        fetch('../../agente/close-ticket.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) {
                var requiresReport = <?php echo (int)($t['requires_report'] ?? 0); ?>;
                var finalMsg = 'updated';
                if (requiresReport === 1) {
                    finalMsg = 'closed_report';
                }
                window.location.href = 'tickets.php?id=' + ticketId + '&msg=' + finalMsg;
                return;
            }
            setBusy(false);
            alert('Error: ' + ((data && data.error) ? data.error : 'No se pudo cerrar el ticket'));
        })
        .catch(function () {
            setBusy(false);
            alert('Error de conexion al cerrar el ticket');
        });
    }

    var statusCloseLinks = document.querySelectorAll('.js-status-close');
    if (statusCloseLinks && statusCloseLinks.length) {
        statusCloseLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                closingStatusId = parseInt(this.getAttribute('data-close-status-id') || '0', 10);
                closingStatusName = String(this.getAttribute('data-close-status-name') || '');
                if (!closingStatusId || !modalChoice) return;
                
                if (closingStatusName.toLowerCase().indexOf('resuelto') !== -1 || closingStatusName.toLowerCase().indexOf('resolved') !== -1) {
                    window.location.href = 'tickets.php?id=' + ticketId + '&action=status&status_id=' + closingStatusId;
                    return;
                }

                if (closeStatusLabel) closeStatusLabel.textContent = closingStatusName !== '' ? ('Estado de cierre: ' + closingStatusName) : '';
                if (canvas && ctx) {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    hasDrawn = false;
                }
                var msgYes = document.getElementById('closeMessageWithSigScp');
                if (msgYes) msgYes.value = '';
                modalChoice.show();
            });
        });
    }

    // Image Preview Logic
    (function() {
        var previewContainer = document.createElement('div');
        previewContainer.className = 'att-image-preview-container';
        
        // Boton de cierre (para movil)
        var closeBtn = document.createElement('button');
        closeBtn.className = 'att-preview-close';
        closeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        closeBtn.type = 'button';
        closeBtn.onclick = function(e) {
            e.stopPropagation();
            previewContainer.style.display = 'none';
            activeUrl = '';
        };
        previewContainer.appendChild(closeBtn);

        document.body.appendChild(previewContainer);

        var triggers = document.querySelectorAll('.att-preview-trigger');
        var hideTimeout = null;
        var activeUrl = '';
        var isMobile = window.innerWidth <= 768;

        function showPreview(el, e) {
            clearTimeout(hideTimeout);
            var url = el.getAttribute('data-preview-url');
            var type = el.getAttribute('data-preview-type');
            if (!url) return;
            
            if (activeUrl === url && previewContainer.style.display === 'block') return;

            activeUrl = url;
            previewContainer.innerHTML = '';
            previewContainer.appendChild(closeBtn);
            previewContainer.style.display = 'block';
            
            if (type === 'image') {
                var img = document.createElement('img');
                img.src = url;
                previewContainer.appendChild(img);
            } else if (type === 'pdf') {
                var iframe = document.createElement('iframe');
                iframe.src = url + '#toolbar=0&navpanes=0&scrollbar=1'; // Habilitar scrollbar
                iframe.style.width = isMobile ? '100%' : '500px';
                iframe.style.height = isMobile ? '60vh' : '380px';
                iframe.style.border = 'none';
                iframe.style.borderRadius = '8px';
                if (!isMobile) previewContainer.style.maxWidth = '520px';
                previewContainer.appendChild(iframe);
            } else if (type === 'docx') {
                var loader = document.createElement('div');
                loader.className = 'preview-loading';
                loader.innerHTML = '<div class="spinner-border spinner-border-sm text-primary me-2"></div> Cargando documento...';
                previewContainer.appendChild(loader);
                previewContainer.style.width = isMobile ? '100%' : '380px';

                fetch(url)
                    .then(function(r) { return r.arrayBuffer(); })
                    .then(function(arrayBuffer) {
                        if (activeUrl !== url) return;
                        return mammoth.convertToHtml({arrayBuffer: arrayBuffer});
                    })
                    .then(function(result) {
                        if (activeUrl !== url || !result) return;
                        previewContainer.innerHTML = '';
                        previewContainer.appendChild(closeBtn);
                        var content = document.createElement('div');
                        content.className = 'preview-content-docx';
                        content.innerHTML = result.value;
                        previewContainer.appendChild(content);
                    })
                    .catch(function(err) {
                        if (activeUrl !== url) return;
                        previewContainer.innerHTML = '';
                        previewContainer.appendChild(closeBtn);
                        var error = document.createElement('div');
                        error.className = 'preview-error';
                        error.innerHTML = '<i class="bi bi-exclamation-triangle"></i> No se pudo previsualizar el documento Word.';
                        previewContainer.appendChild(error);
                    });
            }
        }

        function hidePreview() {
            if (isMobile) return; // En movil no ocultamos por timeout de mouseleave
            hideTimeout = setTimeout(function() {
                previewContainer.style.display = 'none';
                previewContainer.innerHTML = '';
                previewContainer.appendChild(closeBtn);
                previewContainer.style.maxWidth = '400px';
                previewContainer.style.width = '';
                activeUrl = '';
            }, 250);
        }

        triggers.forEach(function(el) {
            el.addEventListener('mouseenter', function(e) {
                if (!isMobile) showPreview(el, e);
            });

            el.addEventListener('mouseleave', function() {
                if (!isMobile) hidePreview();
            });

            // Soporte para "dejar presionado" (long press) en movil
            var pressTimer;
            el.addEventListener('touchstart', function(e) {
                pressTimer = window.setTimeout(function() {
                    showPreview(el, e);
                    if (navigator.vibrate) navigator.vibrate(40);
                }, 600);
            }, {passive: true});

            el.addEventListener('touchend', function() {
                clearTimeout(pressTimer);
            }, {passive: true});

            el.addEventListener('touchmove', function() {
                clearTimeout(pressTimer);
            }, {passive: true});
        });

        previewContainer.addEventListener('mouseenter', function() {
            if (!isMobile) clearTimeout(hideTimeout);
        });

        previewContainer.addEventListener('mouseleave', function() {
            if (!isMobile) hidePreview();
        });

    })();

    if (btnWithSig) {

        btnWithSig.addEventListener('click', function () {
            if (modalChoice) modalChoice.hide();
            if (modalWithSig) modalWithSig.show();
        });
    }
    if (btnWithoutSig) {
        btnWithoutSig.addEventListener('click', function () {
            if (modalChoice) modalChoice.hide();
            if (modalNoSig) modalNoSig.show();
        });
    }
    if (btnConfirmNoSig) {
        btnConfirmNoSig.addEventListener('click', function () {
            closeByAjax('', '');
        });
    }
    if (btnConfirmWithSig) {
        btnConfirmWithSig.addEventListener('click', function () {
            if (!canvas || !ctx || !hasDrawn) {
                alert('Por favor dibuje la firma del cliente antes de cerrar.');
                return;
            }
            var msgYes = document.getElementById('closeMessageWithSigScp');
            closeByAjax(canvas.toDataURL('image/png'), msgYes ? msgYes.value.trim() : '');
        });
    }

    if (btnCloseTicketBottom) {
        btnCloseTicketBottom.addEventListener('click', function () {
            var closeOptions = Array.prototype.slice.call(document.querySelectorAll('.js-status-close'));
            var preferredClose = null;
            for (var i = 0; i < closeOptions.length; i++) {
                var statusName = String(closeOptions[i].getAttribute('data-close-status-name') || '').toLowerCase();
                if (statusName.indexOf('cerrad') !== -1 || statusName.indexOf('closed') !== -1) {
                    preferredClose = closeOptions[i];
                    break;
                }
            }
            if (!preferredClose && closeOptions.length > 0) {
                preferredClose = closeOptions[0];
            }
            if (!preferredClose) {
                alert('No hay un estado de cierre configurado.');
                return;
            }
            closingStatusId = parseInt(preferredClose.getAttribute('data-close-status-id') || '0', 10);
            closingStatusName = String(preferredClose.getAttribute('data-close-status-name') || '');
            if (!closingStatusId || !modalChoice) {
                alert('No se pudo iniciar el cierre del ticket.');
                return;
            }
            
            if (closingStatusName.toLowerCase().indexOf('resuelto') !== -1 || closingStatusName.toLowerCase().indexOf('resolved') !== -1) {
                window.location.href = 'tickets.php?id=' + ticketId + '&action=status&status_id=' + closingStatusId;
                return;
            }

            if (closeStatusLabel) closeStatusLabel.textContent = closingStatusName !== '' ? ('Estado de cierre: ' + closingStatusName) : '';
            if (canvas && ctx) {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                hasDrawn = false;
            }
            var msgYes = document.getElementById('closeMessageWithSigScp');
            if (msgYes) msgYes.value = '';
            modalChoice.show();
        });
    }

    function getCanvasPos(e) {
        var rect = canvas.getBoundingClientRect();
        var scaleX = canvas.width / rect.width;
        var scaleY = canvas.height / rect.height;
        if (e.touches && e.touches.length > 0) {
            return {
                x: (e.touches[0].clientX - rect.left) * scaleX,
                y: (e.touches[0].clientY - rect.top) * scaleY
            };
        }
        return {
            x: (e.clientX - rect.left) * scaleX,
            y: (e.clientY - rect.top) * scaleY
        };
    }

    function startDraw(e) {
        if (!canvas || !ctx) return;
        drawing = true;
        var pos = getCanvasPos(e);
        lastX = pos.x;
        lastY = pos.y;
        e.preventDefault();
    }

    function draw(e) {
        if (!drawing || !canvas || !ctx) return;
        var pos = getCanvasPos(e);
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(pos.x, pos.y);
        ctx.strokeStyle = '#111827';
        ctx.lineWidth = 2.4;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke();
        lastX = pos.x;
        lastY = pos.y;
        hasDrawn = true;
        e.preventDefault();
    }

    function stopDraw(e) {
        drawing = false;
        if (e) e.preventDefault();
    }

    if (canvas && ctx) {
        canvas.addEventListener('mousedown', startDraw);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDraw);
        canvas.addEventListener('mouseleave', stopDraw);
        canvas.addEventListener('touchstart', startDraw, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', stopDraw, { passive: false });

        var clearBtn = document.getElementById('btnClearSignatureScp');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                hasDrawn = false;
            });
        }
    }
});
</script>

<!-- Modal Confirmar Facturación -->
<div class="modal fade" id="modalConfirmBilling" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 25px 60px rgba(0,0,0,0.2); overflow: hidden;">
            <div class="modal-header" style="border-bottom: 1px solid #f1f5f9; padding: 22px 26px; background: #fff;">
                <h5 class="modal-title" style="font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 12px; font-size: 1.15rem;">
                    <div style="width: 36px; height: 36px; background: #dcfce7; color: #166534; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    Confirmar Facturación
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 26px; background: #fff;">
                <p style="color: #475569; font-size: 1rem; line-height: 1.6; margin-bottom: 0;">
                    ¿Estás seguro de que deseas marcar este ticket como <strong style="color: #0f172a;">facturado</strong>? Esta acción confirmará el reporte de costos de forma permanente.
                </p>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #f1f5f9; padding: 20px 26px; gap: 12px; background: #f8fafc;">
                <button type="button" class="btn" data-bs-dismiss="modal" style="background: #fff; color: #64748b; font-weight: 700; border-radius: 12px; padding: 10px 20px; border: 1px solid #e2e8f0; font-size: 0.9rem;">Cancelar</button>
                <a href="tickets.php?id=<?php echo $tid; ?>&action=confirm_billing" class="btn" style="background: #15803d; color: #fff; font-weight: 700; border-radius: 12px; padding: 10px 24px; border: none; box-shadow: 0 4px 15px rgba(21, 128, 61, 0.3); font-size: 0.9rem;">Confirmar y Facturar</a>
            </div>
        </div>
    </div>
</div>
