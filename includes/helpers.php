<?php
/**
 * FUNCIONES AUXILIARES
 */

// Proteger página (requiere login)
function requireLogin($type = 'user') {
    global $mysqli;
    if ((string)getAppSetting('system.force_https', '0') === '1') {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        if (!$isHttps && !headers_sent() && !empty($_SERVER['HTTP_HOST']) && !empty($_SERVER['REQUEST_URI'])) {
            header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
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
                $hasEmpresas = dbTableExists('empresas');
                if ($hasEmpresas) {
                    $q = $mysqli->prepare('SELECT bloqueada, motivo_bloqueo FROM empresas WHERE id = ? LIMIT 1');
                    if ($q) {
                        $q->bind_param('i', $empresaId);
                        if ($q->execute()) {
                            $row = $q->get_result()->fetch_assoc();
                            $isBlocked = (int)($row['bloqueada'] ?? 0) === 1;
                            if ($isBlocked) {
                                $motivo = (string)($row['motivo_bloqueo'] ?? 'Servicio suspendido por falta de pago');
                                $currentPath = (string)($_SERVER['PHP_SELF'] ?? '');
                                $basePrefix = '';
                                $posUpload = strpos($currentPath, '/upload/');
                                if ($posUpload !== false) {
                                    $basePrefix = substr($currentPath, 0, $posUpload);
                                }
                                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                                $host = (string)($_SERVER['HTTP_HOST'] ?? '');
                                $logoutPath = $basePrefix . '/upload/logout.php';
                                $logoutHref = ($host !== '') ? ($scheme . '://' . $host . $logoutPath) : $logoutPath;
                                http_response_code(403);
                                echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Servicio suspendido</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">'
                                    . '<style>'
                                    . 'body{min-height:100vh;background:radial-gradient(1200px 700px at 12% 14%,rgba(13,110,253,.24),transparent 62%),radial-gradient(980px 580px at 88% 82%,rgba(102,16,242,.14),transparent 58%),linear-gradient(180deg,#081225 0%,#0b1b3a 55%,#eef4ff 100%);}'
                                    . '.blk-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}'
                                    . '.blk-card{width:min(760px,96vw);border:1px solid rgba(255,255,255,.20);border-radius:18px;overflow:hidden;box-shadow:0 18px 55px rgba(16,24,40,.22);background:rgba(255,255,255,.94);backdrop-filter:blur(10px);}'
                                    . '.blk-head{padding:18px 18px;background:linear-gradient(135deg,rgba(13,110,253,.98),rgba(13,110,253,.72));color:#fff;display:flex;gap:12px;align-items:center;}'
                                    . '.blk-ico{width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.14);display:flex;align-items:center;justify-content:center;}'
                                    . '.blk-title{margin:0;font-weight:800;letter-spacing:.2px;font-size:18px;}'
                                    . '.blk-sub{margin:0;opacity:.92;font-size:13px;}'
                                    . '.blk-body{padding:18px;}'
                                    . '.blk-kv{background:rgba(13,110,253,.06);border:1px solid rgba(13,110,253,.18);border-radius:14px;padding:12px 14px;}'
                                    . '.blk-kv strong{color:#0a58ca;}'
                                    . '</style></head><body>'
                                    . '<div class="blk-wrap"><div class="blk-card">'
                                    . '<div class="blk-head">'
                                    . '<div class="blk-ico" aria-hidden="true">'
                                    . '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.964 0L.165 13.233c-.457.778.091 1.767.982 1.767h13.706c.89 0 1.438-.99.982-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>'
                                    . '</div>'
                                    . '<div><p class="blk-title">Acceso suspendido temporalmente</p><p class="blk-sub">Verificación de pago requerida para continuar</p></div>'
                                    . '</div>'
                                    . '<div class="blk-body">'
                                    . '<div class="blk-kv">'
                                    . '<div><strong>Motivo:</strong> ' . html($motivo) . '</div>'
                                    . '<div class="mt-2">Si ya realizaste el pago, comunícate con <strong>Vigitec Panamá</strong> para reactivar el servicio.</div>'
                                    . '</div>'
                                    . '<div class="d-flex gap-2 flex-wrap mt-3">'
                                    . '<a class="btn btn-primary" href="' . html($logoutHref) . '" target="_top" rel="noopener">Ir al login</a>'
                                    . '</div>'
                                    . '</div></div></div></body></html>';
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
            $bindIp = (string)getAppSetting('users.bind_session_ip', '0') === '1';
            $ipPrefix = $bindIp ? $ipPrefix : 'no-ip';
            
            $fpNow = hash('sha256', 'cliente|' . $ua . '|' . $ipPrefix);
            $browser = 'unknown';
            if (preg_match('~edg/(\d+)~i', $ua, $m)) {
                $browser = 'edge-' . (string)$m[1];
            } elseif (preg_match('~chrome/(\d+)~i', $ua, $m)) {
                $browser = 'chrome-' . (string)$m[1];
            } elseif (preg_match('~firefox/(\d+)~i', $ua, $m)) {
                $browser = 'firefox-' . (string)$m[1];
            } elseif (preg_match('~version/(\d+).+safari~i', $ua, $m)) {
                $browser = 'safari-' . (string)$m[1];
            } elseif (preg_match('~safari/(\d+)~i', $ua, $m)) {
                $browser = 'safari-' . (string)$m[1];
            }
            $fpNowRelaxed = hash('sha256', 'cliente|' . $browser . '|' . $ipPrefix);
            $fpStored = (string)($_SESSION['session_fp'] ?? '');
            $fpStoredRelaxed = (string)($_SESSION['session_fp_relaxed'] ?? '');
            $fpStrictOk = ($fpStored !== '' && hash_equals($fpStored, $fpNow));
            $fpRelaxedOk = ($fpStoredRelaxed !== '' && hash_equals($fpStoredRelaxed, $fpNowRelaxed));
            if (!$fpStrictOk && !$fpRelaxedOk) {
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
        $isSuperadmin = (strpos((string)$currentPath, '/upload/scp/superadmin/') !== false);
        if (strpos($currentPath, '/upload/scp/') !== false) {
            header('Location: ' . ($isSuperadmin ? '../login.php' : 'login.php'));
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

        $alwaysRaw = trim((string)getAppSetting('billing.always_active_empresas', '1'));
        $alwaysIds = [];
        if ($alwaysRaw !== '') {
            foreach (preg_split('/\s*,\s*/', $alwaysRaw) as $v) {
                if ($v === '') continue;
                if (is_numeric($v)) {
                    $n = (int)$v;
                    if ($n > 0) $alwaysIds[$n] = true;
                }
            }
        }
        $alwaysIds[1] = true;
        $isAlwaysActiveEmpresa = ($empresaId > 0 && isset($alwaysIds[$empresaId]));

        if ($isAlwaysActiveEmpresa) {
            $_SESSION['read_only'] = 0;
            $_SESSION['read_only_reason'] = '';
        }
        if ($empresaId > 0 && isset($mysqli) && $mysqli) {
            try {
                $hasEmpresas = dbTableExists('empresas');
                if ($hasEmpresas) {
                    $q = $mysqli->prepare('SELECT bloqueada, motivo_bloqueo, estado_pago, fecha_vencimiento FROM empresas WHERE id = ? LIMIT 1');
                    if ($q) {
                        $q->bind_param('i', $empresaId);
                        if ($q->execute()) {
                            $row = $q->get_result()->fetch_assoc();
                            $isBlocked = (int)($row['bloqueada'] ?? 0) === 1;
                            if ($isBlocked) {
                                $motivo = (string)($row['motivo_bloqueo'] ?? 'Servicio suspendido por falta de pago');
                                $currentPath = (string)($_SERVER['PHP_SELF'] ?? '');
                                $basePrefix = '';
                                $posUpload = strpos($currentPath, '/upload/');
                                if ($posUpload !== false) {
                                    $basePrefix = substr($currentPath, 0, $posUpload);
                                }
                                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                                $host = (string)($_SERVER['HTTP_HOST'] ?? '');
                                $logoutPath = $basePrefix . '/upload/scp/logout.php';
                                $logoutHref = ($host !== '') ? ($scheme . '://' . $host . $logoutPath) : $logoutPath;
                                http_response_code(403);
                                echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
                                    . '<title>Servicio suspendido</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">'
                                    . '<style>'
                                    . 'body{min-height:100vh;background:radial-gradient(1200px 700px at 12% 14%,rgba(13,110,253,.24),transparent 62%),radial-gradient(980px 580px at 88% 82%,rgba(102,16,242,.14),transparent 58%),linear-gradient(180deg,#081225 0%,#0b1b3a 55%,#eef4ff 100%);}'
                                    . '.blk-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}'
                                    . '.blk-card{width:min(760px,96vw);border:1px solid rgba(255,255,255,.20);border-radius:18px;overflow:hidden;box-shadow:0 18px 55px rgba(16,24,40,.22);background:rgba(255,255,255,.94);backdrop-filter:blur(10px);}'
                                    . '.blk-head{padding:18px 18px;background:linear-gradient(135deg,rgba(13,110,253,.98),rgba(13,110,253,.72));color:#fff;display:flex;gap:12px;align-items:center;}'
                                    . '.blk-ico{width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.14);display:flex;align-items:center;justify-content:center;}'
                                    . '.blk-title{margin:0;font-weight:800;letter-spacing:.2px;font-size:18px;}'
                                    . '.blk-sub{margin:0;opacity:.92;font-size:13px;}'
                                    . '.blk-body{padding:18px;}'
                                    . '.blk-kv{background:rgba(13,110,253,.06);border:1px solid rgba(13,110,253,.18);border-radius:14px;padding:12px 14px;}'
                                    . '.blk-kv strong{color:#0a58ca;}'
                                    . '</style></head><body>'
                                    . '<div class="blk-wrap"><div class="blk-card">'
                                    . '<div class="blk-head">'
                                    . '<div class="blk-ico" aria-hidden="true">'
                                    . '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.964 0L.165 13.233c-.457.778.091 1.767.982 1.767h13.706c.89 0 1.438-.99.982-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>'
                                    . '</div>'
                                    . '<div><p class="blk-title">Acceso suspendido temporalmente</p><p class="blk-sub">Verificación de pago requerida para continuar</p></div>'
                                    . '</div>'
                                    . '<div class="blk-body">'
                                    . '<div class="blk-kv">'
                                    . '<div><strong>Motivo:</strong> ' . html($motivo) . '</div>'
                                    . '<div class="mt-2">Si ya realizaste el pago, comunícate con <strong>Vigitec Panamá</strong> para reactivar el servicio.</div>'
                                    . '</div>'
                                    . '<div class="d-flex gap-2 flex-wrap mt-3">'
                                    . '<a class="btn btn-primary" href="' . html($logoutHref) . '" target="_top" rel="noopener">Ir al login</a>'
                                    . '</div>'
                                    . '</div></div></div></body></html>';
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
                            if ($isVencida && !$isAlwaysActiveEmpresa) {
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
        $isSuperadmin = (strpos($currentPath, '/upload/scp/superadmin/') !== false);
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
            $bindIp = (string)getAppSetting('agents.bind_session_ip', '0') === '1';
            $ipPrefix = $bindIp ? $ipPrefix : 'no-ip';

            $fpNow = hash('sha256', 'agente|' . $ua . '|' . $ipPrefix);
            $browser = 'unknown';
            if (preg_match('~edg/(\d+)~i', $ua, $m)) {
                $browser = 'edge-' . (string)$m[1];
            } elseif (preg_match('~chrome/(\d+)~i', $ua, $m)) {
                $browser = 'chrome-' . (string)$m[1];
            } elseif (preg_match('~firefox/(\d+)~i', $ua, $m)) {
                $browser = 'firefox-' . (string)$m[1];
            } elseif (preg_match('~version/(\d+).+safari~i', $ua, $m)) {
                $browser = 'safari-' . (string)$m[1];
            } elseif (preg_match('~safari/(\d+)~i', $ua, $m)) {
                $browser = 'safari-' . (string)$m[1];
            }
            $fpNowRelaxed = hash('sha256', 'agente|' . $browser . '|' . $ipPrefix);
            $fpStored = (string)($_SESSION['session_fp'] ?? '');
            $fpStoredRelaxed = (string)($_SESSION['session_fp_relaxed'] ?? '');
            $fpStrictOk = ($fpStored !== '' && hash_equals($fpStored, $fpNow));
            $fpRelaxedOk = ($fpStoredRelaxed !== '' && hash_equals($fpStoredRelaxed, $fpNowRelaxed));
            if (!$fpStrictOk && !$fpRelaxedOk) {
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
                $currentPath = (string)($_SERVER['PHP_SELF'] ?? '');
                $isSuperadmin = (strpos($currentPath, '/upload/scp/superadmin/') !== false);
                if (strpos($currentPath, '/upload/scp/') !== false) {
                    header('Location: ' . ($isSuperadmin ? '../login.php?msg=timeout' : 'login.php?msg=timeout'));
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
                $isSuperadmin = (strpos((string)$currentPath, '/upload/scp/superadmin/') !== false);
                if (strpos($currentPath, '/upload/scp/') !== false) {
                    header('Location: ' . ($isSuperadmin ? '../login.php?msg=timeout' : 'login.php?msg=timeout'));
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
                $isSuperadmin = (strpos((string)$currentPath, '/upload/scp/superadmin/') !== false);
                if (strpos($currentPath, '/upload/scp/') !== false) {
                    header('Location: ' . ($isSuperadmin ? '../login.php?msg=ip' : 'login.php?msg=ip'));
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

    $alwaysRaw = trim((string)getAppSetting('billing.always_active_empresas', '1'));
    $alwaysIds = [];
    if ($alwaysRaw !== '') {
        foreach (preg_split('/\s*,\s*/', $alwaysRaw) as $v) {
            if ($v === '') continue;
            if (is_numeric($v)) {
                $n = (int)$v;
                if ($n > 0) $alwaysIds[$n] = true;
            }
        }
    }
    if (isset($alwaysIds[$empresaId])) {
        try {
            $mysqli->query("UPDATE empresas SET estado_pago = 'al_dia', bloqueada = 0, motivo_bloqueo = NULL WHERE id = {$empresaId}");
        } catch (Throwable $e) {}
        return true;
    }

    try {
        $hasEmpresas = dbTableExists('empresas');
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

function ensureBillingNoticeLogTable() {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return false;
    $sql = "CREATE TABLE IF NOT EXISTS billing_notice_log (\n"
        . "  id INT PRIMARY KEY AUTO_INCREMENT,\n"
        . "  empresa_id INT NOT NULL,\n"
        . "  days_before INT NOT NULL,\n"
        . "  fecha_vencimiento DATE NOT NULL,\n"
        . "  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  UNIQUE KEY uq_billing_notice (empresa_id, days_before, fecha_vencimiento),\n"
        . "  KEY idx_empresa (empresa_id)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    return (bool)$mysqli->query($sql);
}

function syncAllEmpresasBillingStatus() {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return false;
    try {
        $hasEmpresas = dbTableExists('empresas');
        if (!$hasEmpresas) return false;

        $alwaysRaw = trim((string)getAppSetting('billing.always_active_empresas', '1'));
        $alwaysIds = [];
        if ($alwaysRaw !== '') {
            foreach (preg_split('/\s*,\s*/', $alwaysRaw) as $v) {
                if ($v === '') continue;
                if (is_numeric($v)) {
                    $n = (int)$v;
                    if ($n > 0) $alwaysIds[$n] = true;
                }
            }
        }
        $notAlwaysSql = '';
        if (!empty($alwaysIds)) {
            $notAlwaysSql = ' AND id NOT IN (' . implode(',', array_map('intval', array_keys($alwaysIds))) . ')';
        }

        // Al vencer: pasa a suspendido (sin bloquear)
        $mysqli->query("UPDATE empresas SET estado_pago = 'suspendido'
                        WHERE fecha_vencimiento IS NOT NULL
                          AND DATEDIFF(fecha_vencimiento, CURDATE()) <= 0
                          AND estado_pago = 'al_dia'{$notAlwaysSql}");

        // Luego de 3 días desde el vencimiento: bloquear (mantiene suspendido)
        $mysqli->query("UPDATE empresas
                        SET bloqueada = 1,
                            motivo_bloqueo = COALESCE(NULLIF(motivo_bloqueo,''), 'Servicio suspendido por falta de pago')
                        WHERE fecha_vencimiento IS NOT NULL
                          AND DATEDIFF(CURDATE(), fecha_vencimiento) >= 3
                          AND estado_pago = 'suspendido'
                          AND bloqueada = 0{$notAlwaysSql}");

        if (!empty($alwaysIds)) {
            $mysqli->query("UPDATE empresas SET estado_pago = 'al_dia', bloqueada = 0, motivo_bloqueo = NULL WHERE id IN (" . implode(',', array_map('intval', array_keys($alwaysIds))) . ")");
        }

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

        $alwaysRaw = trim((string)getAppSetting('billing.always_active_empresas', '1'));
        $alwaysIds = [];
        if ($alwaysRaw !== '') {
            foreach (preg_split('/\s*,\s*/', $alwaysRaw) as $v) {
                if ($v === '') continue;
                if (is_numeric($v)) {
                    $n = (int)$v;
                    if ($n > 0) $alwaysIds[$n] = true;
                }
            }
        }

        if (!ensureBillingNoticeLogTable()) return false;

        if (!dbTableExists('empresas')) return false;
        if (!dbTableExists('staff')) return false;
        if (!dbTableExists('notifications')) return false;

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
        $hasStaffEmpresa = dbColumnExists('staff', 'empresa_id');
        if (!$hasStaffEmpresa) return false;

        $in = implode(',', array_map('intval', $days));
        $sql = "SELECT id, nombre, fecha_vencimiento, DATEDIFF(fecha_vencimiento, CURDATE()) dias\n"
             . "FROM empresas\n"
             . "WHERE fecha_vencimiento IS NOT NULL\n"
             . "  AND estado_pago = 'al_dia'\n"
             . "  AND bloqueada = 0\n"
             . (!empty($alwaysIds) ? ('  AND id NOT IN (' . implode(',', array_map('intval', array_keys($alwaysIds))) . ')\n') : '')
             . "  AND DATEDIFF(fecha_vencimiento, CURDATE()) IN ($in)";

        $res = $mysqli->query($sql);
        if (!$res) return true;

        $stmtStaff = $mysqli->prepare("SELECT id FROM staff WHERE is_active = 1 AND role = 'admin' AND empresa_id = ? ORDER BY id");
        $stmtLogIns = $mysqli->prepare("INSERT IGNORE INTO billing_notice_log (empresa_id, days_before, fecha_vencimiento, created_at) VALUES (?, ?, ?, NOW())");
        $stmtIns = $mysqli->prepare("INSERT INTO notifications (staff_id, message, type, related_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        if (!$stmtStaff || !$stmtLogIns || !$stmtIns) return false;

        $type = 'billing_due';

        while ($e = $res->fetch_assoc()) {
            $empresaId = (int)($e['id'] ?? 0);
            if ($empresaId <= 0) continue;
            $dias = (int)($e['dias'] ?? 0);
            $empresaNombre = (string)($e['nombre'] ?? '');
            $venc = (string)($e['fecha_vencimiento'] ?? '');

            if ($venc === '') continue;

            $stmtLogIns->bind_param('iis', $empresaId, $dias, $venc);
            if (!$stmtLogIns->execute()) {
                continue;
            }
            if ((int)$stmtLogIns->affected_rows <= 0) {
                continue;
            }

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

function dbTableExists($tableName, $ttlSeconds = 300) {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return false;
    $tableName = trim((string)$tableName);
    if ($tableName === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) return false;
    $ttlSeconds = max(5, (int)$ttlSeconds);

    static $runtimeCache = [];
    $cacheKey = 'tbl:' . strtolower($tableName);
    $now = time();
    if (isset($runtimeCache[$cacheKey]) && ($now - (int)$runtimeCache[$cacheKey]['ts']) <= $ttlSeconds) {
        return (bool)$runtimeCache[$cacheKey]['ok'];
    }

    if (!isset($_SESSION['_dbmeta_cache']) || !is_array($_SESSION['_dbmeta_cache'])) {
        $_SESSION['_dbmeta_cache'] = [];
    }
    if (isset($_SESSION['_dbmeta_cache'][$cacheKey])) {
        $hit = $_SESSION['_dbmeta_cache'][$cacheKey];
        if (is_array($hit) && ($now - (int)($hit['ts'] ?? 0)) <= $ttlSeconds) {
            $ok = (bool)($hit['ok'] ?? false);
            $runtimeCache[$cacheKey] = ['ts' => $now, 'ok' => $ok];
            return $ok;
        }
    }

    $res = $mysqli->query("SHOW TABLES LIKE '" . $mysqli->real_escape_string($tableName) . "'");
    $ok = (bool)($res && $res->num_rows > 0);
    $runtimeCache[$cacheKey] = ['ts' => $now, 'ok' => $ok];
    $_SESSION['_dbmeta_cache'][$cacheKey] = ['ts' => $now, 'ok' => $ok];
    return $ok;
}

function dbColumnExists($tableName, $columnName, $ttlSeconds = 300) {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return false;
    $tableName = trim((string)$tableName);
    $columnName = trim((string)$columnName);
    if ($tableName === '' || $columnName === '') return false;
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName) || !preg_match('/^[a-zA-Z0-9_]+$/', $columnName)) return false;
    $ttlSeconds = max(5, (int)$ttlSeconds);

    static $runtimeCache = [];
    $cacheKey = 'col:' . strtolower($tableName) . ':' . strtolower($columnName);
    $now = time();
    if (isset($runtimeCache[$cacheKey]) && ($now - (int)$runtimeCache[$cacheKey]['ts']) <= $ttlSeconds) {
        return (bool)$runtimeCache[$cacheKey]['ok'];
    }

    if (!isset($_SESSION['_dbmeta_cache']) || !is_array($_SESSION['_dbmeta_cache'])) {
        $_SESSION['_dbmeta_cache'] = [];
    }
    if (isset($_SESSION['_dbmeta_cache'][$cacheKey])) {
        $hit = $_SESSION['_dbmeta_cache'][$cacheKey];
        if (is_array($hit) && ($now - (int)($hit['ts'] ?? 0)) <= $ttlSeconds) {
            $ok = (bool)($hit['ok'] ?? false);
            $runtimeCache[$cacheKey] = ['ts' => $now, 'ok' => $ok];
            return $ok;
        }
    }

    $res = $mysqli->query("SHOW COLUMNS FROM `" . $mysqli->real_escape_string($tableName) . "` LIKE '" . $mysqli->real_escape_string($columnName) . "'");
    $ok = (bool)($res && $res->num_rows > 0);
    $runtimeCache[$cacheKey] = ['ts' => $now, 'ok' => $ok];
    $_SESSION['_dbmeta_cache'][$cacheKey] = ['ts' => $now, 'ok' => $ok];
    return $ok;
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

/**
 * Limpia texto plano que pueda contener entidades HTML residuales del editor
 * (ej: asunto del ticket con &nbsp; insertado por teclado móvil).
 * Decodifica entidades HTML, elimina tags, y retorna texto seguro para re-escapar.
 */
function cleanPlainText(string $text): string {
    // Decodificar entidades HTML (incluye &nbsp; → espacio normal)
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Eliminar cualquier etiqueta HTML residual
    $text = strip_tags($text);
    // Normalizar espacios múltiples y espacios no rompibles (\xc2\xa0)
    $text = str_replace("\xc2\xa0", ' ', $text);
    $text = preg_replace('/\s{2,}/', ' ', $text) ?? $text;
    return trim($text);
}

function sanitizeRichText($inputHtml) {
    $inputHtml = (string)$inputHtml;
    if ($inputHtml === '') return '';
    if (stripos($inputHtml, '<') === false) {
        // Decodificar entidades HTML literales (ej: &nbsp; insertado por teclado móvil/editor)
        // antes de re-escapar, para que no aparezcan como texto "&nbsp;" en pantalla.
        $decoded = html_entity_decode($inputHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return nl2br(html($decoded));
    }

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

    // saveHTML() puede devolver un documento HTML completo con <html><body> en algunos entornos.
    // Extraemos solo el contenido del <body> si existe, o usamos saveHTML nodo a nodo.
    $out = '';
    $body = $doc->getElementsByTagName('body')->item(0);
    if ($body) {
        foreach ($body->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
    } else {
        $out = $doc->saveHTML();
        // Eliminar la declaración xml y posibles wrappers html/body
        $out = preg_replace('~^<\?xml[^>]*>~i', '', (string)$out);
        $out = preg_replace('~^<!DOCTYPE[^>]*>~i', '', $out);
        $out = preg_replace('~<html[^>]*>|</html>|<body[^>]*>|</body>|<head[^>]*>.*?</head>~is', '', $out);
    }

    // DOMDocument::saveHTML convierte &nbsp; (\xc2\xa0) a &#160; — restaurar a &nbsp; para renderizado correcto
    $out = str_replace(['&#160;', "\xc2\xa0"], '&nbsp;', (string)$out);

    return trim((string)$out);
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

    static $ensured = null;
    if ($ensured !== null) {
        return (bool)$ensured;
    }

    $sql = "CREATE TABLE IF NOT EXISTS app_settings (\n"
        . "  `empresa_id` INT NOT NULL DEFAULT 1,\n"
        . "  `key` VARCHAR(191) NOT NULL,\n"
        . "  `value` LONGTEXT NULL,\n"
        . "  `updated` DATETIME NULL,\n"
        . "  PRIMARY KEY (`empresa_id`, `key`)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if (!$mysqli->query($sql)) {
        $ensured = false;
        return false;
    }

    $hasEmpresa = false;
    $hasEmpresa = dbColumnExists('app_settings', 'empresa_id');
    if (!$hasEmpresa) {
        $mysqli->query("ALTER TABLE app_settings ADD COLUMN empresa_id INT NOT NULL DEFAULT 1");
    }

    $hasEmpresa2 = dbColumnExists('app_settings', 'empresa_id');
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

        // Asegurar que no exista un UNIQUE/PRIMARY legacy sobre `key` solamente,
        // porque haría que los cambios de una empresa afecten a otra.
        $idxU = $mysqli->query("SHOW INDEX FROM app_settings WHERE Key_name = 'uq_app_settings_key'");
        if ($idxU && $idxU->num_rows > 0) {
            @$mysqli->query('ALTER TABLE app_settings DROP INDEX uq_app_settings_key');
        }

        $idxComposite = $mysqli->query("SHOW INDEX FROM app_settings WHERE Key_name = 'uq_app_settings_empresa_key'");
        if (!$idxComposite || $idxComposite->num_rows < 1) {
            @$mysqli->query('ALTER TABLE app_settings ADD UNIQUE KEY uq_app_settings_empresa_key (empresa_id, `key`)');
        }
    }

    $ensured = true;
    return true;
}

function getAppSetting($key, $default = null) {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return $default;
    if (!ensureAppSettingsTable()) return $default;
    $key = (string)$key;

    static $hasEmpresa = null;
    if ($hasEmpresa === null) {
        $hasEmpresa = dbColumnExists('app_settings', 'empresa_id');
    }

    static $valueCache = [];
    $cacheScope = $hasEmpresa ? ('e' . empresaId()) : 'global';
    $cacheKey = $cacheScope . ':' . $key;
    if (array_key_exists($cacheKey, $valueCache)) {
        return $valueCache[$cacheKey];
    }

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
    $valueCache[$cacheKey] = $row ? ($row['value'] ?? $default) : $default;
    return $valueCache[$cacheKey];
}

function setAppSetting($key, $value) {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return false;
    if (!ensureAppSettingsTable()) return false;
    $key = (string)$key;
    $value = $value !== null ? (string)$value : null;

    static $hasEmpresa = null;
    if ($hasEmpresa === null) {
        $hasEmpresa = dbColumnExists('app_settings', 'empresa_id');
    }

    static $valueCache = [];
    if ($hasEmpresa) {
        $eid = empresaId();
        $stmt = $mysqli->prepare('INSERT INTO app_settings (`empresa_id`, `key`, `value`, `updated`) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated` = NOW()');
        if (!$stmt) return false;
        $stmt->bind_param('iss', $eid, $key, $value);
        $ok = $stmt->execute();
        if ($ok) $valueCache['e' . $eid . ':' . $key] = $value;
        return $ok;
    }

    $stmt = $mysqli->prepare('INSERT INTO app_settings (`key`, `value`, `updated`) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated` = NOW()');
    if (!$stmt) return false;
    $stmt->bind_param('ss', $key, $value);
    $ok = $stmt->execute();
    if ($ok) $valueCache['global:' . $key] = $value;
    return $ok;
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
    if ($val === '' && function_exists('empresaId')) {
        $eid = (int)empresaId();
        if ($eid !== 1) {
            global $mysqli;
            if (isset($mysqli) && $mysqli) {
                try {
                    if (ensureAppSettingsTable()) {
                        $stmt = $mysqli->prepare('SELECT `value` FROM app_settings WHERE `empresa_id` = 1 AND `key` = ? LIMIT 1');
                        if ($stmt) {
                            $k = (string)$settingKey;
                            $stmt->bind_param('s', $k);
                            if ($stmt->execute()) {
                                $row = $stmt->get_result()->fetch_assoc();
                                $val = (string)($row['value'] ?? '');
                            }
                        }
                    }
                } catch (Throwable $e) {
                }
            }
        }
    }
    if ($val !== '') {
        return toAppAbsoluteUrl($val);
    }
    return toAppAbsoluteUrl($fallbackRelativePath);
}

function getDefaultCompanyLogoRelativePath() {
    $candidates = [
        'publico/img/vigitec-logo.webp',
        'publico/img/vigitec-logo.png',
        'publico/img/vigitec-logo.jpg',
        'publico/img/vigitec-logo.jpeg',
        'publico/img/vigitec-logo.gif',
        'publico/img/vigitec-logo.svg',
    ];

    $rootAbs = realpath(__DIR__ . '/..');
    if ($rootAbs) {
        $rootAbs = rtrim((string)$rootAbs, '/\\');
        foreach ($candidates as $candidate) {
            $fs = $rootAbs . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
            if (is_file($fs)) {
                return $candidate;
            }
        }
    }

    return $candidates[0];
}

function getCompanyLogoUrl($fallbackRelativePath = '') {
    $mode = (string)getAppSetting('company.logo_mode', '');
    $logo = (string)getAppSetting('company.logo', '');
    $fallbackRelativePath = (string)$fallbackRelativePath;
    if ($fallbackRelativePath === '' || preg_match('~^publico/img/vigitec-logo\.(?:png|webp|jpg|jpeg|gif|svg)$~i', $fallbackRelativePath)) {
        $fallbackRelativePath = getDefaultCompanyLogoRelativePath();
    }

    if (($mode === '' && $logo === '') && function_exists('empresaId')) {
        $eid = (int)empresaId();
        if ($eid !== 1) {
            global $mysqli;
            if (isset($mysqli) && $mysqli) {
                try {
                    if (ensureAppSettingsTable()) {
                        $stmt = $mysqli->prepare("SELECT `key`, `value` FROM app_settings WHERE `empresa_id` = 1 AND `key` IN ('company.logo_mode','company.logo')");
                        if ($stmt && $stmt->execute()) {
                            $res = $stmt->get_result();
                            while ($row = $res->fetch_assoc()) {
                                $k = (string)($row['key'] ?? '');
                                $v = (string)($row['value'] ?? '');
                                if ($k === 'company.logo_mode' && $mode === '') $mode = $v;
                                if ($k === 'company.logo' && $logo === '') $logo = $v;
                            }
                        }
                    }
                } catch (Throwable $e) {
                }
            }
        }
    }

    if ($mode === '') {
        $mode = $logo !== '' ? 'custom' : 'default';
    }

    $finalUrl = '';
    if ($mode === 'custom' && $logo !== '') {
        $finalUrl = toAppAbsoluteUrl($logo);
    } else {
        $finalUrl = toAppAbsoluteUrl($fallbackRelativePath);
    }

    try {
        $path = (string)parse_url($finalUrl, PHP_URL_PATH);
        if ($path !== '') {
            $rootAbs = realpath(__DIR__ . '/..');
            $publicAbs = $rootAbs ? (rtrim((string)$rootAbs, '/\\') . DIRECTORY_SEPARATOR . 'publico') : '';
            $uploadAbs = $rootAbs ? (rtrim((string)$rootAbs, '/\\') . DIRECTORY_SEPARATOR . 'upload') : '';

            $ver = 1;
            $posPub = strpos($path, '/publico/');
            if ($posPub !== false && $publicAbs !== '') {
                $rel = substr($path, $posPub + 9);
                $fs = rtrim($publicAbs, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($rel, '/'));
                if (is_file($fs)) {
                    $ver = (int)@filemtime($fs);
                    if ($ver <= 0) $ver = 1;
                }
            } else {
                $posUp = strpos($path, '/upload/');
                if ($posUp !== false && $uploadAbs !== '') {
                    $rel = substr($path, $posUp + 7);
                    $fs = rtrim($uploadAbs, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($rel, '/'));
                    if (is_file($fs)) {
                        $ver = (int)@filemtime($fs);
                        if ($ver <= 0) $ver = 1;
                    }
                }
            }

            if ($ver > 1) {
                $finalUrl .= (strpos($finalUrl, '?') !== false ? '&' : '?') . 'v=' . (string)$ver;
            }
        }
    } catch (Throwable $e) {
    }

    return $finalUrl;
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
    $hasEmpresa = dbColumnExists('logs', 'empresa_id');

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

function ensureEmailQueueTable() {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return false;

    $sql = "CREATE TABLE IF NOT EXISTS email_queue (\n"
        . "  id BIGINT PRIMARY KEY AUTO_INCREMENT,\n"
        . "  empresa_id INT NOT NULL DEFAULT 1,\n"
        . "  recipient_email VARCHAR(255) NOT NULL,\n"
        . "  subject VARCHAR(255) NOT NULL,\n"
        . "  body_html MEDIUMTEXT NULL,\n"
        . "  body_text MEDIUMTEXT NULL,\n"
        . "  status VARCHAR(20) NOT NULL DEFAULT 'pending',\n"
        . "  attempts INT NOT NULL DEFAULT 0,\n"
        . "  max_attempts INT NOT NULL DEFAULT 5,\n"
        . "  next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  last_error TEXT NULL,\n"
        . "  context_type VARCHAR(50) NULL,\n"
        . "  context_id INT NULL,\n"
        . "  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  sent_at DATETIME NULL,\n"
        . "  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        . "  KEY idx_email_queue_status_next (status, next_attempt_at),\n"
        . "  KEY idx_email_queue_empresa (empresa_id),\n"
        . "  KEY idx_email_queue_context (context_type, context_id)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if (!$mysqli->query($sql)) return false;

    if (!dbColumnExists('email_queue', 'empresa_id')) {
        @$mysqli->query("ALTER TABLE email_queue ADD COLUMN empresa_id INT NOT NULL DEFAULT 1");
        @$mysqli->query("ALTER TABLE email_queue ADD INDEX idx_email_queue_empresa (empresa_id)");
    }
    return true;
}

function ensureEmailLogsTable() {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return false;
    $sql = "CREATE TABLE IF NOT EXISTS email_logs (\n"
        . "  id BIGINT PRIMARY KEY AUTO_INCREMENT,\n"
        . "  empresa_id INT NOT NULL DEFAULT 1,\n"
        . "  queue_id BIGINT NULL,\n"
        . "  recipient_email VARCHAR(255) NULL,\n"
        . "  status VARCHAR(20) NOT NULL,\n"
        . "  error_message TEXT NULL,\n"
        . "  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  KEY idx_email_logs_empresa (empresa_id),\n"
        . "  KEY idx_email_logs_queue (queue_id),\n"
        . "  KEY idx_email_logs_status (status)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if (!$mysqli->query($sql)) return false;
    return true;
}

function ensureNotificationRecipientsTable() {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return false;
    $sql = "CREATE TABLE IF NOT EXISTS notification_recipients (\n"
        . "  id INT PRIMARY KEY AUTO_INCREMENT,\n"
        . "  empresa_id INT NOT NULL DEFAULT 1,\n"
        . "  staff_id INT NOT NULL,\n"
        . "  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  UNIQUE KEY uq_notification_recipient (empresa_id, staff_id),\n"
        . "  KEY idx_notification_staff (staff_id),\n"
        . "  KEY idx_notification_empresa (empresa_id)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if (!$mysqli->query($sql)) return false;
    return true;
}

function parseEmailList($rawEmails) {
    $out = [
        'valid' => [],
        'invalid' => [],
    ];
    $raw = (string)$rawEmails;
    if ($raw === '') return $out;

    $parts = preg_split('/[;,]+/', $raw);
    if (!is_array($parts)) return $out;

    $seen = [];
    foreach ($parts as $item) {
        $email = strtolower(trim((string)$item));
        if ($email === '') continue;
        if (isset($seen[$email])) continue;
        $seen[$email] = true;
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $out['valid'][] = $email;
        } else {
            $out['invalid'][] = $email;
        }
    }
    return $out;
}

function enqueueEmailJob($to, $subject, $bodyHtml, $bodyText = '', array $meta = []) {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return false;
    if (!ensureEmailQueueTable()) return false;

    $to = strtolower(trim((string)$to));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $eid = isset($meta['empresa_id']) && is_numeric($meta['empresa_id'])
        ? (int)$meta['empresa_id']
        : (function_exists('empresaId') ? (int)empresaId() : (int)($_SESSION['empresa_id'] ?? 1));
    if ($eid <= 0) $eid = 1;

    $contextType = isset($meta['context_type']) ? trim((string)$meta['context_type']) : null;
    if ($contextType === '') $contextType = null;
    $contextId = isset($meta['context_id']) && is_numeric($meta['context_id']) ? (int)$meta['context_id'] : null;
    $maxAttempts = isset($meta['max_attempts']) && is_numeric($meta['max_attempts']) ? (int)$meta['max_attempts'] : 5;
    if ($maxAttempts < 1) $maxAttempts = 1;
    if ($maxAttempts > 15) $maxAttempts = 15;

    $subject = trim((string)$subject);
    if ($subject === '') $subject = '(Sin asunto)';

    $stmt = $mysqli->prepare(
        "INSERT INTO email_queue (empresa_id, recipient_email, subject, body_html, body_text, status, attempts, max_attempts, next_attempt_at, context_type, context_id, created_at, updated_at)\n"
        . "VALUES (?, ?, ?, ?, ?, 'pending', 0, ?, NOW(), ?, ?, NOW(), NOW())"
    );
    if (!$stmt) return false;
    $htmlBody = (string)$bodyHtml;
    $textBody = (string)$bodyText;
    $stmt->bind_param('issssisi', $eid, $to, $subject, $htmlBody, $textBody, $maxAttempts, $contextType, $contextId);
    return (bool)$stmt->execute();
}

function addEmailLog($status, $errorMessage = '', array $meta = []) {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return false;
    if (!ensureEmailLogsTable()) return false;

    $status = trim((string)$status);
    if ($status === '') $status = 'unknown';
    $error = trim((string)$errorMessage);
    if ($error === '') $error = null;

    $eid = isset($meta['empresa_id']) && is_numeric($meta['empresa_id'])
        ? (int)$meta['empresa_id']
        : (function_exists('empresaId') ? (int)empresaId() : (int)($_SESSION['empresa_id'] ?? 1));
    if ($eid <= 0) $eid = 1;

    $queueId = isset($meta['queue_id']) && is_numeric($meta['queue_id']) ? (int)$meta['queue_id'] : null;
    $recipient = isset($meta['recipient_email']) ? trim((string)$meta['recipient_email']) : null;
    if ($recipient === '') $recipient = null;

    $stmt = $mysqli->prepare('INSERT INTO email_logs (empresa_id, queue_id, recipient_email, status, error_message, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    if (!$stmt) return false;
    $stmt->bind_param('iisss', $eid, $queueId, $recipient, $status, $error);
    return (bool)$stmt->execute();
}

function triggerEmailQueueWorkerAsync($limit = 30) {
    $limit = (int)$limit;
    if ($limit < 1) $limit = 1;
    if ($limit > 100) $limit = 100;

    $token = trim((string)getAppSetting('mail.queue_worker_token', ''));
    if ($token === '') {
        try {
            $token = bin2hex(random_bytes(24));
            setAppSetting('mail.queue_worker_token', $token);
        } catch (Throwable $e) {
            error_log('[mail_queue] token generation failed: ' . $e->getMessage());
            return false;
        }
    }

      $appUrl = defined('APP_URL') ? APP_URL : '';
      if ($appUrl !== '') {
          $basePath = (string)parse_url($appUrl, PHP_URL_PATH);
          $baseDir = rtrim($basePath, '/');
      } else {
          $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '/upload/open.php');
          $baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
          $baseDir = preg_replace('#/(upload|agente)(/.*)?$#i', '', $baseDir);
      }
      $workerPath = $baseDir . '/upload/process_mail_queue.php';
    $eid = function_exists('empresaId') ? (int)empresaId() : (int)($_SESSION['empresa_id'] ?? 1);
    if ($eid <= 0) $eid = 1;
    $qs = http_build_query(['token' => $token, 'limit' => $limit, 'eid' => $eid]);
    $path = $workerPath . '?' . $qs;

    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $hostOnly = preg_replace('/:\d+$/', '', $host);
    if ($hostOnly === '') $hostOnly = 'localhost';
    $port = 80;
    $scheme = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = 'https';
        $port = 443;
    }
    if (preg_match('/:(\d+)$/', $host, $m)) {
        $port = (int)$m[1];
    }

    $transport = ($scheme === 'https') ? 'ssl://' : '';
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($transport . $hostOnly, $port, $errno, $errstr, 1.5);
    if (!$fp) {
        error_log('[mail_queue] async trigger failed: ' . $hostOnly . ':' . $port . ' ' . $errstr . ' (' . $errno . ')');
        return false;
    }

    stream_set_blocking($fp, false);
    $out = "GET " . $path . " HTTP/1.1\r\n";
    $out .= "Host: " . $host . "\r\n";
    $out .= "Connection: Close\r\n\r\n";
    @fwrite($fp, $out);
    @fclose($fp);
    return true;
}

function ensureRolePermissionsTable() {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return false;
    $sql = "CREATE TABLE IF NOT EXISTS role_permissions (\n"
        . "  id INT PRIMARY KEY AUTO_INCREMENT,\n"
        . "  empresa_id INT NOT NULL DEFAULT 1,\n"
        . "  role_name VARCHAR(100) NOT NULL,\n"
        . "  perm_key VARCHAR(120) NOT NULL,\n"
        . "  is_enabled TINYINT(1) NOT NULL DEFAULT 1,\n"
        . "  created DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
        . "  updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        . "  UNIQUE KEY uq_role_perm (empresa_id, role_name, perm_key),\n"
        . "  KEY idx_role (role_name),\n"
        . "  KEY idx_perm (perm_key),\n"
        . "  KEY idx_role_perm_empresa (empresa_id, role_name)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $ok = (bool)$mysqli->query($sql);

    try {
        $hasEmpresaId = dbColumnExists('role_permissions', 'empresa_id');
        if (!$hasEmpresaId) {
            $mysqli->query("ALTER TABLE role_permissions ADD COLUMN empresa_id INT NOT NULL DEFAULT 1");
            $mysqli->query("ALTER TABLE role_permissions ADD INDEX idx_role_perm_empresa (empresa_id, role_name)");
        }

        $idxOld = $mysqli->query("SHOW INDEX FROM role_permissions WHERE Key_name = 'uq_role_perm'");
        if ($idxOld && $idxOld->num_rows > 0) {
            $mysqli->query("ALTER TABLE role_permissions DROP INDEX uq_role_perm");
        }

        $idxNew = $mysqli->query("SHOW INDEX FROM role_permissions WHERE Key_name = 'uq_role_perm_empresa_role_perm'");
        if (!$idxNew || $idxNew->num_rows < 1) {
            $mysqli->query("ALTER TABLE role_permissions ADD UNIQUE KEY uq_role_perm_empresa_role_perm (empresa_id, role_name, perm_key)");
        }
    } catch (Throwable $e) {
    }

    return $ok;
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

    $eid = empresaId();
    $hasEmpresa = false;
    try {
        $hasEmpresa = dbColumnExists('role_permissions', 'empresa_id');
    } catch (Throwable $e) {
        $hasEmpresa = false;
    }

    $stmt = $hasEmpresa
        ? $mysqli->prepare('SELECT 1 FROM role_permissions WHERE empresa_id = ? AND role_name = ? AND perm_key = ? AND is_enabled = 1 LIMIT 1')
        : $mysqli->prepare('SELECT 1 FROM role_permissions WHERE role_name = ? AND perm_key = ? AND is_enabled = 1 LIMIT 1');
    if (!$stmt) return false;

    if ($hasEmpresa) {
        $stmt->bind_param('iss', $eid, $role, $permKey);
    } else {
        $stmt->bind_param('ss', $role, $permKey);
    }
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

    $eid = empresaId();
    $hasEmpresa = false;
    try {
        $hasEmpresa = dbColumnExists('role_permissions', 'empresa_id');
    } catch (Throwable $e) {
        $hasEmpresa = false;
    }

    $stmt = $hasEmpresa
        ? $mysqli->prepare('SELECT 1 FROM role_permissions WHERE empresa_id = ? AND role_name = ? AND perm_key LIKE ? AND is_enabled = 1 LIMIT 1')
        : $mysqli->prepare('SELECT 1 FROM role_permissions WHERE role_name = ? AND perm_key LIKE ? AND is_enabled = 1 LIMIT 1');
    if (!$stmt) return false;

    if ($hasEmpresa) {
        $stmt->bind_param('iss', $eid, $role, $like);
    } else {
        $stmt->bind_param('ss', $role, $like);
    }
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

    $fallback = toAppAbsoluteUrl('upload/scp/index.php');
    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref !== '') {
        $refHost = (string)parse_url($ref, PHP_URL_HOST);
        $curHost = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($refHost === '' || $refHost === $curHost) {
            $refPath = (string)parse_url($ref, PHP_URL_PATH);
            if ($refPath !== '' && strpos($refPath, '/upload/scp/') !== false) {
                $fallback = $ref;
            }
        }
    }

    http_response_code(403);
    header('Location: ' . $fallback);
    exit;
}
?>
