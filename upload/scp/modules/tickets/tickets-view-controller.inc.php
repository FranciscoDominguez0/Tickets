<?php
// Vista de un ticket concreto (tickets.php?id=X)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $tid = (int) $_GET['id'];

    // Firma del staff (si existe columna signature)
    $staff_signature = '';
    $staff_has_signature = false;
    $current_staff_id = (int) ($_SESSION['staff_id'] ?? 0);
    if ($current_staff_id > 0) {
        $stmtSig = $mysqli->prepare('SELECT * FROM staff WHERE empresa_id = ? AND id = ? LIMIT 1');
        $stmtSig->bind_param('ii', $eid, $current_staff_id);
        $stmtSig->execute();
        $staffRow = $stmtSig->get_result()->fetch_assoc();
        if ($staffRow && array_key_exists('signature', $staffRow)) {
            $staff_signature = trim((string)($staffRow['signature'] ?? ''));
            $staff_has_signature = $staff_signature !== '';
        }
    }

    // Cargar ticket con usuario, estado, prioridad, departamento, asignado
    $hasTopicCol = true;
    $hasTopicsTable = true;

    $topicSelect = ", ht.name AS topic_name";
    $topicJoin = " LEFT JOIN help_topics ht ON ht.id = t.topic_id";

    $stmt = $mysqli->prepare(
        "SELECT t.*, u.firstname AS user_first, u.lastname AS user_last, u.email AS user_email, u.address AS user_address, u.latitude AS user_latitude, u.longitude AS user_longitude,
         s.firstname AS staff_first, s.lastname AS staff_last, s.email AS staff_email,
         d.name AS dept_name, d.requires_report, ts.name AS status_name, ts.color AS status_color,
         p.name AS priority_name, p.color AS priority_color,
          (CASE WHEN tr.id IS NOT NULL THEN 1 ELSE 0 END) AS has_report,
          tr.billing_status,
          (SELECT status FROM ticket_approvals WHERE ticket_id = t.id ORDER BY id DESC LIMIT 1) AS approval_status,
          tr.final_price AS report_final_price"
          . $topicSelect .
        " FROM tickets t
         JOIN users u ON t.user_id = u.id
         LEFT JOIN staff s ON t.staff_id = s.id"
         . $topicJoin .
        " JOIN departments d ON t.dept_id = d.id
         JOIN ticket_status ts ON t.status_id = ts.id
         JOIN priorities p ON t.priority_id = p.id
         LEFT JOIN ticket_reports tr ON tr.ticket_id = t.id
         WHERE t.id = ? AND t.empresa_id = ?"
    );
    $stmt->bind_param('ii', $tid, $eid);
    $stmt->execute();
    $res = $stmt->get_result();
    $ticketView = $res ? $res->fetch_assoc() : null;
    if ($ticketView) {
        $canViewAll = roleHasPermission('ticket.view_all');
        $isAssignedToMe = (int)($ticketView['staff_id'] ?? 0) === (int)($_SESSION['staff_id'] ?? 0);
        // Allow access if: can view all, is assigned to me, OR has any ticket action permission
        $canActOnTickets = $canViewAll || $isAssignedToMe
            || roleHasPermission('ticket.edit')
            || roleHasPermission('ticket.assign')
            || roleHasPermission('ticket.reply')
            || roleHasPermission('ticket.close')
            || roleHasPermission('ticket.transfer')
            || roleHasPermission('ticket.post')
            || roleHasPermission('ticket.merge')
            || roleHasPermission('ticket.markanswered');
        if (!$canActOnTickets) {
            $_SESSION['flash_error'] = 'No tienes permiso para ver este ticket.';
            header('Location: tickets.php');
            exit;
        }
    } else {
        $_SESSION['flash_error'] = 'Ticket no encontrado o no pertenece a esta empresa.';
        header('Location: tickets.php');
        exit;
    }



    // Acción: Solicitar firma del cliente
    if ($ticketView && isset($_GET['action']) && $_GET['action'] === 'request_signature') {
        if (roleHasPermission('ticket.close')) {
            // Las columnas signature_token y signature_requested ya existen en la tabla tickets en producción.

            $token = bin2hex(random_bytes(16));
            $stmtUpd = $mysqli->prepare("UPDATE tickets SET signature_requested = 1, signature_token = ? WHERE id = ? AND empresa_id = ?");
            $stmtUpd->bind_param('sii', $token, $tid, $eid);
            if ($stmtUpd->execute()) {
                addLog('signature_requested', 'Firma solicitada al cliente remotamente', 'ticket', $tid, 'staff', (int)($_SESSION['staff_id'] ?? 0));
                
                // Enviar correo al cliente
                $clientEmail = trim((string)($ticketView['user_email'] ?? ''));
                if (filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
                    $clientName = trim($ticketView['user_first'] . ' ' . $ticketView['user_last']);
                    if ($clientName === '') $clientName = 'Cliente';
                    
                    $signUrl = rtrim(APP_URL, '/') . '/upload/view-ticket.php?id=' . $tid . '&s=' . $token;
                    $ticketNo = $ticketView['ticket_number'];
                    $subject = $ticketView['subject'];
                    
                    $emailSubject = 'Firma requerida: Ticket #' . $ticketNo . ' - ' . $subject;
                    $emailBodyHtml = '
<div style="font-family: Segoe UI, sans-serif; max-width: 600px; margin: 0 auto; color: #333;">
    <h2 style="color: #2c3e50;">Solicitud de firma digital</h2>
    <p>Se ha completado la atención de su ticket y requerimos su firma de conformidad para proceder con el cierre formal.</p>
    
    <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
        <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #eee; width: 100px;"><strong>Número:</strong></td>
            <td style="padding: 8px 0; border-bottom: 1px solid #eee;">#' . htmlspecialchars($ticketNo) . '</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Asunto:</strong></td>
            <td style="padding: 8px 0; border-bottom: 1px solid #eee;">' . htmlspecialchars($subject) . '</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Cliente:</strong></td>
            <td style="padding: 8px 0; border-bottom: 1px solid #eee;">' . htmlspecialchars($clientName) . '</td>
        </tr>
    </table>

    <p style="margin: 25px 0;">
        <a href="' . htmlspecialchars($signUrl) . '" style="display: inline-block; background: #3498db; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: 600;">
            Firmar de conformidad
        </a>
    </p>

    <p style="color: #7f8c8d; font-size: 12px; margin-top: 30px;">
        ' . htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets') . '
    </p>
</div>';
                    
                    $emailBodyText = "Hola $clientName,\n\nRequerimos su firma de conformidad para el ticket #$ticketNo.\n\nFirme aquí: $signUrl\n\nAtentamente,\n" . APP_NAME;
                    
                    if (function_exists('enqueueEmailJob')) {
                        enqueueEmailJob($clientEmail, $emailSubject, $emailBodyHtml, $emailBodyText, [
                            'empresa_id' => (int)$eid,
                            'context_type' => 'ticket_signature_request',
                            'context_id' => (int)$tid,
                        ]);
                        if (function_exists('triggerEmailQueueWorkerAsync')) {
                            triggerEmailQueueWorkerAsync();
                        }
                    } else {
                        Mailer::send($clientEmail, $emailSubject, $emailBodyHtml, $emailBodyText);
                    }
                }
                
                header("Location: tickets.php?id=$tid&msg=sig_requested");
                exit;
            }
        }
    }

    // Acción: Confirmar facturación
    if ($ticketView && isset($_GET['action']) && $_GET['action'] === 'confirm_billing') {
        if (roleHasPermission('admin.access')) {
            $stmtConf = $mysqli->prepare("UPDATE ticket_reports SET billing_status = 'confirmed' WHERE ticket_id = ? AND empresa_id = ?");
            $stmtConf->bind_param('ii', $tid, $eid);
            if ($stmtConf->execute()) {
                addLog('billing_confirmed', 'Facturación confirmada', 'ticket', $tid, 'staff', (int)($_SESSION['staff_id'] ?? 0));
                header("Location: tickets.php?id=$tid&msg=billing_confirmed");
                exit;
            }
        }
    }

    if ($ticketView) {
        $sidForSeen = (int)($_SESSION['staff_id'] ?? 0);
        if ($sidForSeen > 0) {
            $tk = 'tickets_seen_' . $sidForSeen;
            if (!isset($_SESSION[$tk]) || !is_array($_SESSION[$tk])) $_SESSION[$tk] = [];
            $_SESSION[$tk][] = (int)$tid;
            $_SESSION[$tk] = array_values(array_slice(array_unique(array_map('intval', $_SESSION[$tk])), -500));
            $seenIds[(int)$tid] = true;

            if (isset($mysqli) && $mysqli) {
                $stmtSeenIns = $mysqli->prepare('INSERT INTO staff_ticket_seen (staff_id, ticket_id, seen_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE seen_at = VALUES(seen_at)');
                if ($stmtSeenIns) {
                    $stmtSeenIns->bind_param('ii', $sidForSeen, $tid);
                    $stmtSeenIns->execute();
                }
            }
        }

        // Asegurar que exista un thread para este ticket
        $stmt = $mysqli->prepare("SELECT id FROM threads WHERE ticket_id = ?" . ($threadsHasEmpresa ? " AND empresa_id = ?" : ""));
        if ($threadsHasEmpresa) {
            $stmt->bind_param('ii', $tid, $eid);
        } else {
            $stmt->bind_param('i', $tid);
        }
        $stmt->execute();
        $threadRow = $stmt->get_result()->fetch_assoc();
        if (!$threadRow) {
            if ($threadsHasEmpresa) {
                $stmtThread = $mysqli->prepare('INSERT INTO threads (empresa_id, ticket_id, created) VALUES (?, ?, NOW())');
            } else {
                $stmtThread = $mysqli->prepare('INSERT INTO threads (ticket_id, created) VALUES (?, NOW())');
            }
            if ($stmtThread) {
                if ($threadsHasEmpresa) {
                    $stmtThread->bind_param('ii', $eid, $tid);
                } else {
                    $stmtThread->bind_param('i', $tid);
                }
                $stmtThread->execute();
            }
            $threadRow = ['id' => $mysqli->insert_id];
        }
        $thread_id = (int) $threadRow['id'];

        if ($thread_id > 0 && $sidForSeen > 0 && function_exists('markThreadEntriesReadByStaff')) {
            markThreadEntriesReadByStaff($mysqli, $thread_id, $sidForSeen, $eid);
        }

        // Descarga de adjuntos
        if (isset($_GET['download']) && is_numeric($_GET['download'])) {
            $aid = (int) $_GET['download'];
            $stmtA = $mysqli->prepare(
                "SELECT a.id, a.original_filename, a.mimetype, a.path\n"
                . "FROM attachments a\n"
                . "JOIN thread_entries te ON te.id = a.thread_entry_id\n"
                . "WHERE a.id = ? AND te.thread_id = ?\n"
                . "LIMIT 1"
            );
            $stmtA->bind_param('ii', $aid, $thread_id);
            $stmtA->execute();
            $att = $stmtA->get_result()->fetch_assoc();
            if (!$att) {
                http_response_code(404);
                exit('Archivo no encontrado');
            }
            $rel = (string) ($att['path'] ?? '');
            
            // Determinar los diferentes directorios base posibles
            $baseUpload = dirname(__DIR__, 3); // upload/
            $baseRoot   = dirname(__DIR__, 4); // sistema-tickets/

            // Rutas candidatas en orden de preferencia
            $full = '';
            if ($rel !== '') {
                // 1. Usando ATTACHMENTS_DIR (ruta absoluta configurada)
                $fullDir = defined('ATTACHMENTS_DIR')
                    ? rtrim(ATTACHMENTS_DIR, '/\\') . '/' . ltrim(str_replace('uploads/attachments/', '', $rel), '/\\')
                    : '';
                // 2. Ruta relativa al directorio upload/
                $full1 = rtrim($baseUpload, '/\\') . '/' . ltrim($rel, '/\\');
                // 3. Ruta relativa a la raíz del proyecto
                $full2 = rtrim($baseRoot, '/\\') . '/' . ltrim($rel, '/\\');
                // 4. Ruta absoluta tal cual la guardó la BD
                $full3 = $rel;

                foreach ([$fullDir, $full1, $full2, $full3] as $candidate) {
                    if ($candidate !== '' && is_file($candidate)) {
                        $full = $candidate;
                        break;
                    }
                }
            }

            if ($full === '') {
                http_response_code(404);
                exit('Archivo no encontrado');
            }

            $filename = (string) ($att['original_filename'] ?? 'archivo');
            $mime = (string) ($att['mimetype'] ?? 'application/octet-stream');
            $isInline = isset($_GET['inline']) && $_GET['inline'] == '1';

            header('Content-Type: ' . $mime);
            header('Content-Length: ' . (string) filesize($full));
            if ($isInline) {
                header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');
            } else {
                header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
            }
            header('X-Content-Type-Options: nosniff');
            if (ob_get_length()) ob_clean();
            flush();
            readfile($full);
            exit;
        }

        // Info de cierre (para mostrar debajo del hilo)
        $ticket_closed_info = null;
        if (!empty($ticketView['closed'])) {
            $closed_by = '';
            $closed_at = $ticketView['closed'];
            // Tomar el último staff que escribió en el hilo antes (o cerca) del cierre
            $stmtCb = $mysqli->prepare(
                "SELECT te.staff_id, s.firstname, s.lastname\n"
                . "FROM thread_entries te\n"
                . "JOIN staff s ON s.id = te.staff_id\n"
                . "WHERE te.thread_id = ? AND te.staff_id IS NOT NULL AND te.created <= ?\n"
                . "ORDER BY te.created DESC\n"
                . "LIMIT 1"
            );
            if ($stmtCb) {
                $stmtCb->bind_param('is', $thread_id, $closed_at);
                if ($stmtCb->execute()) {
                    $r = $stmtCb->get_result()->fetch_assoc();
                    if ($r) {
                        $closed_by = trim(($r['firstname'] ?? '') . ' ' . ($r['lastname'] ?? ''));
                    }
                }
            }
            if ($closed_by === '') {
                $closed_by = trim((string)($ticketView['staff_first'] ?? '') . ' ' . (string)($ticketView['staff_last'] ?? ''));
            }
            if ($closed_by === '') {
                $closed_by = 'Agente';
            }
            $ticket_closed_info = [
                'by' => $closed_by,
                'at' => $closed_at,
                'status' => (string)($ticketView['status_name'] ?? 'Cerrado'),
            ];
        }

        // Acciones rápidas: estado, asignar, eliminar, etc.
        $action = $_GET['action'] ?? $_POST['action'] ?? null;
        $csrfOk = true;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['send_executive_quote', 'update_support_times', 'request_approval', 'owner', 'block_email', 'delete', 'merge', 'link', 'collab_add', 'transfer', 'priority_update', 'edit_entry', 'delete_entry', 'referral_add'], true)) {
            $csrfOk = isset($_POST['csrf_token']) && Auth::validateCSRF($_POST['csrf_token']);
        }
        if ($action !== null && isset($_SESSION['staff_id']) && $csrfOk) {
            $ok = false;
            $msg = '';
            if ($action === 'status' && isset($_GET['status_id']) && is_numeric($_GET['status_id'])) {
                $sid = (int) $_GET['status_id'];
                
                // Verificar si es un estado de cerrado
                $isClosingStatus = false;
                $statusLabel = '';
                $stmtSt = $mysqli->prepare('SELECT name FROM ticket_status WHERE id = ? LIMIT 1');
                if ($stmtSt) {
                    $stmtSt->bind_param('i', $sid);
                    if ($stmtSt->execute()) {
                        $stRow = $stmtSt->get_result()->fetch_assoc();
                        $stName = strtolower(trim((string)($stRow['name'] ?? '')));
                        $statusLabel = trim((string)($stRow['name'] ?? ''));
                        if ($stName !== '' && (str_contains($stName, 'cerrad') || str_contains($stName, 'closed'))) {
                            $isClosingStatus = true;
                        }
                    }
                }
                
                if ($isClosingStatus) {
                    requireRolePermission('ticket.close', 'tickets.php?id=' . $tid);
                    $stmt = $mysqli->prepare("UPDATE tickets SET status_id = ?, closed = NOW(), updated = NOW(), signature_requested = 0, signature_token = NULL WHERE id = ? AND empresa_id = ?");
                } else {
                    requireRolePermission('ticket.edit', 'tickets.php?id=' . $tid);
                    $stmt = $mysqli->prepare("UPDATE tickets SET status_id = ?, closed = NULL, updated = NOW() WHERE id = ? AND empresa_id = ?");
                }
                $stmt->bind_param('iii', $sid, $tid, $eid);
                $ok = $stmt->execute();
                $msg = 'updated';

                // Si cambió a En Camino (id=2), notificar al usuario
                if ($ok && $sid === 2 && (int)($ticketView['status_id'] ?? 0) !== 2) {
                    $toClient = trim((string)($ticketView['user_email'] ?? ''));
                    if ($toClient !== '' && filter_var($toClient, FILTER_VALIDATE_EMAIL)) {
                        $ticketNo = (string)($ticketView['ticket_number'] ?? ('#' . $tid));
                        $ticketSubject = (string)($ticketView['subject'] ?? '');
                        $ticketTopic = (string)($ticketView['topic_name'] ?? 'General');
                        $subjClient = '[Ticket En Camino] ' . $ticketNo . ' - ' . $ticketSubject;
                        
                        $bodyHtmlClient = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
                            . '<h2 style="margin:0 0 10px;color:#1e3a5f;">Técnicos en camino</h2>'
                            . '<p>Estimado usuario, los técnicos ya van en camino para atender su solicitud.</p>'
                            . '<div style="background:#f8fafc; border:1px solid #e2e8f0; padding:14px; border-radius:10px; margin-top:14px;">'
                            . '<p style="margin:0 0 8px;"><strong>ID del Ticket:</strong> ' . html($ticketNo) . '</p>'
                            . '<p style="margin:0 0 8px;"><strong>Tema:</strong> ' . html($ticketTopic) . '</p>'
                            . '<p style="margin:0;"><strong>Asunto:</strong> ' . html($ticketSubject) . '</p>'
                            . '</div>'
                            . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . html((string)(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets')) . '</p>'
                            . '</div>';
                        $bodyTextClient = "Estimado usuario, los técnicos ya van en camino para atender su solicitud.\n\n"
                            . "ID del Ticket: $ticketNo\n"
                            . "Tema: $ticketTopic\n"
                            . "Asunto: $ticketSubject";
                        if (function_exists('enqueueEmailJob')) {
                            enqueueEmailJob($toClient, $subjClient, $bodyHtmlClient, $bodyTextClient, [
                                'empresa_id' => (int)$eid,
                                'context_type' => 'ticket_en_camino_client',
                                'context_id' => (int)$tid,
                            ]);
                            if (function_exists('triggerEmailQueueWorkerAsync')) {
                                triggerEmailQueueWorkerAsync();
                            }
                        } else {
                            Mailer::send($toClient, $subjClient, $bodyHtmlClient, $bodyTextClient);
                        }
                        addLog('ticket_en_camino_email', 'Notificación En Camino encolada/enviada al usuario ' . $toClient, 'ticket', $tid);
                    }
                    // Notificación interna (campana) a destinatarios configurados
                    notifyStatusChangeToAdminRecipients($tid, 'En Camino');
                }

                // Si cambió a En Proceso (id=3), notificar al usuario
                if ($ok && $sid === 3 && (int)($ticketView['status_id'] ?? 0) !== 3) {
                    $toClient = trim((string)($ticketView['user_email'] ?? ''));
                    if ($toClient !== '' && filter_var($toClient, FILTER_VALIDATE_EMAIL)) {
                        $ticketNo = (string)($ticketView['ticket_number'] ?? ('#' . $tid));
                        $ticketSubject = (string)($ticketView['subject'] ?? '');
                        $ticketTopic = (string)($ticketView['topic_name'] ?? 'General');
                        $subjClient = '[Ticket En Proceso] ' . $ticketNo . ' - ' . $ticketSubject;
                        
                        $viewUrl = (defined('APP_URL') ? APP_URL : '') . '/upload/tickets.php?id=' . (int) $tid;
                        $bodyHtmlClient = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
                            . '<h2 style="margin:0 0 10px;color:#1e3a5f;">Técnicos han llegado al lugar</h2>'
                            . '<p>Estimado usuario, los técnicos han llegado al lugar o su ticket está en proceso.</p>'
                            . '<div style="background:#f8fafc; border:1px solid #e2e8f0; padding:14px; border-radius:10px; margin-top:14px;">'
                            . '<p style="margin:0 0 8px;"><strong>ID del Ticket:</strong> ' . html($ticketNo) . '</p>'
                            . '<p style="margin:0 0 8px;"><strong>Tema:</strong> ' . html($ticketTopic) . '</p>'
                            . '<p style="margin:0;"><strong>Asunto:</strong> ' . html($ticketSubject) . '</p>'
                            . '</div>'
                            . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . html((string)(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets')) . '</p>'
                            . '</div>';
                        $bodyTextClient = "Estimado usuario, los técnicos han llegado al lugar o su ticket está en proceso.\n\n"
                            . "ID del Ticket: $ticketNo\n"
                            . "Tema: $ticketTopic\n"
                            . "Asunto: $ticketSubject";
                        if (function_exists('enqueueEmailJob')) {
                            enqueueEmailJob($toClient, $subjClient, $bodyHtmlClient, $bodyTextClient, [
                                'empresa_id' => (int)$eid,
                                'context_type' => 'ticket_en_proceso_client',
                                'context_id' => (int)$tid,
                            ]);
                            if (function_exists('triggerEmailQueueWorkerAsync')) {
                                triggerEmailQueueWorkerAsync();
                            }
                        } else {
                            Mailer::send($toClient, $subjClient, $bodyHtmlClient, $bodyTextClient);
                        }
                        addLog('ticket_en_proceso_email', 'Notificación En Proceso encolada/enviada al usuario ' . $toClient, 'ticket', $tid);
                    }
                    // Notificación interna (campana) a destinatarios configurados
                    notifyStatusChangeToAdminRecipients($tid, 'En Proceso');
                }

                // Si cambió a Resuelto (id=4), notificar
                if ($ok && $sid === 4 && (int)($ticketView['status_id'] ?? 0) !== 4) {
                    notifyStatusChangeToAdminRecipients($tid, 'Resuelto');
                }

                // Si cambió a Retenido (id=6), notificar al creador y a su jefe
                if ($ok && $sid === 6 && (int)($ticketView['status_id'] ?? 0) !== 6) {
                    $ticketNo = (string)($ticketView['ticket_number'] ?? ('#' . $tid));
                    $ticketSubject = (string)($ticketView['subject'] ?? '');
                    
                    $subjRetenido = '[Ticket Retenido] ' . $ticketNo . ' - ' . $ticketSubject;
                    $bodyHtmlRetenido = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
                        . '<h2 style="margin:0 0 10px;color:#e74c3c;">Ticket Retenido</h2>'
                        . '<p>Estimado usuario, su ticket ha sido puesto en estado Retenido temporalmente.</p>'
                        . '<div style="background:#f8fafc; border:1px solid #e2e8f0; padding:14px; border-radius:10px; margin-top:14px;">'
                        . '<p style="margin:0 0 8px;"><strong>ID del Ticket:</strong> ' . html($ticketNo) . '</p>'
                        . '<p style="margin:0;"><strong>Asunto:</strong> ' . html($ticketSubject) . '</p>'
                        . '</div>'
                        . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . html((string)(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets')) . '</p>'
                        . '</div>';
                    $bodyTextRetenido = "Estimado usuario, su ticket ha sido puesto en estado Retenido temporalmente.\n\n"
                        . "ID del Ticket: $ticketNo\n"
                        . "Asunto: $ticketSubject";

                    // 1. Notificar al creador del ticket
                    $toClient = trim((string)($ticketView['user_email'] ?? ''));
                    if ($toClient !== '' && filter_var($toClient, FILTER_VALIDATE_EMAIL)) {
                        if (function_exists('enqueueEmailJob')) {
                            enqueueEmailJob($toClient, $subjRetenido, $bodyHtmlRetenido, $bodyTextRetenido, [
                                'empresa_id' => (int)$eid,
                                'context_type' => 'ticket_retenido_client',
                                'context_id' => (int)$tid,
                            ]);
                        } else {
                            Mailer::send($toClient, $subjRetenido, $bodyHtmlRetenido, $bodyTextRetenido);
                        }
                    }

                    // 2. Notificar al jefe de la organización
                    $clientFullName = trim((string)($ticketView['user_first'] ?? '') . ' ' . (string)($ticketView['user_last'] ?? ''));
                    if ($clientFullName === '') $clientFullName = 'un miembro de su organización';

                    $subjBossRetenido = '[Ticket Retenido] ' . $ticketNo . ' - ' . $ticketSubject;
                    $bodyHtmlBossRetenido = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
                        . '<h2 style="margin:0 0 10px;color:#e74c3c;">Ticket Retenido</h2>'
                        . '<p>Le informamos que el ticket de <strong>' . html($clientFullName) . '</strong> ha sido puesto en estado Retenido temporalmente.</p>'
                        . '<div style="background:#f8fafc; border:1px solid #e2e8f0; padding:14px; border-radius:10px; margin-top:14px;">'
                        . '<p style="margin:0 0 8px;"><strong>ID del Ticket:</strong> ' . html($ticketNo) . '</p>'
                        . '<p style="margin:0;"><strong>Asunto:</strong> ' . html($ticketSubject) . '</p>'
                        . '</div>'
                        . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . html((string)(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets')) . '</p>'
                        . '</div>';
                    $bodyTextBossRetenido = "Le informamos que el ticket de $clientFullName ha sido puesto en estado Retenido temporalmente.\n\n"
                        . "ID del Ticket: $ticketNo\n"
                        . "Asunto: $ticketSubject";

                    $stmtBoss = $mysqli->prepare("SELECT u.email FROM user_organizations uo JOIN users u ON u.id = uo.user_id WHERE uo.organization_id = (SELECT organization_id FROM user_organizations WHERE user_id = ? LIMIT 1) AND u.org_tickets_view = 1 AND u.empresa_id = ? LIMIT 1");
                    if ($stmtBoss) {
                        $stmtBoss->bind_param('ii', $ticketView['user_id'], $eid);
                        if ($stmtBoss->execute()) {
                            $bossRow = $stmtBoss->get_result()->fetch_assoc();
                            if ($bossRow) {
                                $bossEmail = trim((string)($bossRow['email'] ?? ''));
                                if ($bossEmail !== '' && filter_var($bossEmail, FILTER_VALIDATE_EMAIL)) {
                                    if (function_exists('enqueueEmailJob')) {
                                        enqueueEmailJob($bossEmail, $subjBossRetenido, $bodyHtmlBossRetenido, $bodyTextBossRetenido, [
                                            'empresa_id' => (int)$eid,
                                            'context_type' => 'ticket_retenido_boss',
                                            'context_id' => (int)$tid,
                                        ]);
                                    } else {
                                        Mailer::send($bossEmail, $subjBossRetenido, $bodyHtmlBossRetenido, $bodyTextBossRetenido);
                                    }
                                }
                            }
                        }
                    }

                    if (function_exists('triggerEmailQueueWorkerAsync')) {
                        triggerEmailQueueWorkerAsync();
                    }
                    addLog('ticket_retenido_email', 'Notificación Retenido enviada al creador y jefe', 'ticket', $tid);
                    notifyStatusChangeToAdminRecipients($tid, 'Retenido');
                }

                // Si se cierra (id=5 u otro cierre), notificar
                if ($ok && $isClosingStatus && (int)($ticketView['status_id'] ?? 0) !== $sid) {
                    $labelNotif = ($statusLabel !== '') ? $statusLabel : 'Cerrado';
                    notifyStatusChangeToAdminRecipients($tid, $labelNotif);
                }

                // Si se cierra desde cambio de estado (sin firma), notificar por correo
                if ($ok && $isClosingStatus && empty($ticketView['closed'])) {
                    $ticketNo = (string)($ticketView['ticket_number'] ?? ('#' . $tid));
                    $ticketSubject = trim((string)($ticketView['subject'] ?? 'Ticket'));
                    if ($statusLabel === '') $statusLabel = 'Cerrado';
                    $companyName = (string)(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets');
                    
                    $token = hash_hmac('sha256', (string)$tid, defined('SECRET_KEY') ? SECRET_KEY : 'default-secret');
                    $ticketPdfUrl = rtrim((string)(defined('APP_URL') ? APP_URL : ''), '/') . '/upload/scp/ticket_pdf.php?id=' . (int)$tid . '&t=' . $token;

                    // Generar PDF para adjuntar al correo del cliente
                    $pdfBytesClient = null;
                    try {
                        $projectRootForPdf = realpath(dirname(__DIR__, 4));
                        if ($projectRootForPdf !== false) {
                            if (!class_exists('TicketPdfGenerator')) {
                                $tpgFile = $projectRootForPdf . '/includes/TicketPdfGenerator.php';
                                if (is_file($tpgFile)) require_once $tpgFile;
                            }
                            if (class_exists('TicketPdfGenerator')) {
                                $pdfBytesClient = TicketPdfGenerator::generate((int)$tid, $mysqli, $projectRootForPdf);
                            }
                        }
                    } catch (Throwable $e) {
                        $pdfBytesClient = null;
                    }

                    // Correo al cliente
                    $clientName = trim((string)($ticketView['user_first'] ?? '') . ' ' . (string)($ticketView['user_last'] ?? ''));
                    if ($clientName === '') $clientName = 'Cliente';
                    $clientEmail = strtolower(trim((string)($ticketView['user_email'] ?? '')));
                    $clientPdfUrl = rtrim((string)(defined('APP_URL') ? APP_URL : ''), '/') . '/upload/ticket_pdf.php?id=' . (int)$tid . '&t=' . $token;
                    
                    if ($clientEmail !== '' && filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
                        $safeTicketNo = preg_replace('~[^A-Za-z0-9_-]+~', '_', $ticketNo);
                        $clientSubj = '[Ticket cerrado] ' . $ticketNo . ' - ' . $ticketSubject;
                        $clientBodyHtml = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
                            . '<h2 style="margin:0 0 10px;color:#1e3a5f;">Tu ticket fue cerrado</h2>'
                            . '<p>Hola ' . html($clientName) . ',</p>'
                            . '<p>Te informamos que tu ticket <strong>' . html($ticketNo) . '</strong> fue marcado como <strong>' . html($statusLabel) . '</strong>.</p>'
                            . '<p><strong>Asunto:</strong> ' . html($ticketSubject) . '</p>'
                            . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . html($companyName) . '</p>'
                            . '</div>';
                        $clientBodyText = "Hola " . $clientName . ",\n\n"
                            . "Tu ticket " . $ticketNo . " fue marcado como " . $statusLabel . ".\n"
                            . "Asunto: " . $ticketSubject . "\n\n"
                            . $companyName;
                        $clientMailOpts = [
                            'empresa_id'   => (int)$eid,
                            'context_type' => 'ticket_closed_client',
                            'context_id'   => (int)$tid,
                        ];
                        if ($pdfBytesClient !== null) {
                            $clientMailOpts['attachments'] = [[
                                'filename'    => 'Ticket_' . $safeTicketNo . '.pdf',
                                'contentType' => 'application/pdf',
                                'content'     => $pdfBytesClient,
                            ]];
                        }
                        if (function_exists('enqueueEmailJob')) {
                            enqueueEmailJob($clientEmail, $clientSubj, $clientBodyHtml, $clientBodyText, $clientMailOpts);
                        } else {
                            if ($pdfBytesClient !== null) {
                                Mailer::sendWithOptions($clientEmail, $clientSubj, $clientBodyHtml, $clientBodyText, [
                                    'attachments' => [[
                                        'filename'    => 'Ticket_' . $safeTicketNo . '.pdf',
                                        'contentType' => 'application/pdf',
                                        'content'     => $pdfBytesClient,
                                    ]],
                                ]);
                            } else {
                                Mailer::send($clientEmail, $clientSubj, $clientBodyHtml, $clientBodyText);
                            }
                        }
                    }

                    // Correo a agentes configurados en notificaciones
                    $adminRecipients = [];
                    if (dbTableExists('notification_recipients')) {
                        $staffHasEmpresa = dbColumnExists('staff', 'empresa_id');

                        $sqlAdmin = "SELECT s.email FROM notification_recipients nr INNER JOIN staff s ON s.id = nr.staff_id WHERE nr.empresa_id = ? AND s.is_active = 1";
                        if ($staffHasEmpresa) {
                            $sqlAdmin .= " AND s.empresa_id = ?";
                        }
                        $stmtAdmin = $mysqli->prepare($sqlAdmin);
                        if ($stmtAdmin) {
                            if ($staffHasEmpresa) {
                                $stmtAdmin->bind_param('ii', $eid, $eid);
                            } else {
                                $stmtAdmin->bind_param('i', $eid);
                            }
                            if ($stmtAdmin->execute()) {
                                $rsAdmin = $stmtAdmin->get_result();
                                while ($rsAdmin && ($rowAdmin = $rsAdmin->fetch_assoc())) {
                                    $em = strtolower(trim((string)($rowAdmin['email'] ?? '')));
                                    if ($em === '' || !filter_var($em, FILTER_VALIDATE_EMAIL)) continue;
                                    $adminRecipients[$em] = true;
                                }
                            }
                        }
                    }

                    if (!empty($adminRecipients)) {
                        $adminSubj = '[Ticket cerrado] ' . $ticketNo . ' - ' . $ticketSubject;
                        $adminBodyHtml = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
                            . '<h2 style="margin:0 0 10px;color:#1e3a5f;">Ticket cerrado</h2>'
                            . '<p>Un ticket fue cerrado en el sistema.</p>'
                            . '<table style="width:100%;border-collapse:collapse;margin:10px 0 14px;">'
                            . '<tr><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;"><strong>Número:</strong></td><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;">' . html($ticketNo) . '</td></tr>'
                            . '<tr><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;"><strong>Asunto:</strong></td><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;">' . html($ticketSubject) . '</td></tr>'
                            . '<tr><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;"><strong>Estado:</strong></td><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;">' . html($statusLabel) . '</td></tr>'
                            . '<tr><td style="padding:6px 0;"><strong>Firma cliente:</strong></td><td style="padding:6px 0;">No</td></tr>'
                            . '</table>'
                            . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . html($companyName) . '</p>'
                            . '</div>';
                        $adminBodyText = "Ticket cerrado\n\n"
                            . "Numero: " . $ticketNo . "\n"
                            . "Asunto: " . $ticketSubject . "\n"
                            . "Estado: " . $statusLabel . "\n"
                            . "Firma cliente: No\n\n"
                            . $companyName;

                        $pdfAttachment = null;
                        try {
                            $projectRoot = realpath(dirname(__DIR__, 4));
                            if ($projectRoot !== false) {
                                $autoload = $projectRoot . '/vendor/autoload.php';
                                if (is_file($autoload)) {
                                    require_once $autoload;
                                    if (class_exists('Dompdf\\Dompdf')) {
                                        if (!defined('TICKET_PDF_RENDER')) {
                                            define('TICKET_PDF_RENDER', true);
                                        }
                                        $_GET['id'] = (int)$tid;
                                        ob_start();
                                        require __DIR__ . '/../../print_ticket.php';
                                        $pdfHtml = (string)ob_get_clean();

                                        $dompdf = new Dompdf\Dompdf([
                                            'isRemoteEnabled' => true,
                                            'isHtml5ParserEnabled' => true,
                                        ]);
                                        $dompdf->loadHtml($pdfHtml, 'UTF-8');
                                        $dompdf->setPaper('A4', 'portrait');
                                        $dompdf->render();
                                        $pdfBin = $dompdf->output();
                                        if (is_string($pdfBin) && $pdfBin !== '') {
                                            $safeTicketNo = preg_replace('~[^A-Za-z0-9_-]+~', '_', (string)$ticketNo);
                                            $pdfAttachment = [
                                                'filename' => 'Ticket_' . $safeTicketNo . '.pdf',
                                                'contentType' => 'application/pdf',
                                                'content' => $pdfBin,
                                            ];
                                        }
                                    }
                                }
                            }
                        } catch (Throwable $e) {
                            $pdfAttachment = null;
                        }

                        foreach (array_keys($adminRecipients) as $adminEmail) {
                            $adminMailOpts = [
                                'empresa_id' => (int)$eid,
                                'context_type' => 'ticket_closed_admin',
                                'context_id' => (int)$tid,
                            ];
                            if ($pdfAttachment !== null) {
                                $adminMailOpts['attachments'] = [$pdfAttachment];
                            }
                            if (function_exists('enqueueEmailJob')) {
                                enqueueEmailJob($adminEmail, $adminSubj, $adminBodyHtml, $adminBodyText, $adminMailOpts);
                            } else {
                                if ($pdfAttachment !== null) {
                                    Mailer::sendWithOptions($adminEmail, $adminSubj, $adminBodyHtml, $adminBodyText, [
                                        'attachments' => [$pdfAttachment],
                                    ]);
                                } else {
                                    Mailer::send($adminEmail, $adminSubj, $adminBodyHtml, $adminBodyText);
                                }
                            }
                        }
                    }

                    if (function_exists('triggerEmailQueueWorkerAsync')) {
                        triggerEmailQueueWorkerAsync(40);
                    }
                }
            } elseif ($action === 'request_approval') {
                requireRolePermission('ticket.edit', 'tickets.php?id=' . $tid);
                $msg = 'approval_error';

                $stmtPending = $mysqli->prepare("SELECT id FROM ticket_approvals WHERE ticket_id = ? AND status = 'pending' LIMIT 1");
                $hasPendingApproval = false;
                if ($stmtPending) {
                    $stmtPending->bind_param('i', $tid);
                    if ($stmtPending->execute()) {
                        $hasPendingApproval = (bool)$stmtPending->get_result()->fetch_assoc();
                    }
                }
                if ($hasPendingApproval) {
                    $msg = 'approval_already_pending';
                } else {
                    $stmtBoss = $mysqli->prepare("SELECT u.id, u.email, u.firstname, u.lastname FROM user_organizations uo JOIN users u ON u.id = uo.user_id WHERE uo.organization_id = (SELECT organization_id FROM user_organizations WHERE user_id = ? LIMIT 1) AND u.org_tickets_view = 1 AND u.empresa_id = ? LIMIT 1");
                    $bossRow = null;
                    if ($stmtBoss) {
                        $stmtBoss->bind_param('ii', $ticketView['user_id'], $eid);
                        if ($stmtBoss->execute()) {
                            $bossRow = $stmtBoss->get_result()->fetch_assoc();
                        }
                    }

                    if (!$bossRow) {
                        $msg = 'approval_no_manager';
                    } else {
                        $bossEmail = trim((string)($bossRow['email'] ?? ''));
                        if ($bossEmail === '' || !filter_var($bossEmail, FILTER_VALIDATE_EMAIL)) {
                            $msg = 'approval_no_email';
                        } else {
                            $ticketNo = $ticketView['ticket_number'];
                            $bossName = trim($bossRow['firstname'] . ' ' . $bossRow['lastname']);
                            $subjBoss = "[Revisión requerida] Ticket #" . $ticketNo . " requiere autorización";

                            $orgPortalUrl = rtrim((string)(defined('APP_URL') ? APP_URL : ''), '/') . '/upload/view-ticket.php?from=org&id=' . $tid;

                            $bossBodyHtml = '<div style="font-family: Segoe UI, sans-serif; max-width: 700px; margin: 0 auto;">'
                                . '<h2 style="color:#1e3a5f; margin: 0 0 8px;">Autorización Requerida</h2>'
                                . '<p style="color:#475569; margin: 0 0 12px;">Estimado/a <strong>' . htmlspecialchars($bossName) . '</strong>, un agente requiere su revisión y autorización ejecutiva para proceder con el siguiente ticket:</p>'
                                . '<table style="width:100%; border-collapse: collapse; margin: 12px 0;">'
                                . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee; width: 100px;"><strong>Número:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">#' . htmlspecialchars($ticketNo) . '</td></tr>'
                                . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee;"><strong>Asunto:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">' . htmlspecialchars((string)$ticketView['subject']) . '</td></tr>'
                                . '</table>'
                                . '<p style="margin: 14px 0 0;"><a href="' . htmlspecialchars($orgPortalUrl) . '" style="display:inline-block; background:#2563eb; color:#fff; padding:10px 16px; text-decoration:none; border-radius:8px;">Revisar y Autorizar</a></p>'
                                . '<p style="color:#94a3b8; font-size:12px; margin-top: 14px;">' . htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets') . '</p>'
                                . '</div>';

                            $bossBodyText = "Autorización Requerida\n\nTicket: #" . $ticketNo . "\nAsunto: " . $ticketView['subject'] . "\n\nPor favor revise y autorice este ticket accediendo al siguiente enlace:\n" . $orgPortalUrl;

                            $emailOk = false;
                            if (function_exists('enqueueEmailJob')) {
                                $emailOk = enqueueEmailJob($bossEmail, $subjBoss, $bossBodyHtml, $bossBodyText, [
                                    'empresa_id' => (int)$eid,
                                    'context_type' => 'ticket_approval_request',
                                    'context_id' => (int)$tid,
                                ]);
                                if ($emailOk && function_exists('triggerEmailQueueWorkerAsync')) {
                                    triggerEmailQueueWorkerAsync();
                                }
                            } else {
                                $emailOk = Mailer::send($bossEmail, $subjBoss, $bossBodyHtml, $bossBodyText);
                            }

                            if ($emailOk) {
                                $staffId = (int)$_SESSION['staff_id'];
                                $stmtIns = $mysqli->prepare("INSERT INTO ticket_approvals (ticket_id, requested_by_staff_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
                                if ($stmtIns) {
                                    $stmtIns->bind_param('ii', $tid, $staffId);
                                    $stmtIns->execute();
                                }
                                addLog('approval_requested', 'Solicitud de aprobación enviada al jefe de la organización (' . $bossEmail . ')', 'ticket', $tid, 'staff', $staffId);
                                $msg = 'approval_requested';
                            } else {
                                $msg = 'approval_email_failed';
                            }
                        }
                    }
                }
                header("Location: tickets.php?id=$tid&msg=$msg");
                exit;
            } elseif ($action === 'send_executive_quote') {
                requireRolePermission('ticket.reply', 'tickets.php?id=' . $tid);
                $msg = 'quote_error';
                
                if (!empty($_FILES['quote_pdf']['name']) && $_FILES['quote_pdf']['error'] === UPLOAD_ERR_OK) {
                    $staffId = (int)$_SESSION['staff_id'];
                    $quoteMsg = trim($_POST['quote_msg'] ?? '');
                    
                    $tmpName = $_FILES['quote_pdf']['tmp_name'];
                    $origName = $_FILES['quote_pdf']['name'];
                    $size = $_FILES['quote_pdf']['size'];
                    $mime = 'application/pdf';
                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    
                    if ($ext === 'pdf') {
                        $hash = md5_file($tmpName);
                        $subdir = substr($hash, 0, 2);
                        $uploadDir = rtrim(ATTACHMENTS_DIR, '/\\') . '/' . $subdir;
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        $finalName = $hash . '_' . time() . '.pdf';
                        $finalPath = $uploadDir . '/' . $finalName;
                        $dbPath = 'uploads/attachments/' . $subdir . '/' . $finalName;
                        
                        if (move_uploaded_file($tmpName, $finalPath)) {
                            $body = "<p><strong>Cotización enviada para revisión ejecutiva.</strong></p>";
                            if ($quoteMsg !== '') {
                                $body .= "<p>" . htmlspecialchars($quoteMsg) . "</p>";
                            }
                            
                            if (dbColumnExists('thread_entries', 'empresa_id')) {
                                $stmtEntry = $mysqli->prepare("INSERT INTO thread_entries (empresa_id, thread_id, staff_id, body, is_internal, created) VALUES (?, ?, ?, ?, 0, NOW())");
                                $stmtEntry->bind_param('iiis', $eid, $thread_id, $staffId, $body);
                            } else {
                                $stmtEntry = $mysqli->prepare("INSERT INTO thread_entries (thread_id, staff_id, body, is_internal, created) VALUES (?, ?, ?, 0, NOW())");
                                $stmtEntry->bind_param('iis', $thread_id, $staffId, $body);
                            }
                            $stmtEntry->execute();
                            $entryId = $mysqli->insert_id;
                            
                            if (dbColumnExists('attachments', 'empresa_id')) {
                                $stmtAtt = $mysqli->prepare("INSERT INTO attachments (empresa_id, thread_entry_id, filename, original_filename, mimetype, size, path, hash, created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                                $stmtAtt->bind_param('iisssiss', $eid, $entryId, $finalName, $origName, $mime, $size, $dbPath, $hash);
                            } else {
                                $stmtAtt = $mysqli->prepare("INSERT INTO attachments (thread_entry_id, filename, original_filename, mimetype, size, path, hash, created) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                                $stmtAtt->bind_param('isssiss', $entryId, $finalName, $origName, $mime, $size, $dbPath, $hash);
                            }
                            $stmtAtt->execute();
                            
                            $stmtUpd = $mysqli->prepare("UPDATE ticket_approvals SET status = 'pending' WHERE ticket_id = ? AND status = 'cotizacion'");
                            $stmtUpd->bind_param('i', $tid);
                            $stmtUpd->execute();
                            
                            addLog('executive_quote_sent', 'Cotización enviada al jefe de la organización', 'ticket', $tid, 'staff', $staffId);
                            
                            $stmtBoss = $mysqli->prepare("SELECT u.id, u.email, u.firstname, u.lastname FROM user_organizations uo JOIN users u ON u.id = uo.user_id WHERE uo.organization_id = (SELECT organization_id FROM user_organizations WHERE user_id = ? LIMIT 1) AND u.org_tickets_view = 1 AND u.empresa_id = ? LIMIT 1");
                            $bossRow = null;
                            if ($stmtBoss) {
                                $stmtBoss->bind_param('ii', $ticketView['user_id'], $eid);
                                if ($stmtBoss->execute()) {
                                    $bossRow = $stmtBoss->get_result()->fetch_assoc();
                                }
                            }
                            if ($bossRow) {
                                $bossEmail = trim((string)($bossRow['email'] ?? ''));
                                if ($bossEmail !== '' && filter_var($bossEmail, FILTER_VALIDATE_EMAIL)) {
                                    $bossName = trim($bossRow['firstname'] . ' ' . $bossRow['lastname']);
                                    $ticketNo = $ticketView['ticket_number'];
                                    $subjBoss = "[Revisión requerida] Cotización adjunta para el Ticket #" . $ticketNo;
                                    $orgPortalUrl = rtrim((string)(defined('APP_URL') ? APP_URL : ''), '/') . '/upload/view-ticket.php?from=org&id=' . $tid;
                                    
                                    $bossBodyHtml = '<div style="font-family: Segoe UI, sans-serif; max-width: 700px; margin: 0 auto;">'
                                        . '<h2 style="color:#1e3a5f; margin: 0 0 8px;">Cotización Lista para Revisión</h2>'
                                        . '<p style="color:#475569; margin: 0 0 12px;">Estimado/a <strong>' . htmlspecialchars($bossName) . '</strong>, el agente ha enviado la cotización solicitada para el ticket:</p>'
                                        . '<table style="width:100%; border-collapse: collapse; margin: 12px 0;">'
                                        . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee; width: 100px;"><strong>Número:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">#' . htmlspecialchars($ticketNo) . '</td></tr>'
                                        . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee;"><strong>Asunto:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">' . htmlspecialchars((string)$ticketView['subject']) . '</td></tr>'
                                        . '</table>';
                                    if ($quoteMsg !== '') {
                                        $bossBodyHtml .= '<div style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:8px; margin: 12px 0;">' . nl2br(htmlspecialchars($quoteMsg)) . '</div>';
                                    }
                                    $bossBodyHtml .= '<p style="margin: 14px 0 0;"><a href="' . htmlspecialchars($orgPortalUrl) . '" style="display:inline-block; background:#2563eb; color:#fff; padding:10px 16px; text-decoration:none; border-radius:8px;">Revisar y Autorizar</a></p>'
                                        . '<p style="color:#94a3b8; font-size:12px; margin-top: 14px;">' . htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets') . '</p>'
                                        . '</div>';
                                        
                                    $bossBodyText = "Cotización Lista para Revisión\n\nTicket: #" . $ticketNo . "\nAsunto: " . $ticketView['subject'] . "\n\nPor favor revise el archivo adjunto y autorice el ticket accediendo al siguiente enlace:\n" . $orgPortalUrl;
                                    
                                    $fileContent = file_get_contents($finalPath);
                                    if ($fileContent !== false) {
                                        if (function_exists('enqueueEmailJob')) {
                                            enqueueEmailJob($bossEmail, $subjBoss, $bossBodyHtml, $bossBodyText, [
                                                'empresa_id' => (int)$eid,
                                                'context_type' => 'ticket_quote_sent',
                                                'context_id' => (int)$tid,
                                                'attachments' => [[
                                                    'filename' => $origName,
                                                    'contentType' => $mime,
                                                    'content' => $fileContent,
                                                ]]
                                            ]);
                                            if (function_exists('triggerEmailQueueWorkerAsync')) {
                                                triggerEmailQueueWorkerAsync();
                                            }
                                        } else {
                                            Mailer::sendWithOptions($bossEmail, $subjBoss, $bossBodyHtml, $bossBodyText, [
                                                'attachments' => [[
                                                    'filename' => $origName,
                                                    'contentType' => $mime,
                                                    'content' => base64_encode($fileContent), // Or depending on implementation
                                                ]]
                                            ]);
                                        }
                                    }
                                }
                            }
                            $msg = 'quote_sent';
                        }
                    }
                }
                header("Location: tickets.php?id=$tid&msg=$msg");
                exit;
            } elseif ($action === 'assign') {
                requireRolePermission('ticket.assign', 'tickets.php?id=' . $tid);
                $staff_id = isset($_GET['staff_id']) ? (int) $_GET['staff_id'] : (isset($_POST['staff_id']) ? (int) $_POST['staff_id'] : null);
                if ($staff_id !== null) {
                    $val = $staff_id === 0 ? null : $staff_id;
                    $previousStaffId = $ticketView['staff_id'] ?? null;

                    // Validar que el agente pertenezca al mismo departamento del ticket (o sea General)
                    $allowed = true;
                    if ($val !== null) {
                        $tdept = (int) ($ticketView['dept_id'] ?? 0);
                        if ($tdept > 0) {
                            $allowed = $staffBelongsToDept((int)$val, $tdept, (int)$generalDeptId);
                        }
                    }

                    if ($allowed) {
                        $stmt = $mysqli->prepare("UPDATE tickets SET staff_id = ?, updated = NOW() WHERE id = ? AND empresa_id = ?");
                        $stmt->bind_param('iii', $val, $tid, $eid);
                        $ok = $stmt->execute();
                        $msg = 'assigned';
                    } else {
                        $ok = false;
                        $msg = 'assigned';
                    }

                    // Notificación en BD (solo si se asignó a alguien y cambió)
                    if ($ok && $val !== null && (string)$previousStaffId !== (string)$val) {
                        $ticketNo = (string)($ticketView['ticket_number'] ?? ('#' . $tid));
                        $message = 'Se te asignó el ticket ' . $ticketNo . ': ' . (string)($ticketView['subject'] ?? '');
                        $type = 'ticket_assigned';
                        $stmtN = $mysqli->prepare('INSERT INTO notifications (staff_id, message, type, related_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
                        if ($stmtN) {
                            $stmtN->bind_param('issi', $val, $message, $type, $tid);
                            $stmtN->execute();
                        }
                    }

                    // Enviar email al agente asignado (solo si se asignó a alguien y cambió)
                    if ($ok && $val !== null && (string)$previousStaffId !== (string)$val) {
                        $stmtS = $mysqli->prepare('SELECT email, firstname, lastname FROM staff WHERE empresa_id = ? AND id = ? AND is_active = 1 LIMIT 1');
                        $stmtS->bind_param('ii', $eid, $val);
                        if ($stmtS->execute()) {
                            $srow = $stmtS->get_result()->fetch_assoc();
                            $to = trim((string)($srow['email'] ?? ''));
                            $emailEnabled = ((string)getAppSetting('staff.' . (int)$val . '.email_ticket_assigned', '1') === '1');
                            if ($emailEnabled && $to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                                $ticketNo = (string)($ticketView['ticket_number'] ?? ('#' . $tid));
                                $subj = '[Asignación] ' . $ticketNo . ' - ' . (string)($ticketView['subject'] ?? 'Ticket');
                                $staffName = trim((string)($srow['firstname'] ?? '') . ' ' . (string)($srow['lastname'] ?? ''));
                                if ($staffName === '') $staffName = 'Agente';
                                $viewUrl = (defined('APP_URL') ? APP_URL : '') . '/upload/scp/tickets.php?id=' . (int) $tid;
                                $bodyHtml = '<div style="font-family: Segoe UI, sans-serif; max-width: 700px; margin: 0 auto;">'
                                    . '<h2 style="color:#1e3a5f; margin: 0 0 8px;">Se te asignó un ticket</h2>'
                                    . '<p style="color:#475569; margin: 0 0 12px;">Hola <strong>' . htmlspecialchars($staffName) . '</strong>, se te asignó el siguiente ticket:</p>'
                                    . '<table style="width:100%; border-collapse: collapse; margin: 12px 0;">'
                                    . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee;"><strong>Número:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">' . htmlspecialchars($ticketNo) . '</td></tr>'
                                    . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee;"><strong>Asunto:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">' . htmlspecialchars((string)($ticketView['subject'] ?? '')) . '</td></tr>'
                                    . '<tr><td style="padding: 6px 0;"><strong>Departamento:</strong></td><td style="padding: 6px 0;">' . htmlspecialchars((string)($ticketView['dept_name'] ?? '')) . '</td></tr>'
                                    . '</table>'
                                    . '<p style="margin: 14px 0 0;"><a href="' . htmlspecialchars($viewUrl) . '" style="display:inline-block; background:#2563eb; color:#fff; padding:10px 16px; text-decoration:none; border-radius:8px;">Ver ticket</a></p>'
                                    . '<p style="color:#94a3b8; font-size:12px; margin-top: 14px;">' . htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets') . '</p>'
                                    . '</div>';

                                $bodyText = 'Se te asignó un ticket: ' . $ticketNo . "\n" . 'Asunto: ' . (string)($ticketView['subject'] ?? '') . "\n" . 'Ver: ' . $viewUrl;
                                if (function_exists('enqueueEmailJob')) {
                                    enqueueEmailJob($to, $subj, $bodyHtml, $bodyText, [
                                        'empresa_id' => (int)$eid,
                                        'context_type' => 'ticket_assigned_staff',
                                        'context_id' => (int)$tid,
                                    ]);
                                    if (function_exists('triggerEmailQueueWorkerAsync')) {
                                        triggerEmailQueueWorkerAsync();
                                    }
                                } else {
                                    Mailer::send($to, $subj, $bodyHtml, $bodyText);
                                }
                            }
                        }
                    }
                }
            } elseif ($action === 'mark_answered') {
                requireRolePermission('ticket.markanswered', 'tickets.php?id=' . $tid);
                $resolved_id = 4;
                $stmt = $mysqli->prepare("SELECT id FROM ticket_status WHERE LOWER(name) LIKE ? OR LOWER(name) LIKE ? LIMIT 1");
                if ($stmt) {
                    $like1 = '%resuelto%';
                    $like2 = '%contestado%';
                    $stmt->bind_param('ss', $like1, $like2);
                    if ($stmt->execute()) {
                        $row = $stmt->get_result()->fetch_assoc();
                        if ($row) {
                            $resolved_id = (int) ($row['id'] ?? 4);
                        }
                    }
                }
                $stmt = $mysqli->prepare("UPDATE tickets SET status_id = ?, updated = NOW() WHERE id = ? AND empresa_id = ?");
                $stmt->bind_param('iii', $resolved_id, $tid, $eid);
                $ok = $stmt->execute();
                $msg = 'marked';
            } elseif ($action === 'transfer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                requireRolePermission('ticket.transfer', 'tickets.php?id=' . $tid);
                $newDeptId = isset($_POST['dept_id']) && is_numeric($_POST['dept_id']) ? (int) $_POST['dept_id'] : 0;
                if ($newDeptId > 0) {
                    // Validar departamento destino
                    $deptOk = false;
                    $stmtD = $mysqli->prepare('SELECT id FROM departments WHERE empresa_id = ? AND id = ? AND is_active = 1 LIMIT 1');
                    if ($stmtD) {
                        $stmtD->bind_param('ii', $eid, $newDeptId);
                        if ($stmtD->execute()) {
                            $deptOk = (bool) $stmtD->get_result()->fetch_assoc();
                        }
                    }

                    if ($deptOk) {
                        // Si el ticket está asignado, validar que el agente siga siendo válido para el nuevo dept
                        $currentStaffId = isset($ticketView['staff_id']) && is_numeric($ticketView['staff_id']) ? (int) $ticketView['staff_id'] : 0;
                        $unassign = false;
                        if ($currentStaffId > 0) {
                            $unassign = !$staffBelongsToDept((int)$currentStaffId, (int)$newDeptId, (int)$generalDeptId);
                        }

                        if ($unassign) {
                            $stmtUp = $mysqli->prepare('UPDATE tickets SET dept_id = ?, staff_id = NULL, updated = NOW() WHERE id = ? AND empresa_id = ?');
                            if ($stmtUp) {
                                $stmtUp->bind_param('iii', $newDeptId, $tid, $eid);
                                $ok = $stmtUp->execute();
                            }
                        } else {
                            $stmtUp = $mysqli->prepare('UPDATE tickets SET dept_id = ?, updated = NOW() WHERE id = ? AND empresa_id = ?');
                            if ($stmtUp) {
                                $stmtUp->bind_param('iii', $newDeptId, $tid, $eid);
                                $ok = $stmtUp->execute();
                            }
                        }
                        $msg = 'transferred';
                    }
                }
            } elseif ($action === 'owner' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
                requireRolePermission('ticket.edit', 'tickets.php?id=' . $tid);
                $uid = (int) $_POST['user_id'];
                $stmt = $mysqli->prepare("UPDATE tickets SET user_id = ?, updated = NOW() WHERE id = ? AND empresa_id = ?");
                $stmt->bind_param('iii', $uid, $tid, $eid);
                $ok = $stmt->execute();
                $msg = 'owner';
            } elseif ($action === 'update_support_times' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                requireRolePermission('ticket.edit', 'tickets.php?id=' . $tid);
                $supportStart = !empty($_POST['support_start']) ? $_POST['support_start'] : null;
                $supportEnd = !empty($_POST['support_end']) ? $_POST['support_end'] : null;
                
                $stmt = $mysqli->prepare("UPDATE tickets SET support_start = ?, support_end = ?, updated = NOW() WHERE id = ? AND empresa_id = ?");
                if ($stmt) {
                    $stmt->bind_param('ssii', $supportStart, $supportEnd, $tid, $eid);
                    $ok = $stmt->execute();
                    $msg = 'times_updated';
                }
            } elseif ($action === 'referral_add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                requireRolePermission('ticket.edit', 'tickets.php?id=' . $tid);
                $refStaffId = isset($_POST['ref_staff_id']) && is_numeric($_POST['ref_staff_id']) ? (int)$_POST['ref_staff_id'] : null;
                if ($refStaffId === 0) $refStaffId = null;
                $refDeptId = isset($_POST['ref_dept_id']) && is_numeric($_POST['ref_dept_id']) ? (int)$_POST['ref_dept_id'] : null;
                if ($refDeptId === 0) $refDeptId = null;
                
                if ($refStaffId || $refDeptId) {
                    $stmtIns = $mysqli->prepare("INSERT INTO ticket_referrals (ticket_id, staff_id, dept_id) VALUES (?, ?, ?)");
                    $stmtIns->bind_param('iii', $tid, $refStaffId, $refDeptId);
                    $ok = $stmtIns->execute();
                    $msg = 'referral_added';
                }
            } elseif ($action === 'referral_delete' && isset($_GET['ref_id']) && is_numeric($_GET['ref_id'])) {
                requireRolePermission('ticket.edit', 'tickets.php?id=' . $tid);
                $refId = (int)$_GET['ref_id'];
                $stmtDel = $mysqli->prepare("DELETE FROM ticket_referrals WHERE id = ? AND ticket_id = ?");
                $stmtDel->bind_param('ii', $refId, $tid);
                $ok = $stmtDel->execute();
                $msg = 'referral_deleted';
            } elseif ($action === 'priority_update' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['priority_id']) && is_numeric($_POST['priority_id'])) {
                requireRolePermission('ticket.edit', 'tickets.php?id=' . $tid);
                $pid = (int) $_POST['priority_id'];
                $stmt = $mysqli->prepare("UPDATE tickets SET priority_id = ?, updated = NOW() WHERE id = ? AND empresa_id = ?");
                $stmt->bind_param('iii', $pid, $tid, $eid);
                $ok = $stmt->execute();
                $msg = 'priority_updated';
            } elseif ($action === 'block_email' && ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['confirm']) && $_GET['confirm'] === '1')) {
                requireRolePermission('ticket.edit', 'tickets.php?id=' . $tid);
                $email = $ticketView['user_email'] ?? '';
                if ($email) {
                    $stmt = $mysqli->prepare("UPDATE users SET status = 'banned', updated = NOW() WHERE empresa_id = ? AND id = ?");
                    $uid = (int)($ticketView['user_id'] ?? 0);
                    $stmt->bind_param('ii', $eid, $uid);
                    $ok = $stmt->execute();

                    // La tabla banlist ya existe en producción.

                    $emailNorm = strtolower(trim((string)$email));
                    if ($emailNorm !== '' && filter_var($emailNorm, FILTER_VALIDATE_EMAIL)) {
                        $existsB = false;
                        $stmtB = $mysqli->prepare('SELECT id FROM banlist WHERE email = ? LIMIT 1');
                        if ($stmtB) {
                            $stmtB->bind_param('s', $emailNorm);
                            if ($stmtB->execute()) {
                                $existsB = (bool)$stmtB->get_result()->fetch_assoc();
                            }
                        }

                        if (!$existsB) {
                            $note = 'Bloqueado desde ticket #' . (string)$tid;
                            $stmtI = $mysqli->prepare('INSERT INTO banlist (email, domain, notes, is_active, created, updated) VALUES (?, NULL, ?, 0, NOW(), NOW())');
                            if ($stmtI) {
                                $stmtI->bind_param('ss', $emailNorm, $note);
                                $stmtI->execute();
                            }
                        }
                    }

                    $msg = 'blocked';
                }
            } elseif ($action === 'merge' && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim($_POST['target_ticket_id'] ?? ''))) {
                requireRolePermission('ticket.merge', 'tickets.php?id=' . $tid);
                $target_input = trim($_POST['target_ticket_id']);
                $target_id = 0;
                
                // Primero buscar por número de ticket (que es lo que el usuario suele ingresar)
                $stmtFind = $mysqli->prepare("SELECT id FROM tickets WHERE empresa_id = ? AND ticket_number = ? LIMIT 1");
                if ($stmtFind) {
                    $stmtFind->bind_param('is', $eid, $target_input);
                    $stmtFind->execute();
                    $r = $stmtFind->get_result()->fetch_assoc();
                    if ($r) $target_id = (int) $r['id'];
                }

                // Si no se encuentra y el input es numérico, probar como ID de base de datos
                if ($target_id === 0 && is_numeric($target_input)) {
                    $stmtFindId = $mysqli->prepare("SELECT id FROM tickets WHERE empresa_id = ? AND id = ? LIMIT 1");
                    if ($stmtFindId) {
                        $tid_int = (int)$target_input;
                        $stmtFindId->bind_param('ii', $eid, $tid_int);
                        $stmtFindId->execute();
                        $r = $stmtFindId->get_result()->fetch_assoc();
                        if ($r) $target_id = (int) $r['id'];
                    }
                }
                if ($target_id <= 0) {
                    $_SESSION['flash_error'] = 'Ticket destino no encontrado.';
                    header('Location: tickets.php?id=' . $tid);
                    exit;
                }
                if ($target_id === $tid) {
                    $_SESSION['flash_error'] = 'No puedes unir un ticket consigo mismo.';
                    header('Location: tickets.php?id=' . $tid);
                    exit;
                }

                // Unir: mover mensajes de este ticket al ticket destino (sin alterar el número del destino)
                $ok = false;
                try {
                    if (method_exists($mysqli, 'begin_transaction')) {
                        $mysqli->begin_transaction();
                    }

                    // Obtener/crear thread del ticket destino
                    if ($threadsHasEmpresa) {
                        $stmtT = $mysqli->prepare('SELECT id FROM threads WHERE ticket_id = ? AND empresa_id = ? LIMIT 1');
                        $stmtT->bind_param('ii', $target_id, $eid);
                    } else {
                        $stmtT = $mysqli->prepare('SELECT id FROM threads WHERE ticket_id = ? LIMIT 1');
                        $stmtT->bind_param('i', $target_id);
                    }
                    $stmtT->execute();
                    $targetThreadRow = $stmtT->get_result()->fetch_assoc();
                    $target_thread_id = (int)($targetThreadRow['id'] ?? 0);
                    if ($target_thread_id <= 0) {
                        if ($threadsHasEmpresa) {
                            $stmtNewTh = $mysqli->prepare('INSERT INTO threads (empresa_id, ticket_id, created) VALUES (?, ?, NOW())');
                            if ($stmtNewTh) {
                                $stmtNewTh->bind_param('ii', $eid, $target_id);
                                $stmtNewTh->execute();
                                $target_thread_id = (int)$mysqli->insert_id;
                            }
                        } else {
                            $stmtNewTh = $mysqli->prepare('INSERT INTO threads (ticket_id, created) VALUES (?, NOW())');
                            if ($stmtNewTh) {
                                $stmtNewTh->bind_param('i', $target_id);
                                $stmtNewTh->execute();
                                $target_thread_id = (int)$mysqli->insert_id;
                            }
                        }
                    }

                    // Copiar entradas del thread origen al thread destino (sin borrar las originales)
                    $origin_thread_id = (int)($thread_id ?? 0);
                    if ($origin_thread_id > 0 && $target_thread_id > 0) {
                        if ($entriesHasEmpresa) {
                            $stmtCopy = $mysqli->prepare('INSERT INTO thread_entries (empresa_id, thread_id, user_id, staff_id, body, is_internal, created) SELECT ?, ?, user_id, staff_id, body, is_internal, created FROM thread_entries WHERE thread_id = ?');
                            $stmtCopy->bind_param('iii', $eid, $target_thread_id, $origin_thread_id);
                        } else {
                            $stmtCopy = $mysqli->prepare('INSERT INTO thread_entries (thread_id, user_id, staff_id, body, is_internal, created) SELECT ?, user_id, staff_id, body, is_internal, created FROM thread_entries WHERE thread_id = ?');
                            $stmtCopy->bind_param('ii', $target_thread_id, $origin_thread_id);
                        }
                        $stmtCopy->execute();
                    }

                    // Cerrar este ticket
                    $closed_id = 5;
                    $stmtClosed = $mysqli->prepare('SELECT id FROM ticket_status WHERE LOWER(name) LIKE ? LIMIT 1');
                    if ($stmtClosed) {
                        $likeClosed = '%cerrado%';
                        $stmtClosed->bind_param('s', $likeClosed);
                        if ($stmtClosed->execute()) {
                            $r = $stmtClosed->get_result()->fetch_assoc();
                            if ($r) $closed_id = (int)($r['id'] ?? 5);
                        }
                    }
                    $stmtUp = $mysqli->prepare('UPDATE tickets SET status_id = ?, closed = NOW(), updated = NOW() WHERE id = ? AND empresa_id = ?');
                    $stmtUp->bind_param('iii', $closed_id, $tid, $eid);
                    $stmtUp->execute();

                    // Crear vínculo entre origen y destino
                    if (!($ensureTicketLinksTable)()) {
                        throw new Exception('ticket_links');
                    }
                    
                    $linksHasEmpresa = dbColumnExists('ticket_links', 'empresa_id');

                    if ($linksHasEmpresa) {
                        $stmtL = $mysqli->prepare('INSERT IGNORE INTO ticket_links (empresa_id, ticket_id, linked_ticket_id) VALUES (?, ?, ?), (?, ?, ?)');
                        if ($stmtL) {
                            $stmtL->bind_param('iiiiii', $eid, $tid, $target_id, $eid, $target_id, $tid);
                            $stmtL->execute();
                        }
                    } else {
                        $stmtL = $mysqli->prepare('INSERT IGNORE INTO ticket_links (ticket_id, linked_ticket_id) VALUES (?, ?), (?, ?)');
                        if ($stmtL) {
                            $stmtL->bind_param('iiii', $tid, $target_id, $target_id, $tid);
                            $stmtL->execute();
                        }
                    }

                    if (method_exists($mysqli, 'commit')) {
                        $mysqli->commit();
                    }
                    $ok = true;
                } catch (Throwable $e) {
                    if (method_exists($mysqli, 'rollback')) {
                        $mysqli->rollback();
                    }
                    $_SESSION['flash_error'] = 'No se pudo unir el ticket.';
                    header('Location: tickets.php?id=' . $tid);
                    exit;
                }

                if ($ok) {
                    $_SESSION['flash_msg'] = 'Ticket unido: se copiaron los mensajes al ticket destino y este ticket se cerró.';
                    header('Location: tickets.php');
                    exit;
                }
            } elseif ($action === 'link' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['linked_ticket_id']) && is_numeric($_POST['linked_ticket_id'])) {
                requireRolePermission('ticket.link', 'tickets.php?id=' . $tid);
                $linked_id = (int) $_POST['linked_ticket_id'];
                if ($linked_id !== $tid && $linked_id > 0) {
                    if (!($ensureTicketLinksTable)()) {
                        $_SESSION['flash_error'] = 'No se pudo habilitar la tabla de tickets vinculados.';
                        header('Location: tickets.php?id=' . $tid);
                        exit;
                    }
                $linksHasEmpresa = dbColumnExists('ticket_links', 'empresa_id');

                if ($linksHasEmpresa) {
                    $stmt = $mysqli->prepare("INSERT IGNORE INTO ticket_links (empresa_id, ticket_id, linked_ticket_id) VALUES (?, ?, ?), (?, ?, ?)");
                    $stmt->bind_param('iiiiii', $eid, $tid, $linked_id, $eid, $linked_id, $tid);
                } else {
                    $stmt = $mysqli->prepare("INSERT IGNORE INTO ticket_links (ticket_id, linked_ticket_id) VALUES (?, ?), (?, ?)");
                    $stmt->bind_param('iiii', $tid, $linked_id, $linked_id, $tid);
                }
                $ok = $stmt->execute();
                $msg = 'linked';
                }
            } elseif ($action === 'unlink' && isset($_GET['linked_id']) && is_numeric($_GET['linked_id'])) {
                requireRolePermission('ticket.link', 'tickets.php?id=' . $tid);
                $linked_id = (int) $_GET['linked_id'];
                if (!($ensureTicketLinksTable)()) {
                    $_SESSION['flash_error'] = 'No se pudo habilitar la tabla de tickets vinculados.';
                    header('Location: tickets.php?id=' . $tid);
                    exit;
                }
                $stmt = $mysqli->prepare("DELETE FROM ticket_links WHERE (ticket_id = ? AND linked_ticket_id = ?) OR (ticket_id = ? AND linked_ticket_id = ?)");
                $stmt->bind_param('iiii', $tid, $linked_id, $linked_id, $tid);
                $ok = $stmt->execute();
                $msg = 'unlinked';
            } elseif ($action === 'collab_add' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
                requireRolePermission('ticket.edit', 'tickets.php?id=' . $tid);
                $uid = (int) $_POST['user_id'];
                if (dbTableExists('ticket_collaborators')) {
                    $userOk = false;
                    if ($uid > 0) {
                        $stmtU = $mysqli->prepare('SELECT id FROM users WHERE empresa_id = ? AND id = ? LIMIT 1');
                        if ($stmtU) {
                            $stmtU->bind_param('ii', $eid, $uid);
                            if ($stmtU->execute()) {
                                $userOk = (bool)$stmtU->get_result()->fetch_assoc();
                            }
                        }
                    }
                    if ($userOk) {
                        $stmt = $mysqli->prepare("INSERT IGNORE INTO ticket_collaborators (ticket_id, user_id) VALUES (?, ?)");
                        $stmt->bind_param('ii', $tid, $uid);
                        $ok = $stmt->execute();
                        $msg = 'collab_added';
                    }
                }
            } elseif ($action === 'collab_remove' && isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
                requireRolePermission('ticket.edit', 'tickets.php?id=' . $tid);
                $uid = (int) $_GET['user_id'];
                if (dbTableExists('ticket_collaborators')) {
                    $stmt = $mysqli->prepare("DELETE FROM ticket_collaborators WHERE ticket_id = ? AND user_id = ?");
                    $stmt->bind_param('ii', $tid, $uid);
                    $ok = $stmt->execute();
                    $msg = 'collab_removed';
                }
            } elseif ($action === 'delete_request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                requireRolePermission('ticket.delete', 'tickets.php?id=' . $tid);
                $reason = trim($_POST['delete_reason'] ?? '');
                if (empty($reason)) {
                    $_SESSION['flash_error'] = 'Debe especificar un motivo para el borrado.';
                } else {
                    $stmtDel = $mysqli->prepare("INSERT INTO ticket_deletion_requests (ticket_id, empresa_id, ticket_number, ticket_subject, requested_by, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                    $stmtDel->bind_param('iissis', $tid, $eid, $ticketView['ticket_number'], $ticketView['subject'], $_SESSION['staff_id'], $reason);
                    if ($stmtDel->execute()) {
                        $_SESSION['flash_msg'] = 'Solicitud de borrado enviada correctamente. El administrador revisará su petición.';
                    } else {
                        $_SESSION['flash_error'] = 'Error al enviar la solicitud.';
                    }
                }
                header('Location: tickets.php?id=' . $tid);
                exit;
            } elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                // Borrar ticket (desde modal)
                requireRolePermission('ticket.delete', 'tickets.php?id=' . $tid);
                $confirm = (string)($_POST['confirm'] ?? '');
                if ($confirm !== '1') {
                    $_SESSION['flash_error'] = 'Confirmación inválida.';
                    header('Location: tickets.php?id=' . $tid);
                    exit;
                }

                $deleted = false;
                try {
                    if (method_exists($mysqli, 'begin_transaction')) {
                        $mysqli->begin_transaction();
                    }

                    // Si existen tablas de threads/entries, borrarlas explícitamente para instalaciones sin FKs
                    $threadsOk = dbTableExists('threads');
                    $entriesOk = dbTableExists('thread_entries');

                    if ($threadsOk && $entriesOk) {
                        $stmtDelEntries = $mysqli->prepare(
                            'DELETE te FROM thread_entries te JOIN threads th ON th.id = te.thread_id WHERE th.ticket_id = ?'
                        );
                        if ($stmtDelEntries) {
                            $stmtDelEntries->bind_param('i', $tid);
                            $stmtDelEntries->execute();
                        }
                    }

                    if ($threadsOk) {
                        $stmtDelThreads = $mysqli->prepare('DELETE FROM threads WHERE ticket_id = ?');
                        if ($stmtDelThreads) {
                            $stmtDelThreads->bind_param('i', $tid);
                            $stmtDelThreads->execute();
                        }
                    }

                    $stmtDelTicket = $mysqli->prepare('DELETE FROM tickets WHERE id = ? AND empresa_id = ?');
                    if (!$stmtDelTicket) {
                        throw new Exception('No se pudo preparar la eliminación.');
                    }
                    $stmtDelTicket->bind_param('ii', $tid, $eid);
                    $deleted = (bool)$stmtDelTicket->execute();

                    if (!$deleted) {
                        throw new Exception('No se pudo eliminar el ticket.');
                    }

                    // Registrar el borrado administrativo para historial
                    $reason = trim($_POST['delete_reason'] ?? 'Sin motivo especificado (Admin)');
                    $stmtLog = $mysqli->prepare("INSERT INTO ticket_deletion_requests (ticket_id, empresa_id, ticket_number, ticket_subject, requested_by, reason, status, resolved_at, resolved_by) VALUES (?, ?, ?, ?, ?, ?, 'approved', NOW(), ?)");
                    $stmtLog->bind_param('iissisi', $tid, $eid, $ticketView['ticket_number'], $ticketView['subject'], $_SESSION['staff_id'], $reason, $_SESSION['staff_id']);
                    $stmtLog->execute();

                    if (method_exists($mysqli, 'commit')) {
                        $mysqli->commit();
                    }
                } catch (Throwable $e) {
                    if (method_exists($mysqli, 'rollback')) {
                        $mysqli->rollback();
                    }
                    $deleted = false;
                }

                if ($deleted) {
                    $_SESSION['flash_msg'] = 'Ticket eliminado correctamente.';
                    header('Location: tickets.php');
                    exit;
                }

                $_SESSION['flash_error'] = 'No se pudo eliminar el ticket.';
                header('Location: tickets.php?id=' . $tid);
                exit;
            } elseif ($action === 'edit_entry' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                requireRolePermission('ticket.edit', 'tickets.php?id=' . $tid);
                $entryId = (int)($_POST['entry_id'] ?? 0);
                $body = trim($_POST['body'] ?? '');
                
                $sqlCheck = 'SELECT id, staff_id FROM thread_entries WHERE id = ? AND thread_id = ?';
                if ($entriesHasEmpresa) $sqlCheck .= ' AND empresa_id = ?';
                $sqlCheck .= ' LIMIT 1';

                $stmtCheck = $mysqli->prepare($sqlCheck);
                if ($entriesHasEmpresa) {
                    $stmtCheck->bind_param('iii', $entryId, $thread_id, $eid);
                } else {
                    $stmtCheck->bind_param('ii', $entryId, $thread_id);
                }
                $stmtCheck->execute();
                $entryData = $stmtCheck->get_result()->fetch_assoc();
                
                if ($entryData) {
                    $entryStaffId = (int)($entryData['staff_id'] ?? 0);
                    if ($entryStaffId > 0 && (roleHasPermission('ticket.edit') || $entryStaffId === (int)$_SESSION['staff_id'])) {
                        $stmtUpdate = $mysqli->prepare('UPDATE thread_entries SET body = ? WHERE id = ?');
                        $stmtUpdate->bind_param('si', $body, $entryId);
                        if ($stmtUpdate->execute()) {
                            addLog('ticket_entry_edited', "Editado mensaje ID $entryId", 'ticket', $tid);
                            $_SESSION['flash_msg'] = 'Mensaje actualizado correctamente.';
                        } else {
                            $_SESSION['flash_error'] = 'Error al actualizar el mensaje: ' . $mysqli->error;
                        }
                        
                        if (!empty($_POST['delete_attachments']) && is_array($_POST['delete_attachments'])) {
                            foreach ($_POST['delete_attachments'] as $attId) {
                                $attId = (int)$attId;
                                $stmtAtt = $mysqli->prepare('SELECT id, path FROM attachments WHERE id = ? AND thread_entry_id = ? LIMIT 1');
                                $stmtAtt->bind_param('ii', $attId, $entryId);
                                if ($stmtAtt->execute()) {
                                    $att = $stmtAtt->get_result()->fetch_assoc();
                                    if ($att) {
                                        $fullPath = dirname(__DIR__, 4) . '/' . $att['path'];
                                        if (file_exists($fullPath)) @unlink($fullPath);
                                        $mysqli->query("DELETE FROM attachments WHERE id = $attId");
                                    }
                                }
                            }
                        }
                        
                        if (!empty($_FILES['attachments']['name'][0])) {
                            $uploadDir = defined('ATTACHMENTS_DIR') ? ATTACHMENTS_DIR : dirname(__DIR__, 4) . '/uploads/attachments';
                            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
                            $allowedExt = [
                                'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp',
                                'pdf'=>'application/pdf','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'txt'=>'text/plain',
                                'mp4'=>'video/mp4','webm'=>'video/webm','mov'=>'video/quicktime','mkv'=>'video/x-matroska'
                            ];
                            $files = $_FILES['attachments'];
                            $n = is_array($files['name']) ? count($files['name']) : 1;
                            for ($i = 0; $i < $n; $i++) {
                                $err = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                                if ($err !== UPLOAD_ERR_OK) continue;
                                $orig = (string)$files['name'][$i];
                                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                                if (!isset($allowedExt[$ext])) continue;
                                $safeName = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
                                $path = $uploadDir . '/' . $safeName;
                                if (move_uploaded_file($files['tmp_name'][$i], $path)) {
                                    $relPath = 'uploads/attachments/' . $safeName;
                                    $hash = @hash_file('sha256', $path) ?: '';
                                    $mime = $allowedExt[$ext];
                                    $size = $files['size'][$i];
                                    $stmtA = $mysqli->prepare('INSERT INTO attachments (empresa_id, thread_entry_id, filename, original_filename, mimetype, size, path, hash, created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                                    $stmtA->bind_param('iisssiss', $eid, $entryId, $safeName, $orig, $mime, $size, $relPath, $hash);
                                    $stmtA->execute();
                                }
                            }
                        }
                        
                        addLog('ticket_entry_edited', "Editado mensaje ID $entryId", 'ticket', $tid);
                        $_SESSION['flash_msg'] = 'Mensaje actualizado correctamente.';
                    }
                }
                header('Location: tickets.php?id=' . $tid);
                exit;
            } elseif ($action === 'delete_entry' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                requireRolePermission('ticket.delete', 'tickets.php?id=' . $tid);
                $entryId = (int)($_POST['entry_id'] ?? 0);
                
                $sqlCheck = 'SELECT id, staff_id FROM thread_entries WHERE id = ? AND thread_id = ?';
                if ($entriesHasEmpresa) $sqlCheck .= ' AND empresa_id = ?';
                $sqlCheck .= ' LIMIT 1';

                $stmtCheck = $mysqli->prepare($sqlCheck);
                if ($entriesHasEmpresa) {
                    $stmtCheck->bind_param('iii', $entryId, $thread_id, $eid);
                } else {
                    $stmtCheck->bind_param('ii', $entryId, $thread_id);
                }
                $stmtCheck->execute();
                $entryData = $stmtCheck->get_result()->fetch_assoc();
                if ($entryData && (int)($entryData['staff_id'] ?? 0) > 0) {
                    $stmtAtts = $mysqli->prepare('SELECT id, path FROM attachments WHERE thread_entry_id = ?');
                    $stmtAtts->bind_param('i', $entryId);
                    if ($stmtAtts->execute()) {
                        $resAtts = $stmtAtts->get_result();
                        while ($att = $resAtts->fetch_assoc()) {
                            $fullPath = dirname(__DIR__, 4) . '/' . $att['path'];
                            if (file_exists($fullPath)) @unlink($fullPath);
                            $mysqli->query("DELETE FROM attachments WHERE id = " . (int)$att['id']);
                        }
                    }
                    $mysqli->query("DELETE FROM thread_entries WHERE id = $entryId");
                    addLog('ticket_entry_deleted', "Eliminado mensaje ID $entryId", 'ticket', $tid);
                    $_SESSION['flash_msg'] = 'Mensaje eliminado correctamente.';
                }
                header('Location: tickets.php?id=' . $tid);
                exit;
            }
            if ($ok && $msg && !in_array($action, ['delete', 'merge'], true)) {
                if ($msg === 'updated' && $isClosingStatus && (int)($ticketView['requires_report'] ?? 0) === 1 && roleHasPermission('ticket.reports')) {
                    if (($ticketView['approval_status'] ?? '') !== 'rechazado') {
                        $msg = 'closed_report';
                    }
                }
                header('Location: tickets.php?id=' . $tid . '&msg=' . $msg);
                exit;
            }
        }

        // Procesar respuesta o nota interna (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do'])) {
            if (!isset($_POST['csrf_token']) || !Auth::validateCSRF($_POST['csrf_token'])) {
                $reply_errors[] = 'Token de seguridad inválido.';
            } else {
                $body = trim($_POST['body'] ?? '');
                $is_internal = isset($_POST['do']) && $_POST['do'] === 'internal';
                if ($is_internal) {
                    requireRolePermission('ticket.post', 'tickets.php?id=' . $tid);
                } else {
                    requireRolePermission('ticket.reply', 'tickets.php?id=' . $tid);
                }
                error_log('[tickets] reply POST scp/modules/tickets.php uri=' . ($_SERVER['REQUEST_URI'] ?? '') . ' tid=' . (string)$tid . ' staff_session=' . (string)($_SESSION['staff_id'] ?? '') . ' internal=' . ($is_internal ? '1' : '0'));
                $new_status_id = isset($_POST['status_id']) && is_numeric($_POST['status_id']) ? (int) $_POST['status_id'] : (int) $ticketView['status_id'];
                $signature_mode = trim($_POST['signature'] ?? 'none');
                $hasAttachments = !empty($_FILES['attachments']['name'][0]);
                if ($body === '' && !$hasAttachments) {
                    $reply_errors[] = 'El mensaje no puede estar vacío (o debe adjuntar al menos un archivo).';
                } else {
                    $staff_id = (int) ($_SESSION['staff_id'] ?? 0);

                    if ($entriesHasEmpresa) {
                        $stmt = $mysqli->prepare(
                            "INSERT INTO thread_entries (empresa_id, thread_id, staff_id, body, is_internal, created) VALUES (?, ?, ?, ?, ?, NOW())"
                        );
                        $stmt->bind_param('iiisi', $eid, $thread_id, $staff_id, $body, $is_internal);
                    } else {
                        $stmt = $mysqli->prepare(
                            "INSERT INTO thread_entries (thread_id, staff_id, body, is_internal, created) VALUES (?, ?, ?, ?, NOW())"
                        );
                        $stmt->bind_param('iisi', $thread_id, $staff_id, $body, $is_internal);
                    }
                    if ($stmt->execute()) {
                        $entry_id = (int) $mysqli->insert_id;

                        // Auto status: when staff replies publicly and ticket is Open, move to In Progress
                        // Only if staff didn't change the status manually in the form.
                        try {
                            $currentStatusId = (int)($ticketView['status_id'] ?? 0);
                            if (!$is_internal
                                && $statusIdOpen > 0
                                && $statusIdInProgress > 0
                                && $currentStatusId === $statusIdOpen
                                && $new_status_id === $currentStatusId
                            ) {
                                // $new_status_id = $statusIdInProgress; // DESHABILITADO PARA QUE NO SEA AUTOMÁTICO
                            }
                        } catch (Throwable $e) {
                        }

                        // Notificar al cliente (solo respuestas públicas)
                        if (!$is_internal) {
                            try {
                                $hasUserNotifs = dbTableExists('user_notifications');
                                if ($hasUserNotifs) {
                                    $uidOwner = (int)($ticketView['user_id'] ?? 0);
                                    if ($uidOwner > 0) {
                                        $msgNotif = 'Respuesta nueva · Ticket #' . (string)($ticketView['ticket_number'] ?? '');
                                        $typeNotif = 'ticket_reply';
                                        $stmtUn = $mysqli->prepare('INSERT INTO user_notifications (empresa_id, user_id, type, message, ticket_id, thread_entry_id, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())');
                                        if ($stmtUn) {
                                            $stmtUn->bind_param('iissii', $eid, $uidOwner, $typeNotif, $msgNotif, $tid, $entry_id);
                                            $stmtUn->execute();
                                        }
                                    }
                                }
                            } catch (Throwable $e) {
                            }
                        }

                        // Actualizar estado del ticket
                        $isClosingStatus = false;
                        $stmtSt = $mysqli->prepare('SELECT name FROM ticket_status WHERE id = ? LIMIT 1');
                        if ($stmtSt) {
                            $stmtSt->bind_param('i', $new_status_id);
                            if ($stmtSt->execute()) {
                                $stRow = $stmtSt->get_result()->fetch_assoc();
                                $stName = strtolower(trim((string)($stRow['name'] ?? '')));
                                if ($stName !== '' && (str_contains($stName, 'cerrad') || str_contains($stName, 'closed'))) {
                                    $isClosingStatus = true;
                                }
                            }
                        }

                        if ($isClosingStatus) {
                            $stmtU = $mysqli->prepare("UPDATE tickets SET status_id = ?, closed = NOW(), updated = NOW() WHERE id = ? AND empresa_id = ?");
                        } else {
                            $stmtU = $mysqli->prepare("UPDATE tickets SET status_id = ?, closed = NULL, updated = NOW() WHERE id = ? AND empresa_id = ?");
                        }
                        $stmtU->bind_param('iii', $new_status_id, $tid, $eid);
                        $stmtU->execute();
                        
                        // Si cambió a En Camino (id=2), notificar al usuario
                        if ($new_status_id === 2 && (int)($ticketView['status_id'] ?? 0) !== 2) {
                            $toClient = trim((string)($ticketView['user_email'] ?? ''));
                            if ($toClient !== '' && filter_var($toClient, FILTER_VALIDATE_EMAIL)) {
                                $ticketNo = (string)($ticketView['ticket_number'] ?? ('#' . $tid));
                                $ticketSubject = (string)($ticketView['subject'] ?? '');
                                $ticketTopic = (string)($ticketView['topic_name'] ?? 'General');
                                $subjClient = '[Ticket En Camino] ' . $ticketNo . ' - ' . $ticketSubject;
                                
                                $bodyHtmlClient = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
                                    . '<h2 style="margin:0 0 10px;color:#1e3a5f;">Técnicos en camino</h2>'
                                    . '<p>Estimado usuario, los técnicos ya van en camino para atender su solicitud.</p>'
                                    . '<div style="background:#f8fafc; border:1px solid #e2e8f0; padding:14px; border-radius:10px; margin-top:14px;">'
                                    . '<p style="margin:0 0 8px;"><strong>ID del Ticket:</strong> ' . html($ticketNo) . '</p>'
                                    . '<p style="margin:0 0 8px;"><strong>Tema:</strong> ' . html($ticketTopic) . '</p>'
                                    . '<p style="margin:0;"><strong>Asunto:</strong> ' . html($ticketSubject) . '</p>'
                                    . '</div>'
                                    . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . html((string)(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets')) . '</p>'
                                    . '</div>';
                                $bodyTextClient = "Estimado usuario, los técnicos ya van en camino para atender su solicitud.\n\n"
                                    . "ID del Ticket: $ticketNo\n"
                                    . "Tema: $ticketTopic\n"
                                    . "Asunto: $ticketSubject";
                                if (function_exists('enqueueEmailJob')) {
                                    enqueueEmailJob($toClient, $subjClient, $bodyHtmlClient, $bodyTextClient, [
                                        'empresa_id' => (int)$eid,
                                        'context_type' => 'ticket_en_camino_client',
                                        'context_id' => (int)$tid,
                                    ]);
                                    if (function_exists('triggerEmailQueueWorkerAsync')) {
                                        triggerEmailQueueWorkerAsync();
                                    }
                                } else {
                                    Mailer::send($toClient, $subjClient, $bodyHtmlClient, $bodyTextClient);
                                }
                                addLog('ticket_en_camino_email', 'Notificación En Camino encolada/enviada al usuario ' . $toClient, 'ticket', $tid);
                            }
                            notifyStatusChangeToAdminRecipients($tid, 'En Camino');
                        }

                        // Si cambió a En Proceso (id=3), notificar al usuario
                        if ($new_status_id === 3 && (int)($ticketView['status_id'] ?? 0) !== 3) {
                            $toClient = trim((string)($ticketView['user_email'] ?? ''));
                            if ($toClient !== '' && filter_var($toClient, FILTER_VALIDATE_EMAIL)) {
                                $ticketNo = (string)($ticketView['ticket_number'] ?? ('#' . $tid));
                                $ticketSubject = (string)($ticketView['subject'] ?? '');
                                $ticketTopic = (string)($ticketView['topic_name'] ?? 'General');
                                $subjClient = '[Ticket En Proceso] ' . $ticketNo . ' - ' . $ticketSubject;
                                
                                $viewUrl = (defined('APP_URL') ? APP_URL : '') . '/upload/tickets.php?id=' . (int) $tid;
                                $bodyHtmlClient = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:0 auto;">'
                                    . '<h2 style="margin:0 0 10px;color:#1e3a5f;">Técnicos han llegado al lugar</h2>'
                                    . '<p>Estimado usuario, los técnicos han llegado al lugar o su ticket está en proceso.</p>'
                                    . '<div style="background:#f8fafc; border:1px solid #e2e8f0; padding:14px; border-radius:10px; margin-top:14px;">'
                                    . '<p style="margin:0 0 8px;"><strong>ID del Ticket:</strong> ' . html($ticketNo) . '</p>'
                                    . '<p style="margin:0 0 8px;"><strong>Tema:</strong> ' . html($ticketTopic) . '</p>'
                                    . '<p style="margin:0;"><strong>Asunto:</strong> ' . html($ticketSubject) . '</p>'
                                    . '</div>'
                                    . '<p style="margin-top:14px;color:#64748b;font-size:12px;">' . html((string)(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets')) . '</p>'
                                    . '</div>';
                                $bodyTextClient = "Estimado usuario, los técnicos han llegado al lugar o su ticket está en proceso.\n\n"
                                    . "ID del Ticket: $ticketNo\n"
                                    . "Tema: $ticketTopic\n"
                                    . "Asunto: $ticketSubject";
                                if (function_exists('enqueueEmailJob')) {
                                    enqueueEmailJob($toClient, $subjClient, $bodyHtmlClient, $bodyTextClient, [
                                        'empresa_id' => (int)$eid,
                                        'context_type' => 'ticket_en_proceso_client',
                                        'context_id' => (int)$tid,
                                    ]);
                                    if (function_exists('triggerEmailQueueWorkerAsync')) {
                                        triggerEmailQueueWorkerAsync();
                                    }
                                } else {
                                    Mailer::send($toClient, $subjClient, $bodyHtmlClient, $bodyTextClient);
                                }
                                addLog('ticket_en_proceso_email', 'Notificación En Proceso encolada/enviada al usuario ' . $toClient, 'ticket', $tid);
                            }
                            notifyStatusChangeToAdminRecipients($tid, 'En Proceso');
                        }

                        // Si cambió a Resuelto (id=4), notificar
                        if ($new_status_id === 4 && (int)($ticketView['status_id'] ?? 0) !== 4) {
                            notifyStatusChangeToAdminRecipients($tid, 'Resuelto');
                        }

                        // Si se cierra, notificar
                        if ($isClosingStatus && (int)($ticketView['status_id'] ?? 0) !== $new_status_id) {
                            $stLabel = (isset($stName) && $stName !== '') ? ucwords($stName) : 'Cerrado';
                            notifyStatusChangeToAdminRecipients($tid, $stLabel);
                        }
                        if (!$is_internal && $ticketView['staff_id'] === null) {
                            $stmtAssign = $mysqli->prepare('UPDATE tickets SET staff_id = ? WHERE id = ? AND empresa_id = ?');
                            if ($stmtAssign) {
                                $stmtAssign->bind_param('iii', $staff_id, $tid, $eid);
                                $stmtAssign->execute();
                            }
                        }
                        // Adjuntos: guardar archivos y registrar en BD
                        $uploadDir = defined('ATTACHMENTS_DIR') ? ATTACHMENTS_DIR : dirname(__DIR__, 4) . '/uploads/attachments';
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0755, true);
                        }
                        $allowedExt = [
                            'jpg' => 'image/jpeg',
                            'jpeg' => 'image/jpeg',
                            'png' => 'image/png',
                            'gif' => 'image/gif',
                            'webp' => 'image/webp',
                            'pdf' => 'application/pdf',
                            'doc' => 'application/msword',
                            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'txt' => 'text/plain',
                            'mp4' => 'video/mp4',
                            'webm' => 'video/webm',
                            'mov' => 'video/quicktime',
                            'mkv' => 'video/x-matroska',
                        ];
                        $maxSize = 25 * 1024 * 1024; // Aumentado a 25 MB para soportar PDFs más grandes
                        if (!empty($_FILES['attachments']['name'][0])) {
                            $files = $_FILES['attachments'];
                            $n = is_array($files['name']) ? count($files['name']) : 1;
                            if ($n === 1 && !is_array($files['name'])) {
                                $files = ['name' => [$files['name']], 'type' => [$files['type']], 'tmp_name' => [$files['tmp_name']], 'error' => [$files['error']], 'size' => [$files['size']]];
                            }
                            for ($i = 0; $i < $n; $i++) {
                                $err = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                                if ($err !== UPLOAD_ERR_OK) continue;
                                $size = (int) ($files['size'][$i] ?? 0);
                                if ($size > $maxSize) continue;
                                $mime = (string) ($files['type'][$i] ?? '');
                                $orig = (string) ($files['name'][$i] ?? 'file');
                                $ext = strtolower((string) (pathinfo($orig, PATHINFO_EXTENSION) ?: ''));
                                if ($ext === '' || !isset($allowedExt[$ext])) continue;
                                if (class_exists('finfo') && !empty($files['tmp_name'][$i])) {
                                    $finfoObj = new finfo(FILEINFO_MIME_TYPE);
                                    $detected = @$finfoObj->file($files['tmp_name'][$i]);
                                    if (is_string($detected) && $detected !== '') $mime = $detected;
                                }
                                $safeName = bin2hex(random_bytes(8)) . '_' . time() . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
                                $path = $uploadDir . '/' . $safeName;
                                if (move_uploaded_file($files['tmp_name'][$i], $path)) {
                                    $relPath = 'uploads/attachments/' . $safeName;
                                    $hash = @hash_file('sha256', $path) ?: '';
                                    // Incluimos empresa_id para mantener integridad multi-tenant
                                    $stmtA = $mysqli->prepare("INSERT INTO attachments (empresa_id, thread_entry_id, filename, original_filename, mimetype, size, path, hash, created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                                    $stmtA->bind_param('iisssiss', $eid, $entry_id, $safeName, $orig, $mime, $size, $relPath, $hash);
                                    if (!$stmtA->execute()) {
                                        error_log("[tickets] Error al insertar adjunto en BD: " . $stmtA->error);
                                    }
                                } else {
                                    error_log("[tickets] No se pudo mover el archivo subido a: " . $path);
                                }
                            }
                        }

                        // Notificación por correo al cliente (solo respuestas públicas)
                        if (!$is_internal && (!defined('SEND_CLIENT_UPDATE_EMAIL') || SEND_CLIENT_UPDATE_EMAIL)) {
                            $to = trim((string)($ticketView['user_email'] ?? ''));
                            if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                                $ticketNo = (string)($ticketView['ticket_number'] ?? ('#' . $tid));
                                $subj = '[Respuesta] ' . $ticketNo . ' - ' . (string)($ticketView['subject'] ?? 'Ticket');

                                $sigText = '';
                                if ($signature_mode === 'staff' && $staff_has_signature) {
                                    $sigText = $staff_signature;
                                }

                                $isHtml = strpos($body, '<') !== false;
                                $msgHtml = $isHtml
                                    ? strip_tags($body, '<p><br><strong><em><b><i><u><s><ul><ol><li><a><span><div>')
                                    : nl2br(html($body));
                                $sigHtml = $sigText !== '' ? '<br><br>' . nl2br(html($sigText)) : '';

                                $viewUrl = (defined('APP_URL') ? APP_URL : '') . '/upload/tickets.php?id=' . (int) $tid;
                                $bodyHtml = '
<div style="font-family: Segoe UI, sans-serif; max-width: 600px; margin: 0 auto; color: #333;">
    <h2 style="color: #2c3e50;">Actualización de su ticket</h2>
    <p>Se ha registrado una nueva respuesta en su solicitud de soporte.</p>
    
    <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
        <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #eee; width: 100px;"><strong>Ticket:</strong></td>
            <td style="padding: 8px 0; border-bottom: 1px solid #eee;">#' . htmlspecialchars($ticketNo) . '</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Asunto:</strong></td>
            <td style="padding: 8px 0; border-bottom: 1px solid #eee;">' . htmlspecialchars((string)($ticketView['subject'] ?? 'Ticket')) . '</td>
        </tr>
    </table>

    <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; margin: 20px 0; line-height: 1.6;">
        ' . $msgHtml . $sigHtml . '
    </div>

    <p style="margin: 25px 0;">
        <a href="' . htmlspecialchars($viewUrl) . '" style="display: inline-block; background: #3498db; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: 600;">
            Ver conversación completa
        </a>
    </p>

    <p style="color: #7f8c8d; font-size: 12px; margin-top: 30px;">
        ' . htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets') . '
    </p>
</div>';

                                $bodyText = strip_tags($body) . ($sigText !== '' ? "\n\n" . $sigText : '');
                                if (function_exists('enqueueEmailJob')) {
                                    enqueueEmailJob($to, $subj, $bodyHtml, $bodyText, ['empresa_id' => $eid, 'ticket_id' => $tid]);
                                    triggerEmailQueueWorkerAsync(20);
                                } else {
                                    Mailer::send($to, $subj, $bodyHtml, $bodyText);
                                }
                            }
                        }

                        $reply_success = true;
                        $msgFinal = 'reply_sent';
                        if ($isClosingStatus && (int)($ticketView['requires_report'] ?? 0) === 1 && roleHasPermission('ticket.reports')) {
                            if (($ticketView['approval_status'] ?? '') !== 'rechazado') {
                                $msgFinal = 'closed_report';
                            }
                        }
                        header('Location: tickets.php?id=' . $tid . '&msg=' . $msgFinal);
                        exit;
                    }
                    $reply_errors[] = 'Error al guardar la respuesta.';
                }
            }
        }

        // Lista de estados para el desplegable
        $ticket_status_list = [];
        $resSt = $mysqli->query("SELECT id, name FROM ticket_status ORDER BY order_by, id");
        if ($resSt) while ($row = $resSt->fetch_assoc()) $ticket_status_list[] = $row;

        // Cargar mensajes del hilo (solo no internos para cliente; en SCP mostramos todos)
        $stmt = $mysqli->prepare(
            "SELECT te.id, te.thread_id, te.user_id, te.staff_id, te.body, te.is_internal, te.created,
             u.firstname AS user_first, u.lastname AS user_last,
             s.firstname AS staff_first, s.lastname AS staff_last, s.email AS staff_email
             FROM thread_entries te
             LEFT JOIN users u ON te.user_id = u.id
             LEFT JOIN staff s ON te.staff_id = s.id
             WHERE te.thread_id = ?
             ORDER BY te.created ASC"
        );
        $stmt->bind_param('i', $thread_id);
        $stmt->execute();
        $ticketView['thread_entries'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Adjuntos por entrada
        $attachmentsByEntry = [];
        if (!empty($ticketView['thread_entries'])) {
            $entryIds = array_map(static fn($e) => (int) $e['id'], $ticketView['thread_entries']);
            $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
            $typesA = str_repeat('i', count($entryIds));
            $sqlA = "SELECT id, thread_entry_id, original_filename, mimetype, size FROM attachments WHERE thread_entry_id IN ($placeholders) ORDER BY id";
            $stmtAtt = $mysqli->prepare($sqlA);
            $stmtAtt->bind_param($typesA, ...$entryIds);
            $stmtAtt->execute();
            $resAtt = $stmtAtt->get_result();
            while ($a = $resAtt->fetch_assoc()) {
                $eid = (int) $a['thread_entry_id'];
                if (!isset($attachmentsByEntry[$eid])) $attachmentsByEntry[$eid] = [];
                $attachmentsByEntry[$eid][] = $a;
            }
        }

        $entryReadMap = [];
        if (!empty($ticketView['thread_entries']) && function_exists('getThreadEntryReadStatusMap')) {
            $entryReadMap = getThreadEntryReadStatusMap(
                $mysqli,
                array_map(static fn($e) => (int)($e['id'] ?? 0), $ticketView['thread_entries']),
                $eid
            );
        }

        // Último mensaje y última respuesta (agente)
        $lastMsg = null;
        $lastReply = null;
        foreach ($ticketView['thread_entries'] as $e) {
            if ($e['is_internal'] != 1) {
                $lastMsg = $e['created'];
                if ($e['staff_id']) $lastReply = $e['created'];
            }
        }
        $ticketView['last_message'] = $lastMsg;
        $ticketView['last_response'] = $lastReply;

        $ticketView['user_name'] = trim($ticketView['user_first'] . ' ' . $ticketView['user_last']) ?: $ticketView['user_email'];
        $ticketView['staff_name'] = ($ticketView['staff_first'] || $ticketView['staff_last'])
            ? trim($ticketView['staff_first'] . ' ' . $ticketView['staff_last'])
            : '— Sin asignar —';

        // Tickets vinculados y colaboradores (si existen tablas)
        $ticketView['linked_tickets'] = [];
        $ticketView['collaborators'] = [];
        if (dbTableExists('ticket_links')) {
            $stmt = $mysqli->prepare("SELECT tl.linked_ticket_id AS id, t.ticket_number, t.subject FROM ticket_links tl JOIN tickets t ON t.id = tl.linked_ticket_id WHERE tl.ticket_id = ? AND t.empresa_id = ?");
            $stmt->bind_param('ii', $tid, $eid);
            $stmt->execute();
            $ticketView['linked_tickets'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        if (dbTableExists('ticket_collaborators')) {
            $stmt = $mysqli->prepare("SELECT tc.user_id, u.firstname, u.lastname, u.email FROM ticket_collaborators tc JOIN users u ON u.id = tc.user_id WHERE tc.ticket_id = ? AND u.empresa_id = ?");
            $stmt->bind_param('ii', $tid, $eid);
            $stmt->execute();
            $ticketView['collaborators'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        // Referidos
        $ticketView['referrals'] = [];
        $stmtRef = $mysqli->prepare("SELECT tr.id, tr.staff_id, tr.dept_id, s.firstname, s.lastname, d.name AS dept_name 
            FROM ticket_referrals tr 
            LEFT JOIN staff s ON s.id = tr.staff_id 
            LEFT JOIN departments d ON d.id = tr.dept_id 
            WHERE tr.ticket_id = ?");
        $stmtRef->bind_param('i', $tid);
        $stmtRef->execute();
        $ticketView['referrals'] = $stmtRef->get_result()->fetch_all(MYSQLI_ASSOC);

        require __DIR__ . '/../ticket-view.inc.php';
        return;
    }
}
