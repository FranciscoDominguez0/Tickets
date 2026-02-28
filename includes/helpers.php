<?php
/**
 * FUNCIONES AUXILIARES
 */

// Proteger página (requiere login)
function requireLogin($type = 'user') {
    global $mysqli;
    if ($type === 'cliente') {
        $status = (string)getAppSetting('system.helpdesk_status', 'online');
        if ($status === 'offline') {
            $currentPath = (string)($_SERVER['PHP_SELF'] ?? '');
            if (strpos($currentPath, '/upload/') !== false) {
                header('Location: login.php?msg=offline');
            } else {
                header('Location: upload/login.php?msg=offline');
            }
            exit;
        }
    }
    if ($type === 'cliente' && !isset($_SESSION['user_id'])) {
        // Detectar si estamos en upload/ o en otro lugar
        $currentPath = $_SERVER['PHP_SELF'];
        if (strpos($currentPath, '/upload/') !== false) {
            header('Location: login.php');
        } else {
            header('Location: upload/login.php');
        }
        exit;
    }

    if ($type === 'cliente' && isset($_SESSION['user_id'])) {
        $empresaId = (int)($_SESSION['empresa_id'] ?? 0);
        if ($empresaId > 0 && isset($mysqli) && $mysqli) {
            try {
                $res = $mysqli->query("SHOW TABLES LIKE 'empresas'");
                $hasEmpresas = ($res && $res->num_rows > 0);
                if ($hasEmpresas) {
                    $q = $mysqli->prepare('SELECT bloqueada, motivo_bloqueo FROM empresas WHERE id = ? LIMIT 1');
                    if ($q) {
                        $q->bind_param('i', $empresaId);
                        if ($q->execute()) {
                            $row = $q->get_result()->fetch_assoc();
                            $isBlocked = (int)($row['bloqueada'] ?? 0) === 1;
                            if ($isBlocked) {
                                $motivo = (string)($row['motivo_bloqueo'] ?? 'Servicio suspendido por falta de pago');
                                $loginHref = (strpos((string)($_SERVER['PHP_SELF'] ?? ''), '/upload/scp/') !== false) ? 'logout.php' : 'upload/scp/logout.php';
                                http_response_code(403);
                                echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Servicio suspendido</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css"></head><body class="bg-light"><div class="container py-5" style="max-width:720px"><div class="alert alert-danger"><strong>Servicio suspendido por falta de pago. Comuníquese con Vigitec Panamá.</strong><div class="mt-2">' . html($motivo) . '</div></div><a class="btn btn-primary" href="' . html($loginHref) . '">Ir al login</a></div></body></html>';
                                exit;
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
            }
        }

        if (!empty($_SESSION['session_fp'])) {
            $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
            $ipPrefix = '';
            if ($ip !== '') {
                if (strpos($ip, ':') !== false) {
                    $parts = explode(':', $ip);
                    $ipPrefix = strtolower(implode(':', array_slice($parts, 0, 4)));
                } else {
                    $parts = explode('.', $ip);
                    $ipPrefix = implode('.', array_slice($parts, 0, 3));
                }
            }
            $fpNow = hash('sha256', 'cliente|' . $ua . '|' . $ipPrefix);
            if (!hash_equals((string)$_SESSION['session_fp'], $fpNow)) {
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
                $currentPath = (string)($_SERVER['PHP_SELF'] ?? '');
                if (strpos($currentPath, '/upload/') !== false) {
                    header('Location: login.php?msg=timeout');
                } else {
                    header('Location: upload/login.php?msg=timeout');
                }
                exit;
            }
        }

        $timeoutMin = (int)getAppSetting('users.session_timeout_minutes', '30');
        if (!isset($_SESSION['user_last_activity']) || (int)($_SESSION['user_last_activity'] ?? 0) <= 0) {
            $_SESSION['user_last_activity'] = time();
        }
        if ($timeoutMin > 0) {
            $last = (int)($_SESSION['user_last_activity'] ?? 0);
            if ($last > 0 && (time() - $last) > ($timeoutMin * 60)) {
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
                $currentPath = (string)($_SERVER['PHP_SELF'] ?? '');
                if (strpos($currentPath, '/upload/') !== false) {
                    header('Location: login.php?msg=timeout');
                } else {
                    header('Location: upload/login.php?msg=timeout');
                }
                exit;
            }
        }

        $_SESSION['user_last_activity'] = time();
    }
    if ($type === 'agente' && !isset($_SESSION['staff_id'])) {
        // Detectar si estamos en upload/scp/ o en otro lugar
        $currentPath = $_SERVER['PHP_SELF'];
        if (strpos($currentPath, '/upload/scp/') !== false) {
            header('Location: login.php');
        } else {
            header('Location: ../upload/scp/login.php');
        }
        exit;
    }

    if ($type === 'agente' && isset($_SESSION['staff_id'])) {
        if (!isset($_SESSION['read_only'])) {
            $_SESSION['read_only'] = 0;
        }
        if (!isset($_SESSION['read_only_reason'])) {
            $_SESSION['read_only_reason'] = '';
        }

        $empresaId = (int)($_SESSION['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            syncEmpresaBillingStatus($empresaId);
        }
        if ($empresaId > 0 && isset($mysqli) && $mysqli) {
            try {
                $res = $mysqli->query("SHOW TABLES LIKE 'empresas'");
                $hasEmpresas = ($res && $res->num_rows > 0);
                if ($hasEmpresas) {
                    $q = $mysqli->prepare('SELECT bloqueada, motivo_bloqueo, estado_pago, fecha_vencimiento FROM empresas WHERE id = ? LIMIT 1');
                    if ($q) {
                        $q->bind_param('i', $empresaId);
                        if ($q->execute()) {
                            $row = $q->get_result()->fetch_assoc();
                            $isBlocked = (int)($row['bloqueada'] ?? 0) === 1;
                            if ($isBlocked) {
                                $motivo = (string)($row['motivo_bloqueo'] ?? 'Servicio suspendido por falta de pago');
                                $loginHref = (strpos((string)($_SERVER['PHP_SELF'] ?? ''), '/upload/scp/') !== false) ? 'logout.php' : 'upload/scp/logout.php';
                                http_response_code(403);
                                echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Servicio suspendido</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css"></head><body class="bg-light"><div class="container py-5" style="max-width:720px"><div class="alert alert-danger"><strong>Servicio suspendido por falta de pago. Comuníquese con Vigitec Panamá.</strong><div class="mt-2">' . html($motivo) . '</div></div><a class="btn btn-primary" href="' . html($loginHref) . '">Ir al login</a></div></body></html>';
                                exit;
                            }

                            $estadoPago = (string)($row['estado_pago'] ?? '');
                            $fechaVenc = (string)($row['fecha_vencimiento'] ?? '');
                            $isVencida = false;
                            if ($estadoPago === 'vencido') {
                                $isVencida = true;
                            } elseif ($fechaVenc !== '') {
                                $isVencida = (strtotime($fechaVenc) <= strtotime(date('Y-m-d')));
                            }
                            if ($isVencida) {
                                $_SESSION['read_only'] = 1;
                                $_SESSION['read_only_reason'] = 'Pago vencido. Comuníquese con Vigitec Panamá.';
                            } else {
                                $_SESSION['read_only'] = 0;
                                $_SESSION['read_only_reason'] = '';
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
            }
        }

        $currentPath = (string)($_SERVER['PHP_SELF'] ?? '');
        $isScp = (strpos($currentPath, '/upload/scp/') !== false);
        if ($isScp && (int)($_SESSION['read_only'] ?? 0) === 1) {
            $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            if ($method === 'POST') {
                http_response_code(403);
                $motivo = (string)($_SESSION['read_only_reason'] ?? 'Pago vencido. Comuníquese con Vigitec Panamá.');
                echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Modo lectura</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css"></head><body class="bg-light"><div class="container py-5" style="max-width:720px"><div class="alert alert-warning"><strong>Modo lectura.</strong><div class="mt-2">' . html($motivo) . '</div></div><a class="btn btn-outline-secondary" href="javascript:history.back()">Volver</a></div></body></html>';
                exit;
            }
        }

        if (!empty($_SESSION['session_fp'])) {
            $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
            $ipPrefix = '';
            if ($ip !== '') {
                if (strpos($ip, ':') !== false) {
                    $parts = explode(':', $ip);
                    $ipPrefix = strtolower(implode(':', array_slice($parts, 0, 4)));
                } else {
                    $parts = explode('.', $ip);
                    $ipPrefix = implode('.', array_slice($parts, 0, 3));
                }
            }
            $fpNow = hash('sha256', 'agente|' . $ua . '|' . $ipPrefix);
            if (!hash_equals((string)$_SESSION['session_fp'], $fpNow)) {
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
                $currentPath = (string)($_SERVER['PHP_SELF'] ?? '');
                if (strpos($currentPath, '/upload/scp/') !== false) {
                    header('Location: login.php?msg=timeout');
                } else {
                    header('Location: ../upload/scp/login.php?msg=timeout');
                }
                exit;
            }
        }

        $timeoutMin = (int)getAppSetting('agents.session_timeout_minutes', '30');
        if (!isset($_SESSION['staff_last_activity']) || (int)($_SESSION['staff_last_activity'] ?? 0) <= 0) {
            $_SESSION['staff_last_activity'] = time();
        }
        if ($timeoutMin > 0) {
            $last = (int)($_SESSION['staff_last_activity'] ?? 0);
            if ($last > 0 && (time() - $last) > ($timeoutMin * 60)) {
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
                $currentPath = $_SERVER['PHP_SELF'];
                if (strpos($currentPath, '/upload/scp/') !== false) {
                    header('Location: login.php?msg=timeout');
                } else {
                    header('Location: ../upload/scp/login.php?msg=timeout');
                }
                exit;
            }
        }

        $bindIp = (string)getAppSetting('agents.bind_session_ip', '0') === '1';
        if ($bindIp) {
            $currentIp = (string)($_SERVER['REMOTE_ADDR'] ?? '');
            $loginIp = (string)($_SESSION['staff_login_ip'] ?? '');
            if ($loginIp !== '' && $currentIp !== '' && $loginIp !== $currentIp) {
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
                $currentPath = $_SERVER['PHP_SELF'];
                if (strpos($currentPath, '/upload/scp/') !== false) {
                    header('Location: login.php?msg=ip');
                } else {
                    header('Location: ../upload/scp/login.php?msg=ip');
                }
                exit;
            }
        }

        $_SESSION['staff_last_activity'] = time();
    }
}

function syncEmpresaBillingStatus($empresaId) {
    global $mysqli;
    $empresaId = (int)$empresaId;
    if ($empresaId <= 0) return false;
    if (!isset($mysqli) || !$mysqli) return false;

    try {
        $res = $mysqli->query("SHOW TABLES LIKE 'empresas'");
        $hasEmpresas = ($res && $res->num_rows > 0);
        if (!$hasEmpresas) return false;

        $stmt = $mysqli->prepare('SELECT estado_pago, bloqueada, fecha_vencimiento FROM empresas WHERE id = ? LIMIT 1');
        if (!$stmt) return false;
        $stmt->bind_param('i', $empresaId);
        if (!$stmt->execute()) return false;
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) return false;

        $estadoPago = (string)($row['estado_pago'] ?? '');
        $bloqueada = (int)($row['bloqueada'] ?? 0) === 1;
        $fechaVenc = (string)($row['fecha_vencimiento'] ?? '');
        if ($fechaVenc === '') return true;

        $hoy = date('Y-m-d');

        // Al vencer: pasa a suspendido (sin bloquear) por 3 días
        if (!$bloqueada && $estadoPago === 'al_dia' && strtotime($fechaVenc) <= strtotime($hoy)) {
            $u = $mysqli->prepare("UPDATE empresas SET estado_pago = 'suspendido' WHERE id = ? AND estado_pago = 'al_dia'");
            if ($u) {
                $u->bind_param('i', $empresaId);
                $u->execute();
            }
            $estadoPago = 'suspendido';
        }

        // Si sigue suspendido y pasaron 3 días desde el vencimiento: bloquear
        if (!$bloqueada && $estadoPago === 'suspendido') {
            $daysPast = (int)floor((strtotime($hoy) - strtotime($fechaVenc)) / 86400);
            if ($daysPast >= 3) {
                $u2 = $mysqli->prepare("UPDATE empresas SET bloqueada = 1, motivo_bloqueo = COALESCE(NULLIF(motivo_bloqueo,''), 'Servicio suspendido por falta de pago') WHERE id = ? AND estado_pago = 'suspendido' AND bloqueada = 0");
                if ($u2) {
                    $u2->bind_param('i', $empresaId);
                    $u2->execute();
                }
            }
        }

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function syncAllEmpresasBillingStatus() {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return false;
    try {
        $res = $mysqli->query("SHOW TABLES LIKE 'empresas'");
        $hasEmpresas = ($res && $res->num_rows > 0);
        if (!$hasEmpresas) return false;

        // Al vencer: pasa a suspendido (sin bloquear)
        $mysqli->query("UPDATE empresas SET estado_pago = 'suspendido'
                        WHERE fecha_vencimiento IS NOT NULL
                          AND DATEDIFF(fecha_vencimiento, CURDATE()) <= 0
                          AND estado_pago = 'al_dia'");

        // Luego de 3 días desde el vencimiento: bloquear (mantiene suspendido)
        $mysqli->query("UPDATE empresas
                        SET bloqueada = 1,
                            motivo_bloqueo = COALESCE(NULLIF(motivo_bloqueo,''), 'Servicio suspendido por falta de pago')
                        WHERE fecha_vencimiento IS NOT NULL
                          AND DATEDIFF(CURDATE(), fecha_vencimiento) >= 3
                          AND estado_pago = 'suspendido'
                          AND bloqueada = 0");

        sendBillingDueNotifications();

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function sendBillingDueNotifications() {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return false;

    try {
        $enabled = (string)getAppSetting('billing.notice_enabled', '1');
        if ($enabled !== '1') return true;

        $resE = $mysqli->query("SHOW TABLES LIKE 'empresas'");
        if (!$resE || $resE->num_rows <= 0) return false;
        $resS = $mysqli->query("SHOW TABLES LIKE 'staff'");
        if (!$resS || $resS->num_rows <= 0) return false;
        $resN = $mysqli->query("SHOW TABLES LIKE 'notifications'");
        if (!$resN || $resN->num_rows <= 0) return false;

        $daysRaw = trim((string)getAppSetting('billing.notice_days', '3'));
        $daysList = [];
        foreach (preg_split('/\s*,\s*/', $daysRaw) as $d) {
            if ($d === '') continue;
            if (is_numeric($d)) {
                $n = (int)$d;
                if ($n > 0 && $n <= 365) $daysList[$n] = true;
            }
        }
        $days = array_keys($daysList);
        if (empty($days)) return true;

        $subjectTpl = trim((string)getAppSetting('billing.notice_subject', 'Aviso: vencimiento próximo'));
        $msgTpl = trim((string)getAppSetting('billing.notice_message', 'Tu plan vence en {dias} día(s) ({vencimiento}).'));

        $hasStaffEmpresa = false;
        $col = $mysqli->query("SHOW COLUMNS FROM staff LIKE 'empresa_id'");
        $hasStaffEmpresa = ($col && $col->num_rows > 0);
        if (!$hasStaffEmpresa) return false;

        $in = implode(',', array_map('intval', $days));
        $sql = "SELECT id, nombre, fecha_vencimiento, DATEDIFF(fecha_vencimiento, CURDATE()) dias\n"
             . "FROM empresas\n"
             . "WHERE fecha_vencimiento IS NOT NULL\n"
             . "  AND estado_pago = 'al_dia'\n"
             . "  AND bloqueada = 0\n"
             . "  AND DATEDIFF(fecha_vencimiento, CURDATE()) IN ($in)";

        $res = $mysqli->query($sql);
        if (!$res) return true;

        $stmtStaff = $mysqli->prepare("SELECT id FROM staff WHERE is_active = 1 AND role = 'admin' AND empresa_id = ? ORDER BY id");
        $stmtExists = $mysqli->prepare("SELECT 1 FROM notifications WHERE staff_id = ? AND type = ? AND related_id = ? AND DATE(created_at) = CURDATE() LIMIT 1");
        $stmtIns = $mysqli->prepare("INSERT INTO notifications (staff_id, message, type, related_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        if (!$stmtStaff || !$stmtExists || !$stmtIns) return false;

        $type = 'billing_due';

        while ($e = $res->fetch_assoc()) {
            $empresaId = (int)($e['id'] ?? 0);
            if ($empresaId <= 0) continue;
            $dias = (int)($e['dias'] ?? 0);
            $empresaNombre = (string)($e['nombre'] ?? '');
            $venc = (string)($e['fecha_vencimiento'] ?? '');

            $subject = $subjectTpl;
            $message = $msgTpl;
            $repl = [
                '{empresa}' => $empresaNombre,
                '{dias}' => (string)$dias,
                '{vencimiento}' => $venc,
            ];
            $subject = strtr($subject, $repl);
            $message = strtr($message, $repl);
            $final = $subject !== '' ? ('[' . $subject . '] ' . $message) : $message;

            $stmtStaff->bind_param('i', $empresaId);
            if (!$stmtStaff->execute()) continue;
            $rsStaff = $stmtStaff->get_result();
            if (!$rsStaff) continue;
            while ($s = $rsStaff->fetch_assoc()) {
                $sid = (int)($s['id'] ?? 0);
                if ($sid <= 0) continue;

                $stmtExists->bind_param('isi', $sid, $type, $empresaId);
                if ($stmtExists->execute()) {
                    $rsEx = $stmtExists->get_result();
                    if ($rsEx && $rsEx->fetch_assoc()) {
                        continue;
                    }
                }

                $stmtIns->bind_param('issi', $sid, $final, $type, $empresaId);
                $stmtIns->execute();
            }
        }

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function empresaId() {
    $eid = (int)($_SESSION['empresa_id'] ?? 1);
    if ($eid <= 0) $eid = 1;
    return $eid;
}

// Validar CSRF
function validateCSRF() {
    if ($_POST && !Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        return false;
    }
    return true;
}

// Campo CSRF en formulario
function csrfField() {
    echo '<input type="hidden" name="csrf_token" value="' . 
         htmlspecialchars($_SESSION['csrf_token']) . '">';
}

// Escapar output (XSS prevention)
function html($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function sanitizeRichText($inputHtml) {
    $inputHtml = (string)$inputHtml;
    if ($inputHtml === '') return '';
    if (stripos($inputHtml, '<') === false) return nl2br(html($inputHtml));

    // Allowed tags and attributes
    $allowed = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'em' => [],
        'b' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'span' => [],
        'div' => [],
        'blockquote' => [],
        'pre' => [],
        'code' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'iframe' => ['src', 'width', 'height', 'frameborder', 'allow', 'allowfullscreen', 'referrerpolicy'],
    ];

    $stripDisallowed = function ($html) use ($allowed) {
        $allowedTags = '';
        foreach (array_keys($allowed) as $t) $allowedTags .= '<' . $t . '>';
        return strip_tags($html, $allowedTags);
    };

    $htmlIn = $stripDisallowed($inputHtml);

    if (!class_exists('DOMDocument')) {
        return $htmlIn;
    }

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $htmlIn, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $normalizeProtocolRelative = function ($url) {
        $url = trim((string)$url);
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        return $url;
    };

    $isSafeUrl = function ($url) use ($normalizeProtocolRelative) {
        $url = trim((string)$url);
        if ($url === '') return false;
        if (strpos($url, '#') === 0) return true;
        if (preg_match('~^mailto:~i', $url)) return true;
        $url = $normalizeProtocolRelative($url);
        if (preg_match('~^https?://~i', $url)) return true;
        return false;
    };

    $isSafeImgSrc = function ($url) use ($normalizeProtocolRelative) {
        $url = trim((string)$url);
        if ($url === '') return false;
        $url = $normalizeProtocolRelative($url);
        if (preg_match('~^https?://~i', $url)) return true;
        if (preg_match('~^data:image/(png|jpe?g|gif|webp);base64,~i', $url)) return true;
        return false;
    };

    $isSafeIframeSrc = function ($url) use ($normalizeProtocolRelative) {
        $url = trim((string)$url);
        if ($url === '') return false;
        $url = $normalizeProtocolRelative($url);
        if (!preg_match('~^https?://~i', $url)) return false;
        return (bool)preg_match('~^https?://(www\.)?(youtube\.com/embed/|youtube-nocookie\.com/embed/|player\.vimeo\.com/video/)~i', $url);
    };

    $walker = function ($node) use (&$walker, $allowed, $isSafeUrl, $isSafeImgSrc, $isSafeIframeSrc) {
        if (!$node || !$node->childNodes) return;
        // Iterate backwards because we may remove nodes
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if (!$child) continue;

            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($child->nodeName);
                if (!array_key_exists($tag, $allowed)) {
                    // Remove disallowed element but keep its text content
                    $text = $child->textContent;
                    $node->replaceChild($node->ownerDocument->createTextNode($text), $child);
                    continue;
                }

                // Remove all event handlers and disallowed attributes
                $allowedAttrs = $allowed[$tag];
                if ($child->hasAttributes()) {
                    $toRemove = [];
                    foreach ($child->attributes as $attr) {
                        $name = strtolower($attr->name);
                        if (strpos($name, 'on') === 0) {
                            $toRemove[] = $attr->name;
                            continue;
                        }
                        if ($name === 'style' || $name === 'srcdoc') {
                            $toRemove[] = $attr->name;
                            continue;
                        }
                        if (!in_array($name, $allowedAttrs, true)) {
                            $toRemove[] = $attr->name;
                            continue;
                        }
                    }
                    foreach ($toRemove as $rm) $child->removeAttribute($rm);
                }

                // Tag-specific attribute sanitization
                if ($tag === 'a') {
                    $href = $child->getAttribute('href');
                    if ($href !== '' && !$isSafeUrl($href)) {
                        $child->removeAttribute('href');
                    } elseif ($href !== '' && strpos(trim($href), '//') === 0) {
                        $child->setAttribute('href', 'https:' . trim($href));
                    }
                    $target = strtolower($child->getAttribute('target'));
                    if ($target === '_blank') {
                        $child->setAttribute('rel', 'noopener noreferrer');
                    } else {
                        $child->removeAttribute('target');
                        $child->removeAttribute('rel');
                    }
                } elseif ($tag === 'img') {
                    $src = $child->getAttribute('src');
                    if ($src !== '' && strpos(trim($src), '//') === 0) {
                        $src = 'https:' . trim($src);
                        $child->setAttribute('src', $src);
                    }
                    if ($src === '' || !$isSafeImgSrc($src)) {
                        $node->removeChild($child);
                        continue;
                    }
                } elseif ($tag === 'iframe') {
                    $src = $child->getAttribute('src');
                    if ($src !== '' && strpos(trim($src), '//') === 0) {
                        $src = 'https:' . trim($src);
                        $child->setAttribute('src', $src);
                    }
                    if ($src === '' || !$isSafeIframeSrc($src)) {
                        $node->removeChild($child);
                        continue;
                    }
                }

                $walker($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
            }
        }
    };

    $walker($doc);
    $out = $doc->saveHTML();
    $out = preg_replace('~^<\?xml[^>]*>~i', '', (string)$out);
    return (string)$out;
}

// Formatear fecha
function formatDate($date) {
    if (!$date) return '-';
    return date('d/m/Y H:i', strtotime($date));
}

// Redirect
function redirect($url) {
    header("Location: $url");
    exit;
}

// GET seguro
function getQuery($key, $default = null) {
    return $_GET[$key] ?? $default;
}

// POST seguro
function getPost($key, $default = null) {
    return $_POST[$key] ?? $default;
}

// Obtener usuario actual
function getCurrentUser() {
    if (isset($_SESSION['user_id'])) {
        return [
            'id' => $_SESSION['user_id'],
            'type' => 'cliente',
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email']
        ];
    } elseif (isset($_SESSION['staff_id'])) {
        return [
            'id' => $_SESSION['staff_id'],
            'type' => 'agente',
            'name' => $_SESSION['staff_name'],
            'email' => $_SESSION['staff_email']
        ];
    }
    return null;
}

// Validar email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? true : false;
}

// Generar número de ticket
function generateTicketNumber() {
    return strtoupper(substr(md5(uniqid()), 0, 3)) . '-' . date('Ymd') . '-' . 
           str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
}

function ensureAppSettingsTable() {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return false;
    $sql = "CREATE TABLE IF NOT EXISTS app_settings (\n"
        . "  `key` VARCHAR(191) NOT NULL,\n"
        . "  `value` LONGTEXT NULL,\n"
        . "  `updated` DATETIME NULL,\n"
        . "  PRIMARY KEY (`key`)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if (!$mysqli->query($sql)) return false;

    $hasEmpresa = false;
    $col = $mysqli->query("SHOW COLUMNS FROM app_settings LIKE 'empresa_id'");
    $hasEmpresa = ($col && $col->num_rows > 0);
    if (!$hasEmpresa) {
        $mysqli->query("ALTER TABLE app_settings ADD COLUMN empresa_id INT NOT NULL DEFAULT 1");
    }

    $col2 = $mysqli->query("SHOW COLUMNS FROM app_settings LIKE 'empresa_id'");
    $hasEmpresa2 = ($col2 && $col2->num_rows > 0);
    if ($hasEmpresa2) {
        $primaryCols = [];
        $idx = $mysqli->query("SHOW INDEX FROM app_settings WHERE Key_name = 'PRIMARY'");
        if ($idx) {
            while ($r = $idx->fetch_assoc()) {
                $primaryCols[] = (string)($r['Column_name'] ?? '');
            }
        }
        $primaryCols = array_values(array_filter(array_unique($primaryCols)));
        $hasComposite = in_array('empresa_id', $primaryCols, true) && in_array('key', $primaryCols, true);
        if (!$hasComposite) {
            @$mysqli->query('ALTER TABLE app_settings DROP PRIMARY KEY');
            @$mysqli->query('ALTER TABLE app_settings ADD PRIMARY KEY (empresa_id, `key`)');
        }
    }

    return true;
}

function getAppSetting($key, $default = null) {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return $default;
    if (!ensureAppSettingsTable()) return $default;
    $key = (string)$key;

    $hasEmpresa = false;
    $col = $mysqli->query("SHOW COLUMNS FROM app_settings LIKE 'empresa_id'");
    $hasEmpresa = ($col && $col->num_rows > 0);

    if ($hasEmpresa) {
        $eid = empresaId();
        $stmt = $mysqli->prepare('SELECT `value` FROM app_settings WHERE `empresa_id` = ? AND `key` = ? LIMIT 1');
        if (!$stmt) return $default;
        $stmt->bind_param('is', $eid, $key);
    } else {
        $stmt = $mysqli->prepare('SELECT `value` FROM app_settings WHERE `key` = ? LIMIT 1');
        if (!$stmt) return $default;
        $stmt->bind_param('s', $key);
    }
    if (!$stmt) return $default;
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? ($row['value'] ?? $default) : $default;
}

function setAppSetting($key, $value) {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return false;
    if (!ensureAppSettingsTable()) return false;
    $key = (string)$key;
    $value = $value !== null ? (string)$value : null;

    $hasEmpresa = false;
    $col = $mysqli->query("SHOW COLUMNS FROM app_settings LIKE 'empresa_id'");
    $hasEmpresa = ($col && $col->num_rows > 0);

    if ($hasEmpresa) {
        $eid = empresaId();
        $stmt = $mysqli->prepare('INSERT INTO app_settings (`empresa_id`, `key`, `value`, `updated`) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated` = NOW()');
        if (!$stmt) return false;
        $stmt->bind_param('iss', $eid, $key, $value);
        return $stmt->execute();
    }

    $stmt = $mysqli->prepare('INSERT INTO app_settings (`key`, `value`, `updated`) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated` = NOW()');
    if (!$stmt) return false;
    $stmt->bind_param('ss', $key, $value);
    return $stmt->execute();
}

function toAppAbsoluteUrl($path) {
    $path = (string)$path;
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    if ($path[0] === '/') return rtrim((string)APP_URL, '/') . $path;

    $p = $path;
    while (strpos($p, '../') === 0) {
        $p = substr($p, 3);
    }
    $p = ltrim($p, '/');
    return rtrim((string)APP_URL, '/') . '/' . $p;
}

function getBrandAssetUrl($settingKey, $fallbackRelativePath) {
    $val = (string)getAppSetting($settingKey, '');
    if ($val !== '') {
        return toAppAbsoluteUrl($val);
    }
    return toAppAbsoluteUrl($fallbackRelativePath);
}

function getCompanyLogoUrl($fallbackRelativePath = 'publico/img/vigitec-logo.png') {
    $mode = (string)getAppSetting('company.logo_mode', '');
    $logo = (string)getAppSetting('company.logo', '');

    if ($mode === '') {
        $mode = $logo !== '' ? 'custom' : 'default';
    }

    if ($mode === 'custom' && $logo !== '') {
        return toAppAbsoluteUrl($logo);
    }
    return toAppAbsoluteUrl($fallbackRelativePath);
}

function addLog($action, $details = null, $object_type = null, $object_id = null, $user_type = null, $user_id = null) {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return false;
    $action = trim((string)$action);
    if ($action === '') return false;

    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $details = $details !== null ? (string)$details : null;
    $object_type = $object_type !== null ? (string)$object_type : null;
    $object_id = ($object_id !== null && is_numeric($object_id)) ? (int)$object_id : null;
    $user_type = $user_type !== null ? (string)$user_type : null;
    $user_id = ($user_id !== null && is_numeric($user_id)) ? (int)$user_id : null;

    $hasEmpresa = false;
    $col = $mysqli->query("SHOW COLUMNS FROM logs LIKE 'empresa_id'");
    $hasEmpresa = ($col && $col->num_rows > 0);

    if ($hasEmpresa) {
        $eid = empresaId();
        $stmt = $mysqli->prepare('INSERT INTO logs (empresa_id, action, object_type, object_id, user_type, user_id, details, ip_address, created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        if (!$stmt) return false;
        $stmt->bind_param('ississss', $eid, $action, $object_type, $object_id, $user_type, $user_id, $details, $ip);
        return $stmt->execute();
    }

    $stmt = $mysqli->prepare('INSERT INTO logs (action, object_type, object_id, user_type, user_id, details, ip_address, created) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    if (!$stmt) return false;
    $stmt->bind_param('ssissss', $action, $object_type, $object_id, $user_type, $user_id, $details, $ip);
    return $stmt->execute();
}

function ensureRolePermissionsTable() {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return false;
    $sql = "CREATE TABLE IF NOT EXISTS role_permissions (\n"
        . "  id INT PRIMARY KEY AUTO_INCREMENT,\n"
        . "  role_name VARCHAR(100) NOT NULL,\n"
        . "  perm_key VARCHAR(120) NOT NULL,\n"
        . "  is_enabled TINYINT(1) NOT NULL DEFAULT 1,\n"
        . "  created DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
        . "  updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        . "  UNIQUE KEY uq_role_perm (role_name, perm_key),\n"
        . "  KEY idx_role (role_name),\n"
        . "  KEY idx_perm (perm_key)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    return (bool)$mysqli->query($sql);
}

function getCurrentStaffRoleName() {
    global $mysqli;
    static $cached = null;
    if ($cached !== null) return $cached;
    $sid = (int)($_SESSION['staff_id'] ?? 0);
    if ($sid <= 0) {
        $cached = '';
        return $cached;
    }
    if (!isset($mysqli) || !$mysqli) {
        $cached = '';
        return $cached;
    }
    $stmt = $mysqli->prepare('SELECT role FROM staff WHERE id = ? LIMIT 1');
    if (!$stmt) {
        $cached = '';
        return $cached;
    }
    $stmt->bind_param('i', $sid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $cached = trim((string)($row['role'] ?? ''));
    return $cached;
}

function roleHasPermission($permKey) {
    global $mysqli;
    $permKey = trim((string)$permKey);
    if ($permKey === '') return false;

    $role = getCurrentStaffRoleName();
    if ($role === 'admin') return true;
    if ($role === '') return false;

    if (!isset($mysqli) || !$mysqli) return false;
    ensureRolePermissionsTable();

    $stmt = $mysqli->prepare('SELECT 1 FROM role_permissions WHERE role_name = ? AND perm_key = ? AND is_enabled = 1 LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('ss', $role, $permKey);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (bool)$row;
}

function roleHasAnyPermissionPrefix($prefix) {
    global $mysqli;
    $prefix = (string)$prefix;
    if ($prefix === '') return false;
    $role = getCurrentStaffRoleName();
    if ($role === 'admin') return true;
    if ($role === '') return false;
    if (!isset($mysqli) || !$mysqli) return false;
    ensureRolePermissionsTable();

    $like = $prefix . '%';
    $stmt = $mysqli->prepare('SELECT 1 FROM role_permissions WHERE role_name = ? AND perm_key LIKE ? AND is_enabled = 1 LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('ss', $role, $like);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (bool)$row;
}

function requireRolePermission($permKey, $redirectUrl = null) {
    $ok = roleHasPermission($permKey);
    if ($ok) return true;

    $_SESSION['flash_error'] = 'No tienes permiso para hacer esta acción.';
    addLog('permission_denied', (string)$permKey, null, null, 'staff', (int)($_SESSION['staff_id'] ?? 0));

    if ($redirectUrl) {
        header('Location: ' . $redirectUrl);
        exit;
    }
    http_response_code(403);
    exit('No autorizado');
}
?>
