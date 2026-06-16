<?php
/**
 * Bootstrap para el módulo de Cotizaciones.
 * Carga funciones auxiliares si son necesarias.
 */
if (!isset($mysqli) || !$mysqli) {
    die('Database connection not available.');
}

requireRolePermission('quote.view', 'index.php');

$eid = empresaId();



function countCotizaciones($mysqli, $eid, $status = '', $search = '') {
    $where = ["q.empresa_id = ?"];
    $params = [$eid];
    $types = "i";

    if ($status !== '') {
        $where[] = "q.status = ?";
        $params[] = $status;
        $types .= "s";
    }

    if ($search !== '') {
        $where[] = "(q.title LIKE ? OR o.name LIKE ? OR s.firstname LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "sss";
    }

    $whereSql = implode(' AND ', $where);
    $sql = "SELECT COUNT(*) as total FROM quotes q 
            LEFT JOIN organizations o ON q.org_id = o.id 
            LEFT JOIN staff s ON q.staff_id = s.id 
            WHERE $whereSql";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return 0;

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['total'];
}

function getCotizaciones($mysqli, $eid, $status = '', $search = '', $limit = 0, $offset = 0) {
    $where = ["q.empresa_id = ?"];
    $params = [$eid];
    $types = "i";

    if ($status !== '') {
        $where[] = "q.status = ?";
        $params[] = $status;
        $types .= "s";
    }

    if ($search !== '') {
        $where[] = "(q.title LIKE ? OR o.name LIKE ? OR s.firstname LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "sss";
    }

    $whereSql = implode(' AND ', $where);

    $sql = "SELECT q.*, 
            o.name as org_name, 
            CONCAT(s.firstname, ' ', s.lastname) as staff_name 
            FROM quotes q 
            LEFT JOIN organizations o ON q.org_id = o.id 
            LEFT JOIN staff s ON q.staff_id = s.id 
            WHERE $whereSql 
            ORDER BY q.created_at DESC";

    if ($limit > 0) {
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
    }

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $quotes = [];
    while ($row = $res->fetch_assoc()) {
        $quotes[] = $row;
    }
    return $quotes;
}

function sendQuoteEmailToOrgBoss($quoteId, $messageText, $isNewQuote, $mysqli, $newFilePath = null) {
    $quoteId = (int)$quoteId;
    $eid = empresaId();

    // Obtener detalles de la cotización
    $stmtQ = $mysqli->prepare("SELECT q.*, o.name as org_name FROM quotes q LEFT JOIN organizations o ON q.org_id = o.id WHERE q.id = ? AND q.empresa_id = ?");
    if (!$stmtQ) return false;
    $stmtQ->bind_param('ii', $quoteId, $eid);
    $stmtQ->execute();
    $quote = $stmtQ->get_result()->fetch_assoc();
    if (!$quote) return false;

    // Buscar al jefe de la organización
    $stmtBoss = $mysqli->prepare("
        SELECT u.id, u.email, u.firstname, u.lastname 
        FROM user_organizations uo 
        JOIN users u ON u.id = uo.user_id 
        WHERE uo.organization_id = ? 
          AND u.org_tickets_view = 1 
          AND u.empresa_id = ? 
        LIMIT 1
    ");
    if (!$stmtBoss) return false;
    $stmtBoss->bind_param('ii', $quote['org_id'], $eid);
    $stmtBoss->execute();
    $boss = $stmtBoss->get_result()->fetch_assoc();

    if (!$boss) return false; // No hay jefe configurado

    $bossEmail = trim((string)($boss['email'] ?? ''));
    if ($bossEmail === '' || !filter_var($bossEmail, FILTER_VALIDATE_EMAIL)) return false;

    $bossName = trim($boss['firstname'] . ' ' . $boss['lastname']);
    $quoteNo = '#' . str_pad($quoteId, 6, '0', STR_PAD_LEFT);
    $quoteTitle = htmlspecialchars((string)$quote['title']);
    $companyName = htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets');

    $clientPortalUrl = rtrim((string)(defined('APP_URL') ? APP_URL : ''), '/') . '/upload/view-quote.php?id=' . $quoteId;

    if ($isNewQuote) {
        $subjBoss = "[Nueva Cotización] " . $quoteNo . " - " . $quote['title'];
        $headerText = "Nueva Cotización Generada";
        $bodyP = "Se ha generado una nueva cotización para tu revisión.";
    } else {
        $subjBoss = "[Actualización de Cotización] " . $quoteNo . " - " . $quote['title'];
        $headerText = "Actualización en Cotización";
        $bodyP = "Se ha agregado un nuevo mensaje o archivo a la cotización.";
    }

    $bossBodyHtml = '<div style="font-family: Segoe UI, sans-serif; max-width: 700px; margin: 0 auto;">'
        . '<h2 style="color:#1e3a5f; margin: 0 0 8px;">' . $headerText . '</h2>'
        . '<p style="color:#475569; margin: 0 0 12px;">Estimado/a <strong>' . htmlspecialchars($bossName) . '</strong>, ' . $bodyP . '</p>'
        . '<table style="width:100%; border-collapse: collapse; margin: 12px 0;">'
        . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee; width: 100px;"><strong>Número:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">' . $quoteNo . '</td></tr>'
        . '<tr><td style="padding: 6px 0; border-bottom:1px solid #eee;"><strong>Asunto:</strong></td><td style="padding: 6px 0; border-bottom:1px solid #eee;">' . $quoteTitle . '</td></tr>'
        . '</table>';

    if (!empty($messageText)) {
        $bossBodyHtml .= '<div style="background:#f8fafc; border:1px solid #e2e8f0; padding:14px; border-radius:10px; margin-top:14px; margin-bottom:14px;">'
            . '<p style="margin:0;">' . nl2br(htmlspecialchars($messageText)) . '</p>'
            . '</div>';
    }

    $bossBodyHtml .= '<p style="margin: 14px 0 0;"><a href="' . htmlspecialchars($clientPortalUrl) . '" style="display:inline-block; background:#2563eb; color:#fff; padding:10px 16px; text-decoration:none; border-radius:8px;">Ver Cotización</a></p>'
        . '<p style="color:#94a3b8; font-size:12px; margin-top: 14px;">' . $companyName . '</p>'
        . '</div>';

    $bossBodyText = $headerText . "\n\nCotización: " . $quoteNo . "\nAsunto: " . $quote['title'] . "\n\n";
    if (!empty($messageText)) {
        $bossBodyText .= "Mensaje:\n" . $messageText . "\n\n";
    }
    $bossBodyText .= "Por favor revise la cotización accediendo al siguiente enlace:\n" . $clientPortalUrl;

    // Adjuntar PDF/archivo si aplica
    $attachments = [];
    $fileToAttach = null;
    $attachmentName = '';
    
    if ($isNewQuote && !empty($quote['file_path'])) {
        $fileToAttach = $quote['file_path'];
        $attachmentName = 'Cotizacion_' . $quoteNo . '.pdf';
    } elseif ($newFilePath !== null && !empty($newFilePath)) {
        $fileToAttach = $newFilePath;
        $attachmentName = basename($newFilePath);
    }
    
    if ($fileToAttach) {
        $projectRoot = realpath(dirname(__DIR__, 4));
        $filePath = $projectRoot . '/' . ltrim($fileToAttach, '/');
        if (is_file($filePath)) {
            $fileContent = @file_get_contents($filePath);
            if ($fileContent !== false) {
                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                $mimetype = 'application/octet-stream';
                if ($ext === 'pdf') {
                    $mimetype = 'application/pdf';
                } elseif ($ext === 'png') {
                    $mimetype = 'image/png';
                } elseif (in_array($ext, ['jpg', 'jpeg'])) {
                    $mimetype = 'image/jpeg';
                }
                
                $attachments[] = [
                    'filename' => $attachmentName,
                    'contentType' => $mimetype,
                    'content' => $fileContent
                ];
            }
        }
    }

    $mailOpts = [
        'empresa_id' => $eid,
        'context_type' => 'quote_notification',
        'context_id' => $quoteId,
    ];
    if (!empty($attachments)) {
        $mailOpts['attachments'] = $attachments;
    }

    if (function_exists('enqueueEmailJob')) {
        $emailOk = enqueueEmailJob($bossEmail, $subjBoss, $bossBodyHtml, $bossBodyText, $mailOpts);
        if ($emailOk && function_exists('triggerEmailQueueWorkerAsync')) {
            triggerEmailQueueWorkerAsync();
        }
        return $emailOk;
    } else {
        if (!empty($attachments)) {
            return Mailer::sendWithOptions($bossEmail, $subjBoss, $bossBodyHtml, $bossBodyText, ['attachments' => $attachments]);
        } else {
            return Mailer::send($bossEmail, $subjBoss, $bossBodyHtml, $bossBodyText);
        }
    }
}
