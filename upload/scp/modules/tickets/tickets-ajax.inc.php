<?php
// AJAX: búsqueda de usuarios (para cambiar propietario sin listar todos)
if (isset($_GET['action']) && $_GET['action'] === 'user_search') {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['staff_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
    if (!roleHasPermission('ticket.edit')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
        exit;
    }

    $q = trim((string)($_GET['q'] ?? ''));
    if ($q === '' || mb_strlen($q) < 2) {
        echo json_encode(['ok' => true, 'items' => []]);
        exit;
    }

    $like = '%' . $q . '%';
    $items = [];
    $stmtU = $mysqli->prepare(
        "SELECT id, firstname, lastname, email\n"
        . "FROM users\n"
        . "WHERE empresa_id = ? AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR CONCAT(firstname, ' ', lastname) LIKE ?)\n"
        . "ORDER BY firstname, lastname\n"
        . "LIMIT 20"
    );
    if ($stmtU) {
        $stmtU->bind_param('issss', $eid, $like, $like, $like, $like);
        if ($stmtU->execute()) {
            $res = $stmtU->get_result();
            while ($res && ($u = $res->fetch_assoc())) {
                $items[] = [
                    'id' => (int)($u['id'] ?? 0),
                    'name' => trim((string)($u['firstname'] ?? '') . ' ' . (string)($u['lastname'] ?? '')),
                    'email' => (string)($u['email'] ?? ''),
                ];
            }
        }
    }

    echo json_encode(['ok' => true, 'items' => $items]);
    exit;
}

// AJAX: vista previa de un ticket (último mensaje)
if (isset($_GET['action']) && $_GET['action'] === 'ticket_preview') {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['staff_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $tid = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($tid <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid ticket']);
        exit;
    }

    $stmt = $mysqli->prepare(
        "SELECT t.id, t.ticket_number, t.subject, t.updated, t.created,\n"
        . " u.firstname AS user_first, u.lastname AS user_last, u.email AS user_email\n"
        . "FROM tickets t\n"
        . "JOIN users u ON u.id = t.user_id\n"
        . "WHERE t.id = ? AND t.empresa_id = ?\n"
        . "LIMIT 1"
    );
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'DB error']);
        exit;
    }
    $stmt->bind_param('ii', $tid, $eid);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Not found']);
        exit;
    }

    $threadId = 0;
    $stmtTh = $mysqli->prepare('SELECT th.id FROM threads th INNER JOIN tickets t ON t.id = th.ticket_id WHERE th.ticket_id = ? AND t.empresa_id = ? LIMIT 1');
    if ($stmtTh) {
        $stmtTh->bind_param('ii', $tid, $eid);
        if ($stmtTh->execute()) {
            $row = $stmtTh->get_result()->fetch_assoc();
            $threadId = (int)($row['id'] ?? 0);
        }
    }

    $previewWhen = $ticket['updated'] ?: $ticket['created'];
    $previewIsInternal = 0;
    $previewAuthor = '';
    $entriesOut = [];

    if ($threadId > 0) {
        $stmtE = $mysqli->prepare(
            "SELECT te.id, te.staff_id, te.user_id, te.is_internal, te.body, te.created,\n"
            . " s.firstname AS staff_first, s.lastname AS staff_last,\n"
            . " u.firstname AS user_first, u.lastname AS user_last\n"
            . "FROM thread_entries te\n"
            . "LEFT JOIN staff s ON s.id = te.staff_id\n"
            . "LEFT JOIN users u ON u.id = te.user_id\n"
            . "WHERE te.thread_id = ?\n"
            . "ORDER BY te.created DESC, te.id DESC\n"
            . "LIMIT 8"
        );
        if ($stmtE) {
            $stmtE->bind_param('i', $threadId);
            if ($stmtE->execute()) {
                $res = $stmtE->get_result();
                $raw = [];
                while ($res && ($e = $res->fetch_assoc())) {
                    $raw[] = $e;
                }
                $raw = array_reverse($raw);

                foreach ($raw as $e) {
                    $author = '';
                    $isStaff = false;
                    if (!empty($e['staff_id'])) {
                        $author = trim((string)($e['staff_first'] ?? '') . ' ' . (string)($e['staff_last'] ?? ''));
                        $isStaff = true;
                    } elseif (!empty($e['user_id'])) {
                        $author = trim((string)($e['user_first'] ?? '') . ' ' . (string)($e['user_last'] ?? ''));
                    }

                    $bodyHtml = (string)($e['body'] ?? '');
                    $text = trim(html_entity_decode(strip_tags($bodyHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if (mb_strlen($text) > 900) {
                        $text = mb_substr($text, 0, 900) . '…';
                    }

                    $entriesOut[] = [
                        'id' => (int)($e['id'] ?? 0),
                        'author' => $author,
                        'when' => (string)($e['created'] ?? ''),
                        'is_internal' => (int)($e['is_internal'] ?? 0),
                        'is_staff' => $isStaff ? 1 : 0,
                        'text' => $text,
                        'attachments' => []
                    ];
                }

                if (!empty($entriesOut)) {
                    $entryIds = array_column($entriesOut, 'id');
                    $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
                    $stmtA = $mysqli->prepare(
                        "SELECT id, thread_entry_id, original_filename, mimetype, size \n"
                        . "FROM attachments \n"
                        . "WHERE thread_entry_id IN ($placeholders) ORDER BY id"
                    );
                    if ($stmtA) {
                        $types = str_repeat('i', count($entryIds));
                        $stmtA->bind_param($types, ...$entryIds);
                        if ($stmtA->execute()) {
                            $resA = $stmtA->get_result();
                            while ($resA && ($att = $resA->fetch_assoc())) {
                                $isImage = strpos($att['mimetype'], 'image/') === 0;
                                foreach ($entriesOut as &$eo) {
                                    if ($eo['id'] === (int)$att['thread_entry_id']) {
                                        $eo['attachments'][] = [
                                            'id' => (int)$att['id'],
                                            'filename' => (string)$att['original_filename'],
                                            'is_image' => $isImage,
                                            'url' => 'tickets.php?id=' . $tid . '&download=' . (int)$att['id']
                                        ];
                                        break;
                                    }
                                }
                                unset($eo);
                            }
                        }
                    }
                }

                if (!empty($raw)) {
                    $last = $raw[count($raw) - 1];
                    $previewWhen = $last['created'] ?: $previewWhen;
                    $previewIsInternal = (int)($last['is_internal'] ?? 0);
                    $author = '';
                    if (!empty($last['staff_id'])) {
                        $author = trim((string)($last['staff_first'] ?? '') . ' ' . (string)($last['staff_last'] ?? ''));
                    } elseif (!empty($last['user_id'])) {
                        $author = trim((string)($last['user_first'] ?? '') . ' ' . (string)($last['user_last'] ?? ''));
                    }
                    $previewAuthor = $author;
                }
            }
        }
    }

    $clientName = trim((string)($ticket['user_first'] ?? '') . ' ' . (string)($ticket['user_last'] ?? ''));
    if ($clientName === '') $clientName = (string)($ticket['user_email'] ?? '');

    echo json_encode([
        'ok' => true,
        'ticket' => [
            'id' => (int)$ticket['id'],
            'ticket_number' => (string)($ticket['ticket_number'] ?? ''),
            'subject' => (string)($ticket['subject'] ?? ''),
            'client' => $clientName,
            'when' => (string)$previewWhen,
            'author' => (string)$previewAuthor,
            'is_internal' => (int)$previewIsInternal,
            'entries' => $entriesOut,
        ]
    ]);
    exit;
}
