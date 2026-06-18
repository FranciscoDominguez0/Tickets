<?php
/**
 * FUNCIONES AUXILIARES
 */

// Proteger página (requiere login)
function requireLogin($type = 'user')
{
    global $mysqli;
    if ((string) getAppSetting('system.force_https', '0') === '1') {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        if (!$isHttps && !headers_sent() && !empty($_SERVER['HTTP_HOST']) && !empty($_SERVER['REQUEST_URI'])) {
            header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
    if ($type === 'cliente') {
        $status = (string) getAppSetting('system.helpdesk_status', 'online');
        if ($status === 'offline') {
            $currentPath = (string) ($_SERVER['PHP_SELF'] ?? '');
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
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $returnUrl = basename($currentPath) . ($queryString !== '' ? '?' . $queryString : '');
        $returnParam = ($returnUrl !== '' && $returnUrl !== 'login.php') ? '?return=' . urlencode($returnUrl) : '';

        if (strpos($currentPath, '/upload/') !== false) {
            header('Location: login.php' . $returnParam);
        } else {
            header('Location: upload/login.php' . $returnParam);
        }
        exit;
    }

    if ($type === 'cliente' && isset($_SESSION['user_id'])) {
        $empresaId = (int) ($_SESSION['empresa_id'] ?? 0);
        if ($empresaId > 0 && isset($mysqli) && $mysqli) {
            try {
                $hasEmpresas = dbTableExists('empresas');
                if ($hasEmpresas) {
                    $q = $mysqli->prepare('SELECT bloqueada, motivo_bloqueo FROM empresas WHERE id = ? LIMIT 1');
                    if ($q) {
                        $q->bind_param('i', $empresaId);
                        if ($q->execute()) {
                            $row = $q->get_result()->fetch_assoc();
                            $isBlocked = (int) ($row['bloqueada'] ?? 0) === 1;
                            if ($isBlocked) {
                                $motivo = (string) ($row['motivo_bloqueo'] ?? 'Servicio suspendido por falta de pago');
                                $currentPath = (string) ($_SERVER['PHP_SELF'] ?? '');
                                $basePrefix = '';
                                $posUpload = strpos($currentPath, '/upload/');
                                if ($posUpload !== false) {
                                    $basePrefix = substr($currentPath, 0, $posUpload);
                                }
                                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                                $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
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
            $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
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
            $bindIp = (string) getAppSetting('users.bind_session_ip', '0') === '1';
            $ipPrefix = $bindIp ? $ipPrefix : 'no-ip';

            $fpNow = hash('sha256', 'cliente|' . $ua . '|' . $ipPrefix);
            $browser = 'unknown';
            if (preg_match('~edg/(\d+)~i', $ua, $m)) {
                $browser = 'edge-' . (string) $m[1];
            } elseif (preg_match('~chrome/(\d+)~i', $ua, $m)) {
                $browser = 'chrome-' . (string) $m[1];
            } elseif (preg_match('~firefox/(\d+)~i', $ua, $m)) {
                $browser = 'firefox-' . (string) $m[1];
            } elseif (preg_match('~version/(\d+).+safari~i', $ua, $m)) {
                $browser = 'safari-' . (string) $m[1];
            } elseif (preg_match('~safari/(\d+)~i', $ua, $m)) {
                $browser = 'safari-' . (string) $m[1];
            }
            $fpNowRelaxed = hash('sha256', 'cliente|' . $browser . '|' . $ipPrefix);
            $fpStored = (string) ($_SESSION['session_fp'] ?? '');
            $fpStoredRelaxed = (string) ($_SESSION['session_fp_relaxed'] ?? '');

            $fpStrictOk = ($fpStored !== '' && hash_equals($fpStored, $fpNow));
            $fpRelaxedOk = ($fpStoredRelaxed !== '' && hash_equals($fpStoredRelaxed, $fpNowRelaxed));

            // Si falla el fingerprint, solo cerramos sesion si no hay coincidencia relajada
            // y no destruimos la sesion de inmediato para evitar bugs en moviles con pre-fetch
            if (!$fpStrictOk && !$fpRelaxedOk) {
                // En lugar de destruir todo, solo invalidamos si es un cambio mayor de IP o UA radical
                // Por ahora, solo redirigimos si no hay session_fp (sesion nueva)
                if ($fpStored === '') {
                    $_SESSION = [];
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_destroy();
                    }
                    $currentPath = (string) ($_SERVER['PHP_SELF'] ?? '');
                    if (strpos($currentPath, '/upload/') !== false) {
                        header('Location: login.php?msg=timeout');
                    } else {
                        header('Location: upload/login.php?msg=timeout');
                    }
                    exit;
                }
            }
        }

        $timeoutMin = (int) getAppSetting('users.session_timeout_minutes', '30');
        if (!isset($_SESSION['user_last_activity']) || (int) ($_SESSION['user_last_activity'] ?? 0) <= 0) {
            $_SESSION['user_last_activity'] = time();
        }
        if ($timeoutMin > 0) {
            $last = (int) ($_SESSION['user_last_activity'] ?? 0);
            if ($last > 0 && (time() - $last) > ($timeoutMin * 60)) {
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
                $currentPath = (string) ($_SERVER['PHP_SELF'] ?? '');
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
        $isSuperadmin = (strpos((string) $currentPath, '/upload/scp/superadmin/') !== false);
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

        $empresaId = (int) ($_SESSION['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            syncEmpresaBillingStatus($empresaId);
        }

        $alwaysRaw = trim((string) getAppSetting('billing.always_active_empresas', '1'));
        $alwaysIds = [];
        if ($alwaysRaw !== '') {
            foreach (preg_split('/\s*,\s*/', $alwaysRaw) as $v) {
                if ($v === '')
                    continue;
                if (is_numeric($v)) {
                    $n = (int) $v;
                    if ($n > 0)
                        $alwaysIds[$n] = true;
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
                            $isBlocked = (int) ($row['bloqueada'] ?? 0) === 1;
                            if ($isBlocked) {
                                $motivo = (string) ($row['motivo_bloqueo'] ?? 'Servicio suspendido por falta de pago');
                                $currentPath = (string) ($_SERVER['PHP_SELF'] ?? '');
                                $basePrefix = '';
                                $posUpload = strpos($currentPath, '/upload/');
                                if ($posUpload !== false) {
                                    $basePrefix = substr($currentPath, 0, $posUpload);
                                }
                                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                                $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
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

                            $estadoPago = (string) ($row['estado_pago'] ?? '');
                            $fechaVenc = (string) ($row['fecha_vencimiento'] ?? '');
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

        $currentPath = (string) ($_SERVER['PHP_SELF'] ?? '');
        $isScp = (strpos($currentPath, '/upload/scp/') !== false);
        $isSuperadmin = (strpos($currentPath, '/upload/scp/superadmin/') !== false);
        if ($isScp && (int) ($_SESSION['read_only'] ?? 0) === 1) {
            $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            if ($method === 'POST') {
                http_response_code(403);
                $motivo = (string) ($_SESSION['read_only_reason'] ?? 'Pago vencido. Comuníquese con Vigitec Panamá.');
                echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Modo lectura</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css"></head><body class="bg-light"><div class="container py-5" style="max-width:720px"><div class="alert alert-warning"><strong>Modo lectura.</strong><div class="mt-2">' . html($motivo) . '</div></div><a class="btn btn-outline-secondary" href="javascript:history.back()">Volver</a></div></body></html>';
                exit;
            }
        }

        if (!empty($_SESSION['session_fp'])) {
            $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
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
            $bindIp = (string) getAppSetting('agents.bind_session_ip', '0') === '1';
            $ipPrefix = $bindIp ? $ipPrefix : 'no-ip';

            $fpNow = hash('sha256', 'agente|' . $ua . '|' . $ipPrefix);
            $browser = 'unknown';
            if (preg_match('~edg/(\d+)~i', $ua, $m)) {
                $browser = 'edge-' . (string) $m[1];
            } elseif (preg_match('~chrome/(\d+)~i', $ua, $m)) {
                $browser = 'chrome-' . (string) $m[1];
            } elseif (preg_match('~firefox/(\d+)~i', $ua, $m)) {
                $browser = 'firefox-' . (string) $m[1];
            } elseif (preg_match('~version/(\d+).+safari~i', $ua, $m)) {
                $browser = 'safari-' . (string) $m[1];
            } elseif (preg_match('~safari/(\d+)~i', $ua, $m)) {
                $browser = 'safari-' . (string) $m[1];
            }
            $fpNowRelaxed = hash('sha256', 'agente|' . $browser . '|' . $ipPrefix);
            $fpStored = (string) ($_SESSION['session_fp'] ?? '');
            $fpStoredRelaxed = (string) ($_SESSION['session_fp_relaxed'] ?? '');
            $fpStrictOk = ($fpStored !== '' && hash_equals($fpStored, $fpNow));
            $fpRelaxedOk = ($fpStoredRelaxed !== '' && hash_equals($fpStoredRelaxed, $fpNowRelaxed));
            if (!$fpStrictOk && !$fpRelaxedOk) {
                if ($fpStored === '') {
                    $_SESSION = [];
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_destroy();
                    }
                    $currentPath = (string) ($_SERVER['PHP_SELF'] ?? '');
                    $isSuperadmin = (strpos($currentPath, '/upload/scp/superadmin/') !== false);
                    if (strpos($currentPath, '/upload/scp/') !== false) {
                        header('Location: ' . ($isSuperadmin ? '../login.php?msg=timeout' : 'login.php?msg=timeout'));
                    } else {
                        header('Location: ../upload/scp/login.php?msg=timeout');
                    }
                    exit;
                }
            }
        }

        $timeoutMin = (int) getAppSetting('agents.session_timeout_minutes', '30');
        if (!isset($_SESSION['staff_last_activity']) || (int) ($_SESSION['staff_last_activity'] ?? 0) <= 0) {
            $_SESSION['staff_last_activity'] = time();
        }
        if ($timeoutMin > 0) {
            $last = (int) ($_SESSION['staff_last_activity'] ?? 0);
            if ($last > 0 && (time() - $last) > ($timeoutMin * 60)) {
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
                $currentPath = $_SERVER['PHP_SELF'];
                $isSuperadmin = (strpos((string) $currentPath, '/upload/scp/superadmin/') !== false);
                if (strpos($currentPath, '/upload/scp/') !== false) {
                    header('Location: ' . ($isSuperadmin ? '../login.php?msg=timeout' : 'login.php?msg=timeout'));
                } else {
                    header('Location: ../upload/scp/login.php?msg=timeout');
                }
                exit;
            }
        }

        $bindIp = (string) getAppSetting('agents.bind_session_ip', '0') === '1';
        if ($bindIp) {
            $currentIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            $loginIp = (string) ($_SESSION['staff_login_ip'] ?? '');
            if ($loginIp !== '' && $currentIp !== '' && $loginIp !== $currentIp) {
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
                $currentPath = $_SERVER['PHP_SELF'];
                $isSuperadmin = (strpos((string) $currentPath, '/upload/scp/superadmin/') !== false);
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

function syncEmpresaBillingStatus($empresaId)
{
    global $mysqli;
    $empresaId = (int) $empresaId;
    if ($empresaId <= 0)
        return false;
    if (!isset($mysqli) || !$mysqli)
        return false;

    $alwaysRaw = trim((string) getAppSetting('billing.always_active_empresas', '1'));
    $alwaysIds = [];
    if ($alwaysRaw !== '') {
        foreach (preg_split('/\s*,\s*/', $alwaysRaw) as $v) {
            if ($v === '')
                continue;
            if (is_numeric($v)) {
                $n = (int) $v;
                if ($n > 0)
                    $alwaysIds[$n] = true;
            }
        }
    }
    if (isset($alwaysIds[$empresaId])) {
        try {
            $mysqli->query("UPDATE empresas SET estado_pago = 'al_dia', bloqueada = 0, motivo_bloqueo = NULL WHERE id = {$empresaId}");
        } catch (Throwable $e) {
        }
        return true;
    }

    try {
        $hasEmpresas = dbTableExists('empresas');
        if (!$hasEmpresas)
            return false;

        $stmt = $mysqli->prepare('SELECT estado_pago, bloqueada, fecha_vencimiento FROM empresas WHERE id = ? LIMIT 1');
        if (!$stmt)
            return false;
        $stmt->bind_param('i', $empresaId);
        if (!$stmt->execute())
            return false;
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row)
            return false;

        $estadoPago = (string) ($row['estado_pago'] ?? '');
        $bloqueada = (int) ($row['bloqueada'] ?? 0) === 1;
        $fechaVenc = (string) ($row['fecha_vencimiento'] ?? '');
        if ($fechaVenc === '')
            return true;

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
            $daysPast = (int) floor((strtotime($hoy) - strtotime($fechaVenc)) / 86400);
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

function ensureBillingNoticeLogTable()
{
    return true;
}

function syncAllEmpresasBillingStatus()
{
    global $mysqli;
    if (!isset($mysqli) || !$mysqli)
        return false;
    try {
        $hasEmpresas = dbTableExists('empresas');
        if (!$hasEmpresas)
            return false;

        $alwaysRaw = trim((string) getAppSetting('billing.always_active_empresas', '1'));
        $alwaysIds = [];
        if ($alwaysRaw !== '') {
            foreach (preg_split('/\s*,\s*/', $alwaysRaw) as $v) {
                if ($v === '')
                    continue;
                if (is_numeric($v)) {
                    $n = (int) $v;
                    if ($n > 0)
                        $alwaysIds[$n] = true;
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

function sendBillingDueNotifications()
{
    global $mysqli;
    if (!isset($mysqli) || !$mysqli)
        return false;

    try {
        $enabled = (string) getAppSetting('billing.notice_enabled', '1');
        if ($enabled !== '1')
            return true;

        $alwaysRaw = trim((string) getAppSetting('billing.always_active_empresas', '1'));
        $alwaysIds = [];
        if ($alwaysRaw !== '') {
            foreach (preg_split('/\s*,\s*/', $alwaysRaw) as $v) {
                if ($v === '')
                    continue;
                if (is_numeric($v)) {
                    $n = (int) $v;
                    if ($n > 0)
                        $alwaysIds[$n] = true;
                }
            }
        }

        if (!ensureBillingNoticeLogTable())
            return false;

        if (!dbTableExists('empresas'))
            return false;
        if (!dbTableExists('staff'))
            return false;
        if (!dbTableExists('notifications'))
            return false;

        $daysRaw = trim((string) getAppSetting('billing.notice_days', '3'));
        $daysList = [];
        foreach (preg_split('/\s*,\s*/', $daysRaw) as $d) {
            if ($d === '')
                continue;
            if (is_numeric($d)) {
                $n = (int) $d;
                if ($n > 0 && $n <= 365)
                    $daysList[$n] = true;
            }
        }
        $days = array_keys($daysList);
        if (empty($days))
            return true;

        $subjectTpl = trim((string) getAppSetting('billing.notice_subject', 'Aviso: vencimiento próximo'));
        $msgTpl = trim((string) getAppSetting('billing.notice_message', 'Tu plan vence en {dias} día(s) ({vencimiento}).'));

        $hasStaffEmpresa = false;
        $hasStaffEmpresa = dbColumnExists('staff', 'empresa_id');
        if (!$hasStaffEmpresa)
            return false;

        $in = implode(',', array_map('intval', $days));
        $sql = "SELECT id, nombre, fecha_vencimiento, DATEDIFF(fecha_vencimiento, CURDATE()) dias\n"
            . "FROM empresas\n"
            . "WHERE fecha_vencimiento IS NOT NULL\n"
            . "  AND estado_pago = 'al_dia'\n"
            . "  AND bloqueada = 0\n"
            . (!empty($alwaysIds) ? ('  AND id NOT IN (' . implode(',', array_map('intval', array_keys($alwaysIds))) . ')\n') : '')
            . "  AND DATEDIFF(fecha_vencimiento, CURDATE()) IN ($in)";

        $res = $mysqli->query($sql);
        if (!$res)
            return true;

        $stmtStaff = $mysqli->prepare("SELECT id FROM staff WHERE is_active = 1 AND role = 'admin' AND empresa_id = ? ORDER BY id");
        $stmtLogIns = $mysqli->prepare("INSERT IGNORE INTO billing_notice_log (empresa_id, days_before, fecha_vencimiento, created_at) VALUES (?, ?, ?, NOW())");
        $stmtIns = $mysqli->prepare("INSERT INTO notifications (staff_id, message, type, related_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        if (!$stmtStaff || !$stmtLogIns || !$stmtIns)
            return false;

        $type = 'billing_due';

        while ($e = $res->fetch_assoc()) {
            $empresaId = (int) ($e['id'] ?? 0);
            if ($empresaId <= 0)
                continue;
            $dias = (int) ($e['dias'] ?? 0);
            $empresaNombre = (string) ($e['nombre'] ?? '');
            $venc = (string) ($e['fecha_vencimiento'] ?? '');

            if ($venc === '')
                continue;

            $stmtLogIns->bind_param('iis', $empresaId, $dias, $venc);
            if (!$stmtLogIns->execute()) {
                continue;
            }
            if ((int) $stmtLogIns->affected_rows <= 0) {
                continue;
            }

            $subject = $subjectTpl;
            $message = $msgTpl;
            $repl = [
                '{empresa}' => $empresaNombre,
                '{dias}' => (string) $dias,
                '{vencimiento}' => $venc,
            ];
            $subject = strtr($subject, $repl);
            $message = strtr($message, $repl);
            $final = $subject !== '' ? ('[' . $subject . '] ' . $message) : $message;

            $stmtStaff->bind_param('i', $empresaId);
            if (!$stmtStaff->execute())
                continue;
            $rsStaff = $stmtStaff->get_result();
            if (!$rsStaff)
                continue;
            while ($s = $rsStaff->fetch_assoc()) {
                $sid = (int) ($s['id'] ?? 0);
                if ($sid <= 0)
                    continue;

                $stmtIns->bind_param('issi', $sid, $final, $type, $empresaId);
                $stmtIns->execute();
            }
        }

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function empresaId()
{
    $eid = (int) ($_SESSION['empresa_id'] ?? 1);
    if ($eid <= 0)
        $eid = 1;
    return $eid;
}

function dbTableExists($tableName, $ttlSeconds = 300)
{
    global $mysqli;
    if (!isset($mysqli) || !$mysqli)
        return false;
    $tableName = trim((string) $tableName);
    if ($tableName === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $tableName))
        return false;

    // Optimización: tablas que sabemos que existen y son fijas en producción
    $knownTables = [
        'empresas' => true,
        'staff' => true,
        'notifications' => true,
        'tickets' => true,
        'thread_entries' => true,
        'threads' => true,
        'users' => true,
        'organizations' => true,
        'user_organizations' => true,
        'ticket_reports' => true,
        'ticket_approvals' => true,
        'ticket_report_items' => true,
        'role_permissions' => true,
        'roles' => true,
        'sequences' => true,
        'banlist' => true,
        'email_accounts' => true,
        'billing_notice_log' => true,
        'thread_entry_reads' => true,
        'departments' => true,
        'help_topics' => true,
        'staff_departments' => true,
        'staff_ticket_seen' => true,
        'staff_reports_seen' => true,
        'app_settings' => true,
        'logs' => true,
        'email_queue' => true,
        'ticket_links' => true,
        'email_logs' => true,
        'notification_recipients' => true,
    ];
    $lowerTable = strtolower($tableName);
    if (isset($knownTables[$lowerTable])) {
        return true;
    }

    $ttlSeconds = max(5, (int) $ttlSeconds);

    static $runtimeCache = [];
    $cacheKey = 'tbl:' . $lowerTable;
    $now = time();
    if (isset($runtimeCache[$cacheKey]) && ($now - (int) $runtimeCache[$cacheKey]['ts']) <= $ttlSeconds) {
        return (bool) $runtimeCache[$cacheKey]['ok'];
    }

    if (!isset($_SESSION['_dbmeta_cache']) || !is_array($_SESSION['_dbmeta_cache'])) {
        $_SESSION['_dbmeta_cache'] = [];
    }
    if (isset($_SESSION['_dbmeta_cache'][$cacheKey])) {
        $hit = $_SESSION['_dbmeta_cache'][$cacheKey];
        if (is_array($hit) && ($now - (int) ($hit['ts'] ?? 0)) <= $ttlSeconds) {
            $ok = (bool) ($hit['ok'] ?? false);
            $runtimeCache[$cacheKey] = ['ts' => $now, 'ok' => $ok];
            return $ok;
        }
    }

    $res = $mysqli->query("SHOW TABLES LIKE '" . $mysqli->real_escape_string($tableName) . "'");
    $ok = (bool) ($res && $res->num_rows > 0);
    $runtimeCache[$cacheKey] = ['ts' => $now, 'ok' => $ok];
    $_SESSION['_dbmeta_cache'][$cacheKey] = ['ts' => $now, 'ok' => $ok];
    return $ok;
}

function dbColumnExists($tableName, $columnName, $ttlSeconds = 300)
{
    global $mysqli;
    if (!isset($mysqli) || !$mysqli)
        return false;
    $tableName = trim((string) $tableName);
    $columnName = trim((string) $columnName);
    if ($tableName === '' || $columnName === '')
        return false;
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName) || !preg_match('/^[a-zA-Z0-9_]+$/', $columnName))
        return false;

    // Optimización: columnas que sabemos que existen en producción
    $knownColumns = [
        'tickets:closed' => true,
        'tickets:client_signature' => true,
        'tickets:close_message' => true,
        'tickets:closed_at' => true,
        'tickets:signature_token' => true,
        'tickets:signature_requested' => true,
        'tickets:topic_id' => true,
        'tickets:walkin_phone' => true,
        'tickets:walkin_address' => true,
        'staff:empresa_id' => true,
        'staff:signature' => true,
        'thread_entries:empresa_id' => true,
        'threads:empresa_id' => true,
        'organizations:plain_text_emails' => true,
        'users:org_tickets_view' => true,
        'users:phone' => true,
        'users:status' => true,
        'users:created' => true,
        'app_settings:empresa_id' => true,
        'logs:empresa_id' => true,
        'role_permissions:empresa_id' => true,
        'roles:empresa_id' => true,
        'sequences:empresa_id' => true,
        'banlist:empresa_id' => true,
        'email_accounts:empresa_id' => true,
        'departments:empresa_id' => true,
        'departments:requires_report' => true,
        'departments:default_staff_id' => true,
        'help_topics:is_public' => true,
        'ticket_reports:billing_status' => true,
        'ticket_links:empresa_id' => true,
        'staff:role' => true,
        'staff:dept_id' => true,
    ];
    $lowerKey = strtolower($tableName) . ':' . strtolower($columnName);
    if (isset($knownColumns[$lowerKey])) {
        return true;
    }

    $ttlSeconds = max(5, (int) $ttlSeconds);

    static $runtimeCache = [];
    $cacheKey = 'col:' . strtolower($tableName) . ':' . strtolower($columnName);
    $now = time();
    if (isset($runtimeCache[$cacheKey]) && ($now - (int) $runtimeCache[$cacheKey]['ts']) <= $ttlSeconds) {
        return (bool) $runtimeCache[$cacheKey]['ok'];
    }

    if (!isset($_SESSION['_dbmeta_cache']) || !is_array($_SESSION['_dbmeta_cache'])) {
        $_SESSION['_dbmeta_cache'] = [];
    }
    if (isset($_SESSION['_dbmeta_cache'][$cacheKey])) {
        $hit = $_SESSION['_dbmeta_cache'][$cacheKey];
        if (is_array($hit) && ($now - (int) ($hit['ts'] ?? 0)) <= $ttlSeconds) {
            $ok = (bool) ($hit['ok'] ?? false);
            $runtimeCache[$cacheKey] = ['ts' => $now, 'ok' => $ok];
            return $ok;
        }
    }

    $res = $mysqli->query("SHOW COLUMNS FROM `" . $mysqli->real_escape_string($tableName) . "` LIKE '" . $mysqli->real_escape_string($columnName) . "'");
    $ok = (bool) ($res && $res->num_rows > 0);
    $runtimeCache[$cacheKey] = ['ts' => $now, 'ok' => $ok];
    $_SESSION['_dbmeta_cache'][$cacheKey] = ['ts' => $now, 'ok' => $ok];
    return $ok;
}

/**
 * Tabla de acuses de lectura por mensaje (estilo WhatsApp).
 */
function ensureThreadEntryReadsTable($mysqli): void
{
    // Tabla ya creada
}

/** Marca mensajes del agente como leídos por el cliente. */
function markThreadEntriesReadByUser($mysqli, int $threadId, int $userId, int $empresaId): void
{
    if ($threadId <= 0 || $userId <= 0 || !isset($mysqli) || !$mysqli) {
        return;
    }
    ensureThreadEntryReadsTable($mysqli);
    if (!dbTableExists('thread_entry_reads')) {
        return;
    }
    $hasEmpresaTe = dbColumnExists('thread_entries', 'empresa_id');
    $sql = "INSERT IGNORE INTO thread_entry_reads (empresa_id, thread_entry_id, read_by, reader_id, read_at)\n"
        . "SELECT ?, te.id, 'user', ?, NOW()\n"
        . "FROM thread_entries te\n"
        . "WHERE te.thread_id = ? AND te.staff_id IS NOT NULL AND COALESCE(te.is_internal, 0) = 0";
    if ($hasEmpresaTe) {
        $sql .= " AND te.empresa_id = ?";
    }
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return;
    }
    if ($hasEmpresaTe) {
        $stmt->bind_param('iiii', $empresaId, $userId, $threadId, $empresaId);
    } else {
        $stmt->bind_param('iii', $empresaId, $userId, $threadId);
    }
    $stmt->execute();
}

/** Marca mensajes del cliente como leídos por el agente. */
function markThreadEntriesReadByStaff($mysqli, int $threadId, int $staffId, int $empresaId): void
{
    if ($threadId <= 0 || $staffId <= 0 || !isset($mysqli) || !$mysqli) {
        return;
    }
    ensureThreadEntryReadsTable($mysqli);
    if (!dbTableExists('thread_entry_reads')) {
        return;
    }
    $hasEmpresaTe = dbColumnExists('thread_entries', 'empresa_id');
    $sql = "INSERT IGNORE INTO thread_entry_reads (empresa_id, thread_entry_id, read_by, reader_id, read_at)\n"
        . "SELECT ?, te.id, 'staff', 0, NOW()\n"
        . "FROM thread_entries te\n"
        . "WHERE te.thread_id = ? AND te.user_id IS NOT NULL AND COALESCE(te.is_internal, 0) = 0";
    if ($hasEmpresaTe) {
        $sql .= " AND te.empresa_id = ?";
    }
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return;
    }
    if ($hasEmpresaTe) {
        $stmt->bind_param('iii', $empresaId, $threadId, $empresaId);
    } else {
        $stmt->bind_param('ii', $empresaId, $threadId);
    }
    $stmt->execute();
}

/**
 * @return array<int, array{user: bool, staff: bool}>
 */
function getThreadEntryReadStatusMap($mysqli, array $entryIds, int $empresaId): array
{
    $map = [];
    $entryIds = array_values(array_unique(array_filter(array_map('intval', $entryIds), static fn($id) => $id > 0)));
    if (empty($entryIds) || !isset($mysqli) || !$mysqli) {
        return $map;
    }
    ensureThreadEntryReadsTable($mysqli);
    if (!dbTableExists('thread_entry_reads')) {
        return $map;
    }
    $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
    $types = str_repeat('i', count($entryIds));
    $sql = "SELECT thread_entry_id, read_by FROM thread_entry_reads WHERE thread_entry_id IN ($placeholders) AND empresa_id = ?";
    $types .= 'i';
    $entryIds[] = $empresaId;
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return $map;
    }
    $stmt->bind_param($types, ...$entryIds);
    if (!$stmt->execute()) {
        return $map;
    }
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $eid = (int) ($row['thread_entry_id'] ?? 0);
        if ($eid <= 0) {
            continue;
        }
        if (!isset($map[$eid])) {
            $map[$eid] = ['user' => false, 'staff' => false];
        }
        $readBy = (string) ($row['read_by'] ?? '');
        if ($readBy === 'user' || $readBy === 'staff') {
            $map[$eid][$readBy] = true;
        }
    }
    return $map;
}

function threadEntryReadReceiptHtml(bool $isRead, bool $iconFirst = true): string
{
    $icon = $isRead
        ? '<i class="bi bi-check2-all" style="color:#34b7f1;font-weight:bold;" title="Leído"></i>'
        : '<i class="bi bi-check2-all" style="color:#9ca3af;" title="Enviado"></i>';
    $label = 'Enviado';
    if ($iconFirst) {
        return $icon . ' ' . $label;
    }
    return $label . ' ' . $icon;
}

/** Tabla usuario ↔ organizaciones (muchos a muchos). */
function ensureUserOrganizationsTable($mysqli)
{
    return true;
}

/**
 * @return array<int, array{organization_id:int, name:string, created_at:?string}>
 */
function getUserOrganizations($mysqli, int $userId, int $empresaId): array
{
    if ($userId <= 0 || $empresaId <= 0 || !isset($mysqli) || !$mysqli) {
        return [];
    }
    if (!ensureUserOrganizationsTable($mysqli) || !dbTableExists('organizations')) {
        return [];
    }
    $stmt = $mysqli->prepare(
        "SELECT uo.organization_id, o.name, uo.created_at\n"
        . "FROM user_organizations uo\n"
        . "INNER JOIN organizations o ON o.id = uo.organization_id AND o.empresa_id = uo.empresa_id\n"
        . "WHERE uo.user_id = ? AND uo.empresa_id = ?\n"
        . "ORDER BY o.name ASC"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ii', $userId, $empresaId);
    if (!$stmt->execute()) {
        return [];
    }
    $rows = [];
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = [
            'organization_id' => (int) ($row['organization_id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'created_at' => $row['created_at'] ?? null,
        ];
    }
    return $rows;
}

function syncUserCompanyFromOrganizations($mysqli, int $userId, int $empresaId): void
{
    if ($userId <= 0 || $empresaId <= 0 || !isset($mysqli) || !$mysqli) {
        return;
    }
    $orgs = getUserOrganizations($mysqli, $userId, $empresaId);
    $primary = !empty($orgs) ? (string) ($orgs[0]['name'] ?? '') : '';
    $primary = trim($primary);
    $stmt = $mysqli->prepare('UPDATE users SET company = ?, updated = NOW() WHERE id = ? AND empresa_id = ?');
    if (!$stmt) {
        return;
    }
    if ($primary === '') {
        $null = null;
        $stmt->bind_param('sii', $null, $userId, $empresaId);
    } else {
        $stmt->bind_param('sii', $primary, $userId, $empresaId);
    }
    $stmt->execute();
}

function addUserToOrganization($mysqli, int $userId, int $organizationId, int $empresaId): bool
{
    if ($userId <= 0 || $organizationId <= 0 || $empresaId <= 0 || !isset($mysqli) || !$mysqli) {
        return false;
    }
    if (!ensureUserOrganizationsTable($mysqli) || !dbTableExists('organizations')) {
        return false;
    }
    $stmt = $mysqli->prepare(
        'SELECT u.id FROM users u WHERE u.id = ? AND u.empresa_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $userId, $empresaId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        return false;
    }
    $stmt = $mysqli->prepare(
        'SELECT o.id FROM organizations o WHERE o.id = ? AND o.empresa_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $organizationId, $empresaId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        return false;
    }
    $stmt = $mysqli->prepare(
        'INSERT IGNORE INTO user_organizations (empresa_id, user_id, organization_id, created_at) VALUES (?, ?, ?, NOW())'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iii', $empresaId, $userId, $organizationId);
    if (!$stmt->execute()) {
        return false;
    }
    syncUserCompanyFromOrganizations($mysqli, $userId, $empresaId);
    return true;
}

function removeUserFromOrganization($mysqli, int $userId, int $organizationId, int $empresaId): bool
{
    if ($userId <= 0 || $organizationId <= 0 || $empresaId <= 0 || !isset($mysqli) || !$mysqli) {
        return false;
    }
    if (!ensureUserOrganizationsTable($mysqli)) {
        return false;
    }
    $stmt = $mysqli->prepare(
        'DELETE FROM user_organizations WHERE user_id = ? AND organization_id = ? AND empresa_id = ?'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iii', $userId, $organizationId, $empresaId);
    if (!$stmt->execute()) {
        return false;
    }
    syncUserCompanyFromOrganizations($mysqli, $userId, $empresaId);
    return true;
}

/** Tabla puente lista para consultas de organizaciones. */
function organizationMembershipEnabled($mysqli): bool
{
    return ensureUserOrganizationsTable($mysqli) && dbTableExists('organizations');
}

/**
 * JOIN users ↔ organization (user_organizations + fallback users.company).
 */
function sqlJoinOrganizationMembers($mysqli, string $orgAlias = 'o', string $userAlias = 'u'): string
{
    if (!organizationMembershipEnabled($mysqli)) {
        return "LEFT JOIN users {$userAlias} ON {$userAlias}.company = {$orgAlias}.name AND {$userAlias}.empresa_id = {$orgAlias}.empresa_id";
    }
    return "LEFT JOIN (
        SELECT uo.empresa_id, uo.organization_id, uo.user_id
        FROM user_organizations uo
        UNION
        SELECT u.empresa_id, org_l.id, u.id
        FROM users u
        INNER JOIN organizations org_l ON org_l.name = u.company AND org_l.empresa_id = u.empresa_id
        WHERE TRIM(COALESCE(u.company, '')) <> ''
        AND NOT EXISTS (
            SELECT 1 FROM user_organizations uo2
            WHERE uo2.user_id = u.id AND uo2.empresa_id = u.empresa_id
        )
    ) org_members ON org_members.organization_id = {$orgAlias}.id AND org_members.empresa_id = {$orgAlias}.empresa_id
    LEFT JOIN users {$userAlias} ON {$userAlias}.id = org_members.user_id AND {$userAlias}.empresa_id = org_members.empresa_id";
}

/**
 * @return array{user_count:int,ticket_count:int,open_tickets:int,since:?string}
 */
function getOrganizationMembershipStats($mysqli, int $empresaId, int $organizationId, string $orgName): array
{
    $empty = ['user_count' => 0, 'ticket_count' => 0, 'open_tickets' => 0, 'since' => null];
    if ($empresaId <= 0 || !isset($mysqli) || !$mysqli) {
        return $empty;
    }
    $orgName = trim($orgName);

    if ($organizationId > 0 && organizationMembershipEnabled($mysqli)) {
        $sql = "SELECT COUNT(DISTINCT u.id) AS user_count, COUNT(DISTINCT t.id) AS ticket_count,
                SUM(CASE WHEN ts.name IN ('Abierto','En Progreso','Esperando Usuario') THEN 1 ELSE 0 END) AS open_tickets,
                MIN(u.created) AS since
            FROM users u
            LEFT JOIN tickets t ON t.user_id = u.id AND t.empresa_id = ?
            LEFT JOIN ticket_status ts ON ts.id = t.status_id
            WHERE u.empresa_id = ?
            AND (
                EXISTS (
                    SELECT 1 FROM user_organizations uo
                    WHERE uo.user_id = u.id AND uo.organization_id = ? AND uo.empresa_id = ?
                )
                OR (
                    TRIM(COALESCE(u.company, '')) <> '' AND u.company = ?
                    AND NOT EXISTS (
                        SELECT 1 FROM user_organizations uo2
                        WHERE uo2.user_id = u.id AND uo2.empresa_id = ?
                    )
                )
            )";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return $empty;
        }
        $stmt->bind_param('iiiisi', $empresaId, $empresaId, $organizationId, $empresaId, $orgName, $empresaId);
    } else {
        $sql = "SELECT COUNT(DISTINCT u.id) AS user_count, COUNT(DISTINCT t.id) AS ticket_count,
                SUM(CASE WHEN ts.name IN ('Abierto','En Progreso','Esperando Usuario') THEN 1 ELSE 0 END) AS open_tickets,
                MIN(u.created) AS since
            FROM users u
            LEFT JOIN tickets t ON t.user_id = u.id AND t.empresa_id = ?
            LEFT JOIN ticket_status ts ON ts.id = t.status_id
            WHERE u.empresa_id = ? AND u.company = ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return $empty;
        }
        $stmt->bind_param('iis', $empresaId, $empresaId, $orgName);
    }
    if (!$stmt->execute()) {
        return $empty;
    }
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return $empty;
    }
    return [
        'user_count' => (int) ($row['user_count'] ?? 0),
        'ticket_count' => (int) ($row['ticket_count'] ?? 0),
        'open_tickets' => (int) ($row['open_tickets'] ?? 0),
        'since' => $row['since'] ?? null,
    ];
}

function countOrganizationUsers($mysqli, int $empresaId, int $organizationId, string $orgName): int
{
    return getOrganizationMembershipStats($mysqli, $empresaId, $organizationId, $orgName)['user_count'];
}

/**
 * @return array<int, array<string, mixed>>
 */
function fetchOrganizationUsers($mysqli, int $empresaId, int $organizationId, string $orgName, int $limit, int $offset): array
{
    if ($empresaId <= 0 || !isset($mysqli) || !$mysqli) {
        return [];
    }
    $orgName = trim($orgName);
    $limit = max(1, $limit);
    $offset = max(0, $offset);

    if ($organizationId > 0 && organizationMembershipEnabled($mysqli)) {
        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.phone, u.status, u.created
            FROM users u
            WHERE u.empresa_id = ?
            AND (
                EXISTS (
                    SELECT 1 FROM user_organizations uo
                    WHERE uo.user_id = u.id AND uo.organization_id = ? AND uo.empresa_id = ?
                )
                OR (
                    TRIM(COALESCE(u.company, '')) <> '' AND u.company = ?
                    AND NOT EXISTS (
                        SELECT 1 FROM user_organizations uo2
                        WHERE uo2.user_id = u.id AND uo2.empresa_id = ?
                    )
                )
            )
            ORDER BY u.firstname, u.lastname
            LIMIT ? OFFSET ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('iiisiii', $empresaId, $organizationId, $empresaId, $orgName, $empresaId, $limit, $offset);
    } else {
        $sql = "SELECT id, firstname, lastname, email, phone, status, created
            FROM users WHERE empresa_id = ? AND company = ?
            ORDER BY firstname, lastname LIMIT ? OFFSET ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('isii', $empresaId, $orgName, $limit, $offset);
    }
    if (!$stmt->execute()) {
        return [];
    }
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function fetchOrganizationTickets($mysqli, int $empresaId, int $organizationId, string $orgName, int $limit, int $offset): array
{
    if ($empresaId <= 0 || !isset($mysqli) || !$mysqli) {
        return [];
    }
    $orgName = trim($orgName);
    $limit = max(1, $limit);
    $offset = max(0, $offset);

    if ($organizationId > 0 && organizationMembershipEnabled($mysqli)) {
        $sql = "SELECT t.id, t.ticket_number, t.subject, ts.name AS status_name, ts.color AS status_color,
                p.name AS priority_name, d.name AS dept_name, t.created
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            JOIN departments d ON t.dept_id = d.id
            JOIN ticket_status ts ON t.status_id = ts.id
            JOIN priorities p ON t.priority_id = p.id
            WHERE t.empresa_id = ? AND u.empresa_id = ?
            AND (
                EXISTS (
                    SELECT 1 FROM user_organizations uo
                    WHERE uo.user_id = u.id AND uo.organization_id = ? AND uo.empresa_id = ?
                )
                OR (
                    TRIM(COALESCE(u.company, '')) <> '' AND u.company = ?
                    AND NOT EXISTS (
                        SELECT 1 FROM user_organizations uo2
                        WHERE uo2.user_id = u.id AND uo2.empresa_id = ?
                    )
                )
            )
            ORDER BY CASE WHEN (SELECT status FROM ticket_approvals WHERE ticket_id = t.id ORDER BY id DESC LIMIT 1) = 'pending' THEN 0 ELSE 1 END,
                t.created DESC
            LIMIT ? OFFSET ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('iiiisiii', $empresaId, $empresaId, $organizationId, $empresaId, $orgName, $empresaId, $limit, $offset);
    } else {
        $sql = "SELECT t.id, t.ticket_number, t.subject, ts.name AS status_name, ts.color AS status_color,
                p.name AS priority_name, d.name AS dept_name, t.created
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            JOIN departments d ON t.dept_id = d.id
            JOIN ticket_status ts ON t.status_id = ts.id
            JOIN priorities p ON t.priority_id = p.id
            WHERE t.empresa_id = ? AND u.empresa_id = ? AND u.company = ?
            ORDER BY CASE WHEN (SELECT status FROM ticket_approvals WHERE ticket_id = t.id ORDER BY id DESC LIMIT 1) = 'pending' THEN 0 ELSE 1 END,
                t.created DESC
            LIMIT ? OFFSET ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('iisii', $empresaId, $empresaId, $orgName, $limit, $offset);
    }
    if (!$stmt->execute()) {
        return [];
    }
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
}

/**
 * Filtro por mes (YYYY-MM) para listados de tickets del portal.
 *
 * @return array{param: string, year: int, month: int, start: string, end: string, label: string}|null
 */
function parseTicketMonthFilter(?string $raw): ?array
{
    $raw = trim((string) $raw);
    if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $raw, $m)) {
        return null;
    }
    $year = (int) $m[1];
    $month = (int) $m[2];
    if ($year < 2000 || $year > 2100) {
        return null;
    }
    $start = sprintf('%04d-%02d-01 00:00:00', $year, $month);
    if ($month === 12) {
        $end = sprintf('%04d-01-01 00:00:00', $year + 1);
    } else {
        $end = sprintf('%04d-%02d-01 00:00:00', $year, $month + 1);
    }
    $labels = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ];
    $label = ucfirst($labels[$month] ?? '') . ' ' . $year;

    return [
        'param' => $raw,
        'year' => $year,
        'month' => $month,
        'start' => $start,
        'end' => $end,
        'label' => $label,
    ];
}

/** @return array<int, array{value: string, label: string}> */
function listTicketMonthFilterOptions(int $count = 36): array
{
    $count = max(1, min(120, $count));
    $labels = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ];
    $options = [];
    try {
        $dt = new DateTimeImmutable('first day of this month');
    } catch (Throwable $e) {
        return [];
    }
    for ($i = 0; $i < $count; $i++) {
        $y = (int) $dt->format('Y');
        $m = (int) $dt->format('m');
        $value = $dt->format('Y-m');
        $options[] = [
            'value' => $value,
            'label' => ucfirst($labels[$m] ?? '') . ' ' . $y,
        ];
        $dt = $dt->modify('-1 month');
    }
    return $options;
}

/** @param array{start: string, end: string}|null $monthFilter */
function ticketMonthFilterSqlClause(?array $monthFilter): string
{
    if (!$monthFilter || empty($monthFilter['start']) || empty($monthFilter['end'])) {
        return '';
    }
    return ' AND t.created >= ? AND t.created < ?';
}

/** @param array{param: string}|null $monthFilter */
function ticketMonthFilterQueryString(?array $monthFilter): string
{
    if (!$monthFilter || empty($monthFilter['param'])) {
        return '';
    }
    return '&month=' . rawurlencode((string) $monthFilter['param']);
}

/**
 * @param array<int, mixed> $baseParams
 * @param array<int, mixed> $monthParams
 */
function mysqliBindParams(mysqli_stmt $stmt, string $types, array $baseParams, array $monthParams = []): bool
{
    $params = array_merge($baseParams, $monthParams);
    if ($types === '' || $params === []) {
        return true;
    }
    $refs = [];
    foreach ($params as $k => $v) {
        $refs[$k] = &$params[$k];
    }
    array_unshift($refs, $types);
    return (bool) call_user_func_array([$stmt, 'bind_param'], $refs);
}

function countPortalOrganizationTickets($mysqli, int $empresaId, int $organizationId, string $orgName, ?array $monthFilter = null, ?string $searchQuery = null): int
{
    if ($empresaId <= 0 || $organizationId <= 0 || !isset($mysqli) || !$mysqli) {
        return 0;
    }
    $orgName = trim($orgName);
    $monthSql = ticketMonthFilterSqlClause($monthFilter);
    $monthTypes = $monthSql !== '' ? 'ss' : '';
    $monthParams = $monthSql !== '' ? [$monthFilter['start'], $monthFilter['end']] : [];
    
    $searchSql = '';
    $searchTypes = '';
    $searchParams = [];
    if (!empty($searchQuery)) {
        $searchSql = " AND t.ticket_number LIKE ?";
        $searchTypes = 's';
        $searchLike = '%' . trim($searchQuery) . '%';
        $searchParams = [$searchLike];
    }

    if ($organizationId > 0 && organizationMembershipEnabled($mysqli)) {
        $sql = "SELECT COUNT(DISTINCT t.id) AS c
            FROM tickets t
            INNER JOIN users u ON t.user_id = u.id AND u.empresa_id = t.empresa_id
            WHERE t.empresa_id = ? AND u.empresa_id = ?
            AND (
                EXISTS (
                    SELECT 1 FROM user_organizations uo
                    WHERE uo.user_id = u.id AND uo.organization_id = ? AND uo.empresa_id = ?
                )
                OR (
                    TRIM(COALESCE(u.company, '')) <> '' AND u.company = ?
                    AND NOT EXISTS (
                        SELECT 1 FROM user_organizations uo2
                        WHERE uo2.user_id = u.id AND uo2.empresa_id = ?
                    )
                )
            )" . $monthSql . $searchSql;
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        mysqliBindParams(
            $stmt,
            'iiiisi' . $monthTypes . $searchTypes,
            [$empresaId, $empresaId, $organizationId, $empresaId, $orgName, $empresaId],
            array_merge($monthParams, $searchParams)
        );
    } else {
        $sql = 'SELECT COUNT(*) AS c FROM tickets t
            INNER JOIN users u ON t.user_id = u.id AND u.empresa_id = t.empresa_id
            WHERE t.empresa_id = ? AND u.empresa_id = ? AND u.company = ?' . $monthSql . $searchSql;
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        mysqliBindParams($stmt, 'iis' . $monthTypes . $searchTypes, [$empresaId, $empresaId, $orgName], array_merge($monthParams, $searchParams));
    }
    if (!$stmt->execute()) {
        return 0;
    }
    return (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
}

/**
 * Tickets de todos los usuarios de la organización (portal cliente).
 *
 * @return array<int, array<string, mixed>>
 */
function fetchPortalOrganizationTickets($mysqli, int $empresaId, int $organizationId, string $orgName, int $limit, int $offset, ?array $monthFilter = null, ?string $searchQuery = null): array
{
    if ($empresaId <= 0 || $organizationId <= 0 || !isset($mysqli) || !$mysqli) {
        return [];
    }
    $orgName = trim($orgName);
    $limit = max(1, $limit);
    $offset = max(0, $offset);
    $monthSql = ticketMonthFilterSqlClause($monthFilter);
    $monthTypes = $monthSql !== '' ? 'ss' : '';
    $monthParams = $monthSql !== '' ? [$monthFilter['start'], $monthFilter['end']] : [];
    
    $searchSql = '';
    $searchTypes = '';
    $searchParams = [];
    if (!empty($searchQuery)) {
        $searchSql = " AND t.ticket_number LIKE ?";
        $searchTypes = 's';
        $searchLike = '%' . trim($searchQuery) . '%';
        $searchParams = [$searchLike];
    }

    if ($organizationId > 0 && organizationMembershipEnabled($mysqli)) {
        $sql = "SELECT t.id, t.ticket_number, t.subject, t.created, t.closed, t.user_id AS owner_user_id,
                u.firstname AS owner_firstname, u.lastname AS owner_lastname, u.email AS owner_email,
                ts.name AS status_name, ts.color AS status_color,
                (SELECT status FROM ticket_approvals WHERE ticket_id = t.id ORDER BY id DESC LIMIT 1) AS approval_status
            FROM tickets t
            INNER JOIN users u ON t.user_id = u.id AND u.empresa_id = t.empresa_id
            LEFT JOIN ticket_status ts ON t.status_id = ts.id
            WHERE t.empresa_id = ? AND u.empresa_id = ?
            AND (
                EXISTS (
                    SELECT 1 FROM user_organizations uo
                    WHERE uo.user_id = u.id AND uo.organization_id = ? AND uo.empresa_id = ?
                )
                OR (
                    TRIM(COALESCE(u.company, '')) <> '' AND u.company = ?
                    AND NOT EXISTS (
                        SELECT 1 FROM user_organizations uo2
                        WHERE uo2.user_id = u.id AND uo2.empresa_id = ?
                    )
                )
            )" . $monthSql . $searchSql . "
            ORDER BY CASE WHEN (SELECT status FROM ticket_approvals WHERE ticket_id = t.id ORDER BY id DESC LIMIT 1) = 'pending' THEN 0 ELSE 1 END,
                COALESCE(t.updated, t.created) DESC, t.id DESC
            LIMIT ? OFFSET ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return [];
        }
        mysqliBindParams(
            $stmt,
            'iiiisi' . $monthTypes . $searchTypes . 'ii',
            [$empresaId, $empresaId, $organizationId, $empresaId, $orgName, $empresaId],
            array_merge($monthParams, $searchParams, [$limit, $offset])
        );
    } else {
        $sql = "SELECT t.id, t.ticket_number, t.subject, t.created, t.closed, t.user_id AS owner_user_id,
                u.firstname AS owner_firstname, u.lastname AS owner_lastname, u.email AS owner_email,
                ts.name AS status_name, ts.color AS status_color,
                (SELECT status FROM ticket_approvals WHERE ticket_id = t.id ORDER BY id DESC LIMIT 1) AS approval_status
            FROM tickets t
            INNER JOIN users u ON t.user_id = u.id AND u.empresa_id = t.empresa_id
            LEFT JOIN ticket_status ts ON t.status_id = ts.id
            WHERE t.empresa_id = ? AND u.empresa_id = ? AND u.company = ?" . $monthSql . $searchSql . "
            ORDER BY CASE WHEN (SELECT status FROM ticket_approvals WHERE ticket_id = t.id ORDER BY id DESC LIMIT 1) = 'pending' THEN 0 ELSE 1 END,
                COALESCE(t.updated, t.created) DESC, t.id DESC
            LIMIT ? OFFSET ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return [];
        }
        mysqliBindParams(
            $stmt,
            'iis' . $monthTypes . $searchTypes . 'ii',
            [$empresaId, $empresaId, $orgName],
            array_merge($monthParams, $searchParams, [$limit, $offset])
        );
    }
    if (!$stmt->execute()) {
        return [];
    }
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
}

/** Columna users.org_tickets_view (portal: ver tickets de la organización). */
function ensureUserOrgTicketsViewColumn($mysqli): bool
{
    return true;
}

function userOrgTicketsViewEnabled($mysqli, int $userId, int $empresaId): bool
{
    if ($userId <= 0 || $empresaId <= 0 || !isset($mysqli) || !$mysqli) {
        return false;
    }
    if (!ensureUserOrgTicketsViewColumn($mysqli) || !dbColumnExists('users', 'org_tickets_view')) {
        return false;
    }
    $stmt = $mysqli->prepare('SELECT org_tickets_view FROM users WHERE id = ? AND empresa_id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $userId, $empresaId);
    if (!$stmt->execute()) {
        return false;
    }
    $row = $stmt->get_result()->fetch_assoc();
    return ((int) ($row['org_tickets_view'] ?? 0)) === 1;
}

function setUserOrgTicketsView($mysqli, int $userId, int $empresaId, bool $enabled): bool
{
    if ($userId <= 0 || $empresaId <= 0 || !isset($mysqli) || !$mysqli) {
        return false;
    }
    if (!ensureUserOrgTicketsViewColumn($mysqli)) {
        return false;
    }
    $val = $enabled ? 1 : 0;
    $stmt = $mysqli->prepare('UPDATE users SET org_tickets_view = ?, updated = NOW() WHERE id = ? AND empresa_id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iii', $val, $userId, $empresaId);
    return $stmt->execute();
}

/** ¿Dos usuarios comparten al menos una organización? */
function usersShareOrganization($mysqli, int $userIdA, int $userIdB, int $empresaId): bool
{
    if ($userIdA <= 0 || $userIdB <= 0 || $empresaId <= 0 || !isset($mysqli) || !$mysqli) {
        return false;
    }
    if ($userIdA === $userIdB) {
        return true;
    }
    if (organizationMembershipEnabled($mysqli)) {
        $stmt = $mysqli->prepare(
            'SELECT 1 FROM user_organizations a
             INNER JOIN user_organizations b
                ON b.organization_id = a.organization_id AND b.empresa_id = a.empresa_id
             WHERE a.user_id = ? AND b.user_id = ? AND a.empresa_id = ?
             LIMIT 1'
        );
        if ($stmt) {
            $stmt->bind_param('iii', $userIdA, $userIdB, $empresaId);
            if ($stmt->execute() && $stmt->get_result()->fetch_assoc()) {
                return true;
            }
        }
    }
    $stmt = $mysqli->prepare(
        "SELECT 1 FROM users ua
         INNER JOIN users ub ON ub.empresa_id = ua.empresa_id
            AND TRIM(COALESCE(ua.company, '')) <> ''
            AND ua.company = ub.company
         WHERE ua.id = ? AND ub.id = ? AND ua.empresa_id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iii', $userIdA, $userIdB, $empresaId);
    if (!$stmt->execute()) {
        return false;
    }
    return (bool) $stmt->get_result()->fetch_assoc();
}

/** Acceso del cliente a un ticket (propio o vista por organización). */
function clientUserCanAccessTicket($mysqli, int $viewerUserId, int $ticketOwnerUserId, int $empresaId): bool
{
    if ($viewerUserId <= 0 || $ticketOwnerUserId <= 0 || $empresaId <= 0 || !isset($mysqli) || !$mysqli) {
        return false;
    }
    if ($viewerUserId === $ticketOwnerUserId) {
        return true;
    }
    if (!userOrgTicketsViewEnabled($mysqli, $viewerUserId, $empresaId)) {
        return false;
    }
    return usersShareOrganization($mysqli, $viewerUserId, $ticketOwnerUserId, $empresaId);
}

/**
 * @return array<int, array{organization_id:int, name:string}>
 */
function getPortalOrganizationsForUser($mysqli, int $userId, int $empresaId): array
{
    $orgs = getUserOrganizations($mysqli, $userId, $empresaId);
    if (!empty($orgs)) {
        return $orgs;
    }
    $stmt = $mysqli->prepare(
        'SELECT id AS organization_id, name FROM organizations WHERE empresa_id = ? AND name = (
            SELECT company FROM users WHERE id = ? AND empresa_id = ? LIMIT 1
        ) LIMIT 1'
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('iii', $empresaId, $userId, $empresaId);
    if (!$stmt->execute()) {
        return [];
    }
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? [['organization_id' => (int) $row['organization_id'], 'name' => (string) $row['name']]] : [];
}

function removeOrganizationMembershipsByName($mysqli, int $empresaId, string $orgName): void
{
    if ($empresaId <= 0 || trim($orgName) === '' || !isset($mysqli) || !$mysqli) {
        return;
    }
    if (organizationMembershipEnabled($mysqli)) {
        $stmt = $mysqli->prepare(
            'DELETE uo FROM user_organizations uo
             INNER JOIN organizations o ON o.id = uo.organization_id AND o.empresa_id = uo.empresa_id
             WHERE o.empresa_id = ? AND o.name = ?'
        );
        if ($stmt) {
            $stmt->bind_param('is', $empresaId, $orgName);
            $stmt->execute();
        }
    }
    $stmt = $mysqli->prepare('UPDATE users SET company = NULL WHERE empresa_id = ? AND company = ?');
    if ($stmt) {
        $stmt->bind_param('is', $empresaId, $orgName);
        $stmt->execute();
    }
}

// Validar CSRF
function validateCSRF()
{
    if ($_POST && !Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        return false;
    }
    return true;
}

// Campo CSRF en formulario
function csrfField()
{
    echo '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars($_SESSION['csrf_token']) . '">';
}

// Escapar output (XSS prevention)
function html($text)
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Limpia texto plano que pueda contener entidades HTML residuales del editor
 * (ej: asunto del ticket con &nbsp; insertado por teclado móvil).
 * Decodifica entidades HTML, elimina tags, y retorna texto seguro para re-escapar.
 */
function cleanPlainText(string $text): string
{
    // Decodificar entidades HTML (incluye &nbsp; → espacio normal)
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Eliminar cualquier etiqueta HTML residual
    $text = strip_tags($text);
    // Normalizar espacios múltiples y espacios no rompibles (\xc2\xa0)
    $text = str_replace("\xc2\xa0", ' ', $text);
    $text = preg_replace('/\s{2,}/', ' ', $text) ?? $text;
    return trim($text);
}

function sanitizeRichText($inputHtml)
{
    $inputHtml = (string) $inputHtml;
    if ($inputHtml === '')
        return '';
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
        foreach (array_keys($allowed) as $t)
            $allowedTags .= '<' . $t . '>';
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
        $url = trim((string) $url);
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        return $url;
    };

    $isSafeUrl = function ($url) use ($normalizeProtocolRelative) {
        $url = trim((string) $url);
        if ($url === '')
            return false;
        if (strpos($url, '#') === 0)
            return true;
        if (preg_match('~^mailto:~i', $url))
            return true;
        $url = $normalizeProtocolRelative($url);
        if (preg_match('~^https?://~i', $url))
            return true;
        return false;
    };

    $isSafeImgSrc = function ($url) use ($normalizeProtocolRelative) {
        $url = trim((string) $url);
        if ($url === '')
            return false;
        $url = $normalizeProtocolRelative($url);
        if (preg_match('~^https?://~i', $url))
            return true;
        if (preg_match('~^data:image/(png|jpe?g|gif|webp);base64,~i', $url))
            return true;
        return false;
    };

    $isSafeIframeSrc = function ($url) use ($normalizeProtocolRelative) {
        $url = trim((string) $url);
        if ($url === '')
            return false;
        $url = $normalizeProtocolRelative($url);
        if (!preg_match('~^https?://~i', $url))
            return false;
        return (bool) preg_match('~^https?://(www\.)?(youtube\.com/embed/|youtube-nocookie\.com/embed/|player\.vimeo\.com/video/)~i', $url);
    };

    $walker = function ($node) use (&$walker, $allowed, $isSafeUrl, $isSafeImgSrc, $isSafeIframeSrc) {
        if (!$node || !$node->childNodes)
            return;
        // Iterate backwards because we may remove nodes
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if (!$child)
                continue;

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
                    foreach ($toRemove as $rm)
                        $child->removeAttribute($rm);
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
        $out = preg_replace('~^<\?xml[^>]*>~i', '', (string) $out);
        $out = preg_replace('~^<!DOCTYPE[^>]*>~i', '', $out);
        $out = preg_replace('~<html[^>]*>|</html>|<body[^>]*>|</body>|<head[^>]*>.*?</head>~is', '', $out);
    }

    // DOMDocument::saveHTML convierte &nbsp; (\xc2\xa0) a &#160; — restaurar a &nbsp; para renderizado correcto
    $out = str_replace(['&#160;', "\xc2\xa0"], '&nbsp;', (string) $out);

    return trim((string) $out);
}

// Formatear fecha
function formatDate($date)
{
    if (!$date)
        return '-';
    return date('d/m/Y h:i A', strtotime($date));
}

function normalizeTicketHexColor(string $color, string $fallback = '#64748b'): string
{
    $color = trim($color);
    if (!preg_match('~^#([0-9a-f]{3}|[0-9a-f]{6})$~i', $color)) {
        return $fallback;
    }
    if (preg_match('~^#([0-9a-f])([0-9a-f])([0-9a-f])$~i', $color, $m)) {
        return '#' . strtolower($m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3]);
    }
    return strtolower($color);
}

function parseTicketHexRgb(string $color): ?array
{
    $color = normalizeTicketHexColor($color, '');
    if ($color === '' || !preg_match('~^#([0-9a-f]{6})$~i', $color, $m)) {
        return null;
    }
    return [
        hexdec(substr($color, 1, 2)),
        hexdec(substr($color, 3, 2)),
        hexdec(substr($color, 5, 2)),
    ];
}

/**
 * Estado visible cuando hay aprobación ejecutiva pendiente.
 * No altera ticket_status en BD; solo la presentación en UI.
 */
function ticketEffectiveStatusDisplay($statusName, $statusColor, $approvalStatus): array
{
    if ((string) $approvalStatus === 'pending') {
        return [
            'name' => 'Pendiente aprobación',
            'color' => '#d97706',
        ];
    }

    return [
        'name' => (string) $statusName,
        'color' => (string) ($statusColor !== '' && $statusColor !== null ? $statusColor : '#64748b'),
    ];
}

function clientTicketBadgeStyle(string $color, bool $darkMode = false): string
{
    $hex = normalizeTicketHexColor($color);
    $rgb = parseTicketHexRgb($hex);
    if ($rgb === null) {
        return '--badge-bg-light:rgb(232,235,239);--badge-color-light:#64748b;--badge-bg-dark:rgb(33,39,53);--badge-color-dark:#cbd5e1;--badge-border-dark:rgb(46,54,72);';
    }
    [$r, $g, $b] = $rgb;

    $bgLightR = (int) round($r * 0.15 + 255 * 0.85);
    $bgLightG = (int) round($g * 0.15 + 255 * 0.85);
    $bgLightB = (int) round($b * 0.15 + 255 * 0.85);

    $textDarkR = (int) round($r * 0.42 + 212 * 0.58);
    $textDarkG = (int) round($g * 0.42 + 212 * 0.58);
    $textDarkB = (int) round($b * 0.42 + 216 * 0.58);

    $bgDarkR = (int) round($r * 0.14 + 24 * 0.86);
    $bgDarkG = (int) round($g * 0.14 + 24 * 0.86);
    $bgDarkB = (int) round($b * 0.14 + 27 * 0.86);

    $borderDarkR = (int) round($r * 0.26 + 24 * 0.74);
    $borderDarkG = (int) round($g * 0.26 + 24 * 0.74);
    $borderDarkB = (int) round($b * 0.26 + 27 * 0.74);

    return sprintf(
        '--badge-bg-light:rgb(%d,%d,%d);--badge-color-light:%s;--badge-bg-dark:rgb(%d,%d,%d);--badge-color-dark:rgb(%d,%d,%d);--badge-border-dark:rgb(%d,%d,%d);',
        $bgLightR,
        $bgLightG,
        $bgLightB,
        $hex,
        $bgDarkR,
        $bgDarkG,
        $bgDarkB,
        $textDarkR,
        $textDarkG,
        $textDarkB,
        $borderDarkR,
        $borderDarkG,
        $borderDarkB
    );
}

function clientTicketBadgeDotStyle(string $color, bool $darkMode = false): string
{
    $hex = normalizeTicketHexColor($color);
    $rgb = parseTicketHexRgb($hex);
    if ($rgb === null) {
        return 'background:#64748b;';
    }
    if ($darkMode) {
        return sprintf('background:rgba(%d,%d,%d,0.72);', $rgb[0], $rgb[1], $rgb[2]);
    }
    return 'background:' . $hex . ';';
}

// Redirect
function redirect($url)
{
    header("Location: $url");
    exit;
}

// GET seguro
function getQuery($key, $default = null)
{
    return $_GET[$key] ?? $default;
}

// POST seguro
function getPost($key, $default = null)
{
    return $_POST[$key] ?? $default;
}

// Obtener usuario actual
function getCurrentUser()
{
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
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? true : false;
}

// Generar número de ticket
function generateTicketNumber()
{
    return strtoupper(substr(md5(uniqid()), 0, 3)) . '-' . date('Ymd') . '-' .
        str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
}

function ensureAppSettingsTable()
{
    return true;
}

function getAppSetting($key, $default = null)
{
    global $mysqli;
    if (!isset($mysqli) || !$mysqli)
        return $default;
    if (!ensureAppSettingsTable())
        return $default;
    $key = (string) $key;

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
        if (!$stmt)
            return $default;
        $stmt->bind_param('is', $eid, $key);
    } else {
        $stmt = $mysqli->prepare('SELECT `value` FROM app_settings WHERE `key` = ? LIMIT 1');
        if (!$stmt)
            return $default;
        $stmt->bind_param('s', $key);
    }
    if (!$stmt)
        return $default;
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $valueCache[$cacheKey] = $row ? ($row['value'] ?? $default) : $default;
    return $valueCache[$cacheKey];
}

function setAppSetting($key, $value)
{
    global $mysqli;
    if (!isset($mysqli) || !$mysqli)
        return false;
    if (!ensureAppSettingsTable())
        return false;
    $key = (string) $key;
    $value = $value !== null ? (string) $value : null;

    static $hasEmpresa = null;
    if ($hasEmpresa === null) {
        $hasEmpresa = dbColumnExists('app_settings', 'empresa_id');
    }

    static $valueCache = [];
    if ($hasEmpresa) {
        $eid = empresaId();
        $stmt = $mysqli->prepare('INSERT INTO app_settings (`empresa_id`, `key`, `value`, `updated`) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated` = NOW()');
        if (!$stmt)
            return false;
        $stmt->bind_param('iss', $eid, $key, $value);
        $ok = $stmt->execute();
        if ($ok)
            $valueCache['e' . $eid . ':' . $key] = $value;
        return $ok;
    }

    $stmt = $mysqli->prepare('INSERT INTO app_settings (`key`, `value`, `updated`) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated` = NOW()');
    if (!$stmt)
        return false;
    $stmt->bind_param('ss', $key, $value);
    $ok = $stmt->execute();
    if ($ok)
        $valueCache['global:' . $key] = $value;
    return $ok;
}

function toAppAbsoluteUrl($path)
{
    $path = (string) $path;
    if ($path === '')
        return '';
    if (preg_match('~^https?://~i', $path))
        return $path;
    if ($path[0] === '/')
        return rtrim((string) APP_URL, '/') . $path;

    $p = $path;
    while (strpos($p, '../') === 0) {
        $p = substr($p, 3);
    }
    $p = ltrim($p, '/');
    return rtrim((string) APP_URL, '/') . '/' . $p;
}

function getBrandAssetUrl($settingKey, $fallbackRelativePath)
{
    $val = (string) getAppSetting($settingKey, '');
    if ($val === '' && function_exists('empresaId')) {
        $eid = (int) empresaId();
        if ($eid !== 1) {
            global $mysqli;
            if (isset($mysqli) && $mysqli) {
                try {
                    if (ensureAppSettingsTable()) {
                        $stmt = $mysqli->prepare('SELECT `value` FROM app_settings WHERE `empresa_id` = 1 AND `key` = ? LIMIT 1');
                        if ($stmt) {
                            $k = (string) $settingKey;
                            $stmt->bind_param('s', $k);
                            if ($stmt->execute()) {
                                $row = $stmt->get_result()->fetch_assoc();
                                $val = (string) ($row['value'] ?? '');
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

function getDefaultCompanyLogoRelativePath()
{
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
        $rootAbs = rtrim((string) $rootAbs, '/\\');
        foreach ($candidates as $candidate) {
            $fs = $rootAbs . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
            if (is_file($fs)) {
                return $candidate;
            }
        }
    }

    return $candidates[0];
}

function getCompanyLogoUrl($fallbackRelativePath = '')
{
    $mode = (string) getAppSetting('company.logo_mode', '');
    $logo = (string) getAppSetting('company.logo', '');
    $fallbackRelativePath = (string) $fallbackRelativePath;
    if ($fallbackRelativePath === '' || preg_match('~^publico/img/vigitec-logo\.(?:png|webp|jpg|jpeg|gif|svg)$~i', $fallbackRelativePath)) {
        $fallbackRelativePath = getDefaultCompanyLogoRelativePath();
    }

    if (($mode === '' && $logo === '') && function_exists('empresaId')) {
        $eid = (int) empresaId();
        if ($eid !== 1) {
            global $mysqli;
            if (isset($mysqli) && $mysqli) {
                try {
                    if (ensureAppSettingsTable()) {
                        $stmt = $mysqli->prepare("SELECT `key`, `value` FROM app_settings WHERE `empresa_id` = 1 AND `key` IN ('company.logo_mode','company.logo')");
                        if ($stmt && $stmt->execute()) {
                            $res = $stmt->get_result();
                            while ($row = $res->fetch_assoc()) {
                                $k = (string) ($row['key'] ?? '');
                                $v = (string) ($row['value'] ?? '');
                                if ($k === 'company.logo_mode' && $mode === '')
                                    $mode = $v;
                                if ($k === 'company.logo' && $logo === '')
                                    $logo = $v;
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
        $path = (string) parse_url($finalUrl, PHP_URL_PATH);
        if ($path !== '') {
            $rootAbs = realpath(__DIR__ . '/..');
            $publicAbs = $rootAbs ? (rtrim((string) $rootAbs, '/\\') . DIRECTORY_SEPARATOR . 'publico') : '';
            $uploadAbs = $rootAbs ? (rtrim((string) $rootAbs, '/\\') . DIRECTORY_SEPARATOR . 'upload') : '';

            $ver = 1;
            $posPub = strpos($path, '/publico/');
            if ($posPub !== false && $publicAbs !== '') {
                $rel = substr($path, $posPub + 9);
                $fs = rtrim($publicAbs, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($rel, '/'));
                if (is_file($fs)) {
                    $ver = (int) @filemtime($fs);
                    if ($ver <= 0)
                        $ver = 1;
                }
            } else {
                $posUp = strpos($path, '/upload/');
                if ($posUp !== false && $uploadAbs !== '') {
                    $rel = substr($path, $posUp + 7);
                    $fs = rtrim($uploadAbs, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($rel, '/'));
                    if (is_file($fs)) {
                        $ver = (int) @filemtime($fs);
                        if ($ver <= 0)
                            $ver = 1;
                    }
                }
            }

            if ($ver > 1) {
                $finalUrl .= (strpos($finalUrl, '?') !== false ? '&' : '?') . 'v=' . (string) $ver;
            }
        }
    } catch (Throwable $e) {
    }

    return $finalUrl;
}

function addLog($action, $details = null, $object_type = null, $object_id = null, $user_type = null, $user_id = null)
{
    global $mysqli;
    if (!isset($mysqli) || !$mysqli)
        return false;
    $action = trim((string) $action);
    if ($action === '')
        return false;

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $details = $details !== null ? (string) $details : null;
    $object_type = $object_type !== null ? (string) $object_type : null;
    $object_id = ($object_id !== null && is_numeric($object_id)) ? (int) $object_id : null;
    $user_type = $user_type !== null ? (string) $user_type : null;
    $user_id = ($user_id !== null && is_numeric($user_id)) ? (int) $user_id : null;

    $hasEmpresa = false;
    $hasEmpresa = dbColumnExists('logs', 'empresa_id');

    if ($hasEmpresa) {
        $eid = empresaId();
        $stmt = $mysqli->prepare('INSERT INTO logs (empresa_id, action, object_type, object_id, user_type, user_id, details, ip_address, created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        if (!$stmt)
            return false;
        $stmt->bind_param('ississss', $eid, $action, $object_type, $object_id, $user_type, $user_id, $details, $ip);
        return $stmt->execute();
    }

    $stmt = $mysqli->prepare('INSERT INTO logs (action, object_type, object_id, user_type, user_id, details, ip_address, created) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    if (!$stmt)
        return false;
    $stmt->bind_param('ssissss', $action, $object_type, $object_id, $user_type, $user_id, $details, $ip);
    return $stmt->execute();
}

/**
 * Notifica a los agentes configurados en "Destinatarios de notificaciones" (emailsettings.php)
 * sobre un cambio de estado importante (En Camino, En Proceso, etc.) vía campana.
 */
function notifyStatusChangeToAdminRecipients($tid, $statusName)
{
    global $mysqli;
    $eid = empresaId();
    $tid = (int) $tid;

    // Obtener info básica del ticket
    $stmtT = $mysqli->prepare("SELECT ticket_number, subject FROM tickets WHERE id = ? AND empresa_id = ?");
    if (!$stmtT)
        return;
    $stmtT->bind_param('ii', $tid, $eid);
    $stmtT->execute();
    $tRes = $stmtT->get_result();
    $tRow = $tRes ? $tRes->fetch_assoc() : null;
    if (!$tRow)
        return;

    $tNo = (string) ($tRow['ticket_number'] ?? ('#' . $tid));
    $tSub = (string) ($tRow['subject'] ?? '');
    // Mensaje simplificado: Ticket #123456 en camino
    $message = "Ticket #$tNo " . mb_strtolower($statusName);
    $type = 'ticket_assigned'; // Redirige a tickets.php?id=X

    // Obtener destinatarios configurados
    $recipients = [];
    $stmtR = $mysqli->prepare("SELECT staff_id FROM notification_recipients WHERE empresa_id = ?");
    if ($stmtR) {
        $stmtR->bind_param('i', $eid);
        if ($stmtR->execute()) {
            $resR = $stmtR->get_result();
            while ($r = $resR->fetch_assoc()) {
                $sid = (int) ($r['staff_id'] ?? 0);
                if ($sid > 0)
                    $recipients[] = $sid;
            }
        }
    }

    if (empty($recipients))
        return;

    // Evitar duplicados en la misma ejecución
    $recipients = array_unique($recipients);

    // Insertar notificaciones
    $stmtN = $mysqli->prepare("INSERT INTO notifications (empresa_id, staff_id, message, type, related_id, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
    if ($stmtN) {
        foreach ($recipients as $sid) {
            $stmtN->bind_param('iissi', $eid, $sid, $message, $type, $tid);
            $stmtN->execute();
        }
    }
}

function ensureEmailQueueTable()
{
    return true;
}

function ensureEmailLogsTable()
{
    return true;
}

function ensureNotificationRecipientsTable()
{
    return true;
}

function parseEmailList($rawEmails)
{
    $out = [
        'valid' => [],
        'invalid' => [],
    ];
    $raw = (string) $rawEmails;
    if ($raw === '')
        return $out;

    $parts = preg_split('/[;,]+/', $raw);
    if (!is_array($parts))
        return $out;

    $seen = [];
    foreach ($parts as $item) {
        $email = strtolower(trim((string) $item));
        if ($email === '')
            continue;
        if (isset($seen[$email]))
            continue;
        $seen[$email] = true;
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $out['valid'][] = $email;
        } else {
            $out['invalid'][] = $email;
        }
    }
    return $out;
}

function enqueueEmailJob($to, $subject, $bodyHtml, $bodyText = '', array $meta = [])
{
    global $mysqli;
    if (!isset($mysqli) || !$mysqli)
        return false;
    if (!ensureEmailQueueTable())
        return false;

    $to = strtolower(trim((string) $to));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL))
        return false;

    $eid = isset($meta['empresa_id']) && is_numeric($meta['empresa_id'])
        ? (int) $meta['empresa_id']
        : (function_exists('empresaId') ? (int) empresaId() : (int) ($_SESSION['empresa_id'] ?? 1));
    if ($eid <= 0)
        $eid = 1;

    $contextType = isset($meta['context_type']) ? trim((string) $meta['context_type']) : null;
    if ($contextType === '')
        $contextType = null;
    $contextId = isset($meta['context_id']) && is_numeric($meta['context_id']) ? (int) $meta['context_id'] : null;
    $maxAttempts = isset($meta['max_attempts']) && is_numeric($meta['max_attempts']) ? (int) $meta['max_attempts'] : 5;
    if ($maxAttempts < 1)
        $maxAttempts = 1;
    if ($maxAttempts > 15)
        $maxAttempts = 15;

    $subject = trim((string) $subject);
    if ($subject === '')
        $subject = '(Sin asunto)';
    if (mb_strlen($subject) > 255) {
        $subject = mb_substr($subject, 0, 252) . '...';
    }



    // Serializar adjuntos si existen (PDF bytes como base64)
    $attachmentsJson = null;
    if (isset($meta['attachments']) && is_array($meta['attachments']) && !empty($meta['attachments'])) {
        $serializable = [];
        foreach ($meta['attachments'] as $att) {
            if (!is_array($att))
                continue;
            $item = [
                'filename' => (string) ($att['filename'] ?? 'adjunto'),
                'contentType' => (string) ($att['contentType'] ?? 'application/octet-stream'),
            ];
            if (isset($att['content']) && $att['content'] !== '' && $att['content'] !== null) {
                // Codificar bytes binarios como base64 para almacenar en JSON
                $item['content_b64'] = base64_encode($att['content']);
            }
            $serializable[] = $item;
        }
        if (!empty($serializable)) {
            $attachmentsJson = json_encode($serializable, JSON_UNESCAPED_UNICODE);
        }
    }

    $hasAttCol = dbColumnExists('email_queue', 'attachments_json');
    if ($hasAttCol) {
        $stmt = $mysqli->prepare(
            "INSERT INTO email_queue (empresa_id, recipient_email, subject, body_html, body_text, attachments_json, status, attempts, max_attempts, next_attempt_at, context_type, context_id, created_at, updated_at)\n"
            . "VALUES (?, ?, ?, ?, ?, ?, 'pending', 0, ?, NOW(), ?, ?, NOW(), NOW())"
        );
        if (!$stmt)
            return false;
        $htmlBody = (string) $bodyHtml;
        $textBody = (string) $bodyText;
        $stmt->bind_param('isssssisi', $eid, $to, $subject, $htmlBody, $textBody, $attachmentsJson, $maxAttempts, $contextType, $contextId);
    } else {
        $stmt = $mysqli->prepare(
            "INSERT INTO email_queue (empresa_id, recipient_email, subject, body_html, body_text, status, attempts, max_attempts, next_attempt_at, context_type, context_id, created_at, updated_at)\n"
            . "VALUES (?, ?, ?, ?, ?, 'pending', 0, ?, NOW(), ?, ?, NOW(), NOW())"
        );
        if (!$stmt)
            return false;
        $htmlBody = (string) $bodyHtml;
        $textBody = (string) $bodyText;
        $stmt->bind_param('issssisi', $eid, $to, $subject, $htmlBody, $textBody, $maxAttempts, $contextType, $contextId);
    }
    return (bool) $stmt->execute();
}

function addEmailLog($status, $errorMessage = '', array $meta = [])
{
    global $mysqli;
    if (!isset($mysqli) || !$mysqli)
        return false;
    if (!ensureEmailLogsTable())
        return false;

    $status = trim((string) $status);
    if ($status === '')
        $status = 'unknown';
    $error = trim((string) $errorMessage);
    if ($error === '')
        $error = null;

    $eid = isset($meta['empresa_id']) && is_numeric($meta['empresa_id'])
        ? (int) $meta['empresa_id']
        : (function_exists('empresaId') ? (int) empresaId() : (int) ($_SESSION['empresa_id'] ?? 1));
    if ($eid <= 0)
        $eid = 1;

    $queueId = isset($meta['queue_id']) && is_numeric($meta['queue_id']) ? (int) $meta['queue_id'] : null;
    $recipient = isset($meta['recipient_email']) ? trim((string) $meta['recipient_email']) : null;
    if ($recipient === '')
        $recipient = null;

    $stmt = $mysqli->prepare('INSERT INTO email_logs (empresa_id, queue_id, recipient_email, status, error_message, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    if (!$stmt)
        return false;
    $stmt->bind_param('iisss', $eid, $queueId, $recipient, $status, $error);
    return (bool) $stmt->execute();
}

function triggerEmailQueueWorkerAsync($limit = 30)
{
    $limit = (int) $limit;
    if ($limit < 1)
        $limit = 1;
    if ($limit > 100)
        $limit = 100;

    $token = trim((string) getAppSetting('mail.queue_worker_token', ''));
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
        $basePath = (string) parse_url($appUrl, PHP_URL_PATH);
        $baseDir = rtrim($basePath, '/');
    } else {
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/upload/open.php');
        $baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $baseDir = preg_replace('#/(upload|agente)(/.*)?$#i', '', $baseDir);
    }
    $workerPath = $baseDir . '/upload/process_mail_queue.php';
    $eid = function_exists('empresaId') ? (int) empresaId() : (int) ($_SESSION['empresa_id'] ?? 1);
    if ($eid <= 0)
        $eid = 1;
    $qs = http_build_query(['token' => $token, 'limit' => $limit, 'eid' => $eid, 'async' => 1]);
    $path = $workerPath . '?' . $qs;

    $appUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
    if ($appUrl === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $appUrl = $scheme . '://' . $host . $baseDir;
    }

    $fullUrl = $appUrl . '/upload/process_mail_queue.php?' . $qs;

    if (function_exists('curl_init')) {
        $ch = curl_init($fullUrl);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1500); // 1.5s timeout para permitir Handshake SSL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) SistemaTickets/1.0');
        @curl_exec($ch);
        // curl_close() está obsoleto en PHP 8.0+ ya que $ch es un objeto que se destruye automáticamente
        unset($ch);
        return true;
    }

    // Fallback si cURL no existe (muy raro)
    $host = (string) parse_url($fullUrl, PHP_URL_HOST);
    $port = parse_url($fullUrl, PHP_URL_PORT);
    $scheme = parse_url($fullUrl, PHP_URL_SCHEME);
    if (!$port) {
        $port = ($scheme === 'https') ? 443 : 80;
    }
    $path = parse_url($fullUrl, PHP_URL_PATH) . '?' . parse_url($fullUrl, PHP_URL_QUERY);

    $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $fp = @stream_socket_client(
        ($scheme === 'https' ? 'ssl://' : 'tcp://') . $host . ':' . $port,
        $errno,
        $errstr,
        0.5,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if ($fp) {
        $out = "GET " . $path . " HTTP/1.1\r\n";
        $out .= "Host: " . $host . "\r\n";
        $out .= "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) SistemaTickets/1.0\r\n";
        $out .= "Connection: Close\r\n\r\n";
        @fwrite($fp, $out);
        stream_set_blocking($fp, true);
        stream_set_timeout($fp, 0, 50000);
        @fread($fp, 128);
        @fclose($fp);
    }

}

function ensureRolePermissionsTable()
{
    global $mysqli;
    if (!isset($mysqli) || !$mysqli)
        return false;
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
    $ok = (bool) $mysqli->query($sql);

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

function getCurrentStaffRoleName()
{
    global $mysqli;
    static $cached = null;
    if ($cached !== null)
        return $cached;
    $sid = (int) ($_SESSION['staff_id'] ?? 0);
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
    $cached = trim((string) ($row['role'] ?? ''));
    return $cached;
}

function roleHasPermission($permKey)
{
    global $mysqli;
    $permKey = trim((string) $permKey);
    if ($permKey === '')
        return false;

    $role = getCurrentStaffRoleName();
    if ($role === '')
        return false;

    // Protected fallback: admin and administrator roles always have access to admin.access to prevent accidental lockout
    // This is a safety mechanism to ensure the admin panel remains accessible
    if (($role === 'admin' || $role === 'administrator') && $permKey === 'admin.access') {
        return true;
    }

    // Centralized admin permission override: admin.access grants access to all admin-related features
    // Admin-related permissions include: admin.*, user.*, task.*, org.*, email.*, helptopic.*, banlist.*, department.*, role.*, staff.*, notification.*, log.*, billing.*, sequence.*, setting.*
    // Note: ticket.*, agent.*, and stats.* permissions are NOT auto-granted to allow granular control over these features
    if (roleHasPermissionDirect('admin.access')) {
        $adminPrefixes = ['admin.', 'user.', 'task.', 'org.', 'email.', 'helptopic.', 'banlist.', 'department.', 'role.', 'staff.', 'notification.', 'log.', 'billing.', 'sequence.', 'setting.'];
        foreach ($adminPrefixes as $prefix) {
            if (strpos($permKey, $prefix) === 0) {
                return true;
            }
        }
    }

    if (!isset($mysqli) || !$mysqli)
        return false;
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
    if (!$stmt)
        return false;

    if ($hasEmpresa) {
        $stmt->bind_param('iss', $eid, $role, $permKey);
    } else {
        $stmt->bind_param('ss', $role, $permKey);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (bool) $row;
}

function roleHasPermissionDirect($permKey)
{
    global $mysqli;
    $permKey = trim((string) $permKey);
    if ($permKey === '')
        return false;

    $role = getCurrentStaffRoleName();
    if ($role === '')
        return false;

    if (!isset($mysqli) || !$mysqli)
        return false;
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
    if (!$stmt)
        return false;

    if ($hasEmpresa) {
        $stmt->bind_param('iss', $eid, $role, $permKey);
    } else {
        $stmt->bind_param('ss', $role, $permKey);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (bool) $row;
}

function roleHasAnyPermissionPrefix($prefix)
{
    global $mysqli;
    $prefix = (string) $prefix;
    if ($prefix === '')
        return false;

    $role = getCurrentStaffRoleName();
    if ($role === '')
        return false;

    if (!isset($mysqli) || !$mysqli)
        return false;
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
    if (!$stmt)
        return false;

    if ($hasEmpresa) {
        $stmt->bind_param('iss', $eid, $role, $like);
    } else {
        $stmt->bind_param('ss', $role, $like);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (bool) $row;
}

function requireRolePermission($permKey, $redirectUrl = null)
{
    $ok = roleHasPermission($permKey);
    if ($ok)
        return true;

    $_SESSION['flash_error'] = 'No tienes permiso para hacer esta acción.';
    addLog('permission_denied', (string) $permKey, null, null, 'staff', (int) ($_SESSION['staff_id'] ?? 0));

    if ($redirectUrl) {
        header('Location: ' . $redirectUrl);
        exit;
    }

    $fallback = toAppAbsoluteUrl('upload/scp/index.php');
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref !== '') {
        $refHost = (string) parse_url($ref, PHP_URL_HOST);
        $curHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($refHost === '' || $refHost === $curHost) {
            $refPath = (string) parse_url($ref, PHP_URL_PATH);
            if ($refPath !== '' && strpos($refPath, '/upload/scp/') !== false) {
                $fallback = $ref;
            }
        }
    }

    http_response_code(403);
    header('Location: ' . $fallback);
    exit;
}

function getPostMaxSize()
{
    $val = trim(ini_get('post_max_size'));
    if ($val === '')
        return 8 * 1024 * 1024; // default
    $last = strtolower($val[strlen($val) - 1]);
    $res = (int) $val;
    switch ($last) {
        case 'g':
            $res *= 1024;
        case 'm':
            $res *= 1024;
        case 'k':
            $res *= 1024;
    }
    return $res;
}

/**
 * Genera el HTML para una paginación moderna estandarizada.
 * @param int $page Página actual
 * @param int $totalPages Total de páginas
 * @param string $urlParams Parámetros GET adicionales para los enlaces (ej: '&q=busqueda')
 * @param string $pageParamName Nombre del parámetro de la página en la URL (ej: 'page' o 'p')
 * @return string HTML de la paginación
 */
function renderModernPagination($page, $totalPages, $urlParams = '', $pageParamName = 'page')
{
    if ($totalPages <= 1)
        return '';
    $html = '<div class="pagination-wrap mt-4 mb-2 d-flex justify-content-center">';
    $html .= '<nav aria-label="Page navigation">';
    $html .= '<ul class="pagination pagination-sm justify-content-center mb-0 gap-1 modern-pagination">';

    // Anterior
    $html .= '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '">';
    if ($page > 1) {
        $html .= '<a class="page-link border-0 rounded-3 shadow-sm" href="?' . $pageParamName . '=' . ($page - 1) . $urlParams . '" title="Anterior"><i class="bi bi-chevron-left"></i></a>';
    } else {
        $html .= '<span class="page-link border-0 rounded-3 shadow-sm"><i class="bi bi-chevron-left"></i></span>';
    }
    $html .= '</li>';

    // Números
    $range = 2;
    $start = max(1, $page - $range);
    $end = min($totalPages, $page + $range);

    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link border-0 rounded-3 fw-bold shadow-sm" href="?' . $pageParamName . '=1' . $urlParams . '">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link border-0 bg-transparent text-muted" style="pointer-events: none;">&hellip;</span></li>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $activeClass = ($i == $page) ? 'active' : '';
        $html .= '<li class="page-item ' . $activeClass . '">';
        if ($i == $page) {
            $html .= '<span class="page-link border-0 rounded-3 fw-bold shadow-sm">' . $i . '</span>';
        } else {
            $html .= '<a class="page-link border-0 rounded-3 fw-bold shadow-sm" href="?' . $pageParamName . '=' . $i . $urlParams . '">' . $i . '</a>';
        }
        $html .= '</li>';
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link border-0 bg-transparent text-muted" style="pointer-events: none;">&hellip;</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link border-0 rounded-3 fw-bold shadow-sm" href="?' . $pageParamName . '=' . $totalPages . $urlParams . '">' . $totalPages . '</a></li>';
    }

    // Siguiente
    $html .= '<li class="page-item ' . ($page >= $totalPages ? 'disabled' : '') . '">';
    if ($page < $totalPages) {
        $html .= '<a class="page-link border-0 rounded-3 shadow-sm" href="?' . $pageParamName . '=' . ($page + 1) . $urlParams . '" title="Siguiente"><i class="bi bi-chevron-right"></i></a>';
    } else {
        $html .= '<span class="page-link border-0 rounded-3 shadow-sm"><i class="bi bi-chevron-right"></i></span>';
    }
    $html .= '</li>';

    $html .= '</ul>';
    $html .= '</nav>';
    $html .= '</div>';

    return $html;
}

function getUploadMaxSize()
{
    $val = trim(ini_get('upload_max_filesize'));
    if ($val === '')
        return 2 * 1024 * 1024; // default
    $last = strtolower($val[strlen($val) - 1]);
    $res = (int) $val;
    switch ($last) {
        case 'g':
            $res *= 1024;
        case 'm':
            $res *= 1024;
        case 'k':
            $res *= 1024;
    }
    return $res;
}

/**
 * Notifica a los administradores configurados cuando un cliente aprueba un ticket
 */
function notifyApprovalToAdminRecipients($tid, $statusName)
{
    global $mysqli;
    $eid = empresaId();
    $tid = (int) $tid;

    // Obtener info básica del ticket
    $stmtT = $mysqli->prepare("SELECT ticket_number, subject FROM tickets WHERE id = ? AND empresa_id = ?");
    if (!$stmtT)
        return;
    $stmtT->bind_param('ii', $tid, $eid);
    $stmtT->execute();
    $tRes = $stmtT->get_result();
    $tRow = $tRes ? $tRes->fetch_assoc() : null;
    if (!$tRow)
        return;

    $tNo = (string) ($tRow['ticket_number'] ?? ('#' . $tid));
    $tSub = (string) ($tRow['subject'] ?? '');

    $message = "Ticket #$tNo fue $statusName por el cliente.";
    $type = 'ticket_assigned'; // Campana

    // Obtener destinatarios configurados (ID y Email)
    $recipientsIds = [];
    $recipientsEmails = [];

    // Check if staff has empresa_id column
    $staffHasEmpresa = false;
    try {
        $chk = $mysqli->query("SHOW COLUMNS FROM staff LIKE 'empresa_id'");
        $staffHasEmpresa = ($chk && $chk->num_rows > 0);
    } catch (Throwable $e) {
    }

    $sqlAdmin = "SELECT s.id, s.email FROM notification_recipients nr INNER JOIN staff s ON s.id = nr.staff_id WHERE nr.empresa_id = ? AND s.is_active = 1";
    if ($staffHasEmpresa) {
        $sqlAdmin .= " AND s.empresa_id = ?";
    }

    $stmtR = $mysqli->prepare($sqlAdmin);
    if ($stmtR) {
        if ($staffHasEmpresa) {
            $stmtR->bind_param('ii', $eid, $eid);
        } else {
            $stmtR->bind_param('i', $eid);
        }
        if ($stmtR->execute()) {
            $resR = $stmtR->get_result();
            while ($r = $resR->fetch_assoc()) {
                $sid = (int) ($r['id'] ?? 0);
                if ($sid > 0)
                    $recipientsIds[] = $sid;

                $em = strtolower(trim((string) ($r['email'] ?? '')));
                if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) {
                    $recipientsEmails[$em] = true;
                }
            }
        }
    }

    // 1. Notificación de campana
    if (!empty($recipientsIds)) {
        $recipientsIds = array_unique($recipientsIds);
        $stmtN = $mysqli->prepare("INSERT INTO notifications (empresa_id, staff_id, message, type, related_id, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
        if ($stmtN) {
            foreach ($recipientsIds as $sid) {
                $stmtN->bind_param('iissi', $eid, $sid, $message, $type, $tid);
                $stmtN->execute();
            }
        }
    }
}

function ensureOrgBossReportsTable()
{
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) {
        return false;
    }
    $sql = "CREATE TABLE IF NOT EXISTS org_boss_reports (\n"
        . "  id BIGINT PRIMARY KEY AUTO_INCREMENT,\n"
        . "  empresa_id INT NOT NULL DEFAULT 1,\n"
        . "  staff_id INT NOT NULL,\n"
        . "  organization_id INT NOT NULL,\n"
        . "  target_user_id INT NULL,\n"
        . "  subject VARCHAR(255) NOT NULL,\n"
        . "  body_html MEDIUMTEXT NULL,\n"
        . "  body_text MEDIUMTEXT NULL,\n"
        . "  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        . "  KEY idx_obr_empresa (empresa_id),\n"
        . "  KEY idx_obr_org (organization_id),\n"
        . "  KEY idx_obr_created (created_at)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if (!$mysqli->query($sql)) {
        return false;
    }
    return true;
}

function ensureOrgBossReportAttachmentsTable()
{
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) {
        return false;
    }
    ensureOrgBossReportsTable();
    $sql = "CREATE TABLE IF NOT EXISTS org_boss_report_attachments (\n"
        . "  id BIGINT PRIMARY KEY AUTO_INCREMENT,\n"
        . "  report_id BIGINT NOT NULL,\n"
        . "  empresa_id INT NOT NULL DEFAULT 1,\n"
        . "  original_filename VARCHAR(255) NOT NULL,\n"
        . "  filename VARCHAR(255) NOT NULL,\n"
        . "  mimetype VARCHAR(128) NULL,\n"
        . "  size INT NOT NULL DEFAULT 0,\n"
        . "  path VARCHAR(512) NOT NULL,\n"
        . "  hash VARCHAR(64) NULL,\n"
        . "  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  KEY idx_obra_report (report_id),\n"
        . "  KEY idx_obra_empresa (empresa_id)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    return (bool) $mysqli->query($sql);
}

function ensureOrgBossReportReadsTable()
{
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) {
        return false;
    }
    ensureOrgBossReportsTable();
    $sql = "CREATE TABLE IF NOT EXISTS org_boss_report_reads (\n"
        . "  id BIGINT PRIMARY KEY AUTO_INCREMENT,\n"
        . "  report_id BIGINT NOT NULL,\n"
        . "  user_id INT NOT NULL,\n"
        . "  read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  UNIQUE KEY uq_obr_read (report_id, user_id),\n"
        . "  KEY idx_obrr_user (user_id)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    return (bool) $mysqli->query($sql);
}

function orgBossReportsStorageDir()
{
    $base = defined('ATTACHMENTS_DIR') ? dirname((string) ATTACHMENTS_DIR) : (__DIR__ . '/../upload/uploads');
    $dir = rtrim(str_replace('\\', '/', $base), '/') . '/org_boss_reports';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * @return array<int, array{id:int,email:string,firstname:string,lastname:string}>
 */
function fetchOrganizationBosses($mysqli, int $empresaId, int $organizationId): array
{
    if ($empresaId <= 0 || $organizationId <= 0 || !isset($mysqli) || !$mysqli) {
        return [];
    }
    if (!ensureUserOrgTicketsViewColumn($mysqli) || !organizationMembershipEnabled($mysqli)) {
        return [];
    }
    $stmt = $mysqli->prepare(
        "SELECT DISTINCT u.id, u.email, u.firstname, u.lastname\n"
        . "FROM user_organizations uo\n"
        . "JOIN users u ON u.id = uo.user_id AND u.empresa_id = uo.empresa_id\n"
        . "WHERE uo.empresa_id = ? AND uo.organization_id = ? AND u.org_tickets_view = 1 AND u.status = 'active'\n"
        . "ORDER BY u.firstname, u.lastname"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ii', $empresaId, $organizationId);
    if (!$stmt->execute()) {
        return [];
    }
    $rows = [];
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    return $rows;
}

function bossCanAccessOrgReport($mysqli, int $userId, int $empresaId, array $reportRow): bool
{
    if ($userId <= 0 || $empresaId <= 0 || empty($reportRow)) {
        return false;
    }
    if (!userOrgTicketsViewEnabled($mysqli, $userId, $empresaId)) {
        return false;
    }
    $orgId = (int) ($reportRow['organization_id'] ?? 0);
    if ($orgId <= 0) {
        return false;
    }
    $orgs = getPortalOrganizationsForUser($mysqli, $userId, $empresaId);
    foreach ($orgs as $o) {
        if ((int) ($o['organization_id'] ?? 0) === $orgId) {
            return true;
        }
    }
    return false;
}

function countUnreadOrgBossReportsForUser($mysqli, int $userId, int $empresaId): int
{
    if ($userId <= 0 || !userOrgTicketsViewEnabled($mysqli, $userId, $empresaId)) {
        return 0;
    }
    if (!ensureOrgBossReportReadsTable()) {
        return 0;
    }
    $orgs = getPortalOrganizationsForUser($mysqli, $userId, $empresaId);
    $orgIds = [];
    foreach ($orgs as $o) {
        $oid = (int) ($o['organization_id'] ?? 0);
        if ($oid > 0) {
            $orgIds[] = $oid;
        }
    }
    if (empty($orgIds)) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($orgIds), '?'));
    $types = 'ii' . str_repeat('i', count($orgIds));
    $sql = "SELECT COUNT(*) c FROM org_boss_reports r\n"
        . "LEFT JOIN org_boss_report_reads rd ON rd.report_id = r.id AND rd.user_id = ?\n"
        . "WHERE r.empresa_id = ? AND r.organization_id IN ($placeholders) AND rd.id IS NULL";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $params = [$userId, $empresaId];
    foreach ($orgIds as $oid) {
        $params[] = $oid;
    }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        return 0;
    }
    return (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
}

/**
 * @return array<int, array<string, mixed>>
 */
function fetchOrgBossReportsForBoss($mysqli, int $userId, int $empresaId, int $limit = 20, int $offset = 0): array
{
    if ($userId <= 0 || !userOrgTicketsViewEnabled($mysqli, $userId, $empresaId)) {
        return [];
    }
    ensureOrgBossReportReadsTable();
    $orgs = getPortalOrganizationsForUser($mysqli, $userId, $empresaId);
    $orgIds = [];
    foreach ($orgs as $o) {
        $oid = (int) ($o['organization_id'] ?? 0);
        if ($oid > 0) {
            $orgIds[] = $oid;
        }
    }
    if (empty($orgIds)) {
        return [];
    }
    $limit = max(1, $limit);
    $offset = max(0, $offset);
    $placeholders = implode(',', array_fill(0, count($orgIds), '?'));
    $types = 'ii' . str_repeat('i', count($orgIds)) . 'ii';
    $sql = "SELECT r.*, o.name AS org_name,\n"
        . "s.firstname AS staff_first, s.lastname AS staff_last,\n"
        . "tu.firstname AS target_first, tu.lastname AS target_last, tu.email AS target_email,\n"
        . "(CASE WHEN rd.id IS NULL THEN 0 ELSE 1 END) AS is_read\n"
        . "FROM org_boss_reports r\n"
        . "JOIN organizations o ON o.id = r.organization_id AND o.empresa_id = r.empresa_id\n"
        . "LEFT JOIN staff s ON s.id = r.staff_id\n"
        . "LEFT JOIN users tu ON tu.id = r.target_user_id\n"
        . "LEFT JOIN org_boss_report_reads rd ON rd.report_id = r.id AND rd.user_id = ?\n"
        . "WHERE r.empresa_id = ? AND r.organization_id IN ($placeholders)\n"
        . "ORDER BY r.created_at DESC\n"
        . "LIMIT ? OFFSET ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $params = [$userId, $empresaId];
    foreach ($orgIds as $oid) {
        $params[] = $oid;
    }
    $params[] = $limit;
    $params[] = $offset;
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        return [];
    }
    $rows = [];
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    return $rows;
}

function markOrgBossReportRead($mysqli, int $reportId, int $userId): void
{
    if ($reportId <= 0 || $userId <= 0 || !ensureOrgBossReportReadsTable()) {
        return;
    }
    $stmt = $mysqli->prepare(
        'INSERT INTO org_boss_report_reads (report_id, user_id, read_at) VALUES (?, ?, NOW())'
        . ' ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)'
    );
    if ($stmt) {
        $stmt->bind_param('ii', $reportId, $userId);
        $stmt->execute();
    }
}

/**
 * @return array{ok:bool,report_id?:int,error?:string}
 */
function saveOrgBossReportWithAttachments($mysqli, int $empresaId, int $staffId, int $organizationId, ?int $targetUserId, string $subject, string $bodyHtml, array $uploadedFiles = []): array
{
    if ($empresaId <= 0 || $staffId <= 0 || $organizationId <= 0) {
        return ['ok' => false, 'error' => 'Datos inválidos'];
    }
    if (!ensureOrgBossReportAttachmentsTable()) {
        return ['ok' => false, 'error' => 'No se pudo preparar la tabla de informes'];
    }
    $subject = trim($subject);
    if ($subject === '') {
        return ['ok' => false, 'error' => 'El asunto es obligatorio'];
    }
    if (mb_strlen($subject) > 255) {
        $subject = mb_substr($subject, 0, 252) . '...';
    }
    $bodyHtml = trim($bodyHtml);
    if ($bodyHtml === '') {
        return ['ok' => false, 'error' => 'El mensaje es obligatorio'];
    }
    $bodyText = trim(strip_tags($bodyHtml));

    $stmtOrg = $mysqli->prepare('SELECT id, name FROM organizations WHERE id = ? AND empresa_id = ? LIMIT 1');
    if (!$stmtOrg) {
        return ['ok' => false, 'error' => 'Organización no encontrada'];
    }
    $stmtOrg->bind_param('ii', $organizationId, $empresaId);
    $stmtOrg->execute();
    $orgRow = $stmtOrg->get_result()->fetch_assoc();
    if (!$orgRow) {
        return ['ok' => false, 'error' => 'Organización no válida'];
    }
    $orgName = (string) ($orgRow['name'] ?? '');

    if ($targetUserId !== null && $targetUserId > 0) {
        $users = fetchOrganizationUsers($mysqli, $empresaId, $organizationId, $orgName, 500, 0);
        $valid = false;
        foreach ($users as $u) {
            if ((int) ($u['id'] ?? 0) === $targetUserId) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            return ['ok' => false, 'error' => 'El usuario seleccionado no pertenece a la organización'];
        }
    } else {
        $targetUserId = null;
    }

    if ($targetUserId !== null && $targetUserId > 0) {
        $stmt = $mysqli->prepare(
            'INSERT INTO org_boss_reports (empresa_id, staff_id, organization_id, target_user_id, subject, body_html, body_text, created_at, updated_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Error al guardar el informe'];
        }
        $stmt->bind_param('iiiisss', $empresaId, $staffId, $organizationId, $targetUserId, $subject, $bodyHtml, $bodyText);
    } else {
        $stmt = $mysqli->prepare(
            'INSERT INTO org_boss_reports (empresa_id, staff_id, organization_id, target_user_id, subject, body_html, body_text, created_at, updated_at)'
            . ' VALUES (?, ?, ?, NULL, ?, ?, ?, NOW(), NOW())'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Error al guardar el informe'];
        }
        $stmt->bind_param('iiisss', $empresaId, $staffId, $organizationId, $subject, $bodyHtml, $bodyText);
    }
    if (!$stmt->execute()) {
        return ['ok' => false, 'error' => 'Error al guardar el informe'];
    }
    $reportId = (int) $mysqli->insert_id;
    if ($reportId <= 0) {
        return ['ok' => false, 'error' => 'Error al crear el informe'];
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'txt', 'mp4', 'webm', 'mov', 'mkv'];
    $maxSize = 25 * 1024 * 1024;
    $maxFiles = 10;
    $storageDir = orgBossReportsStorageDir();
    $saved = 0;

    if (isset($uploadedFiles['name']) && is_array($uploadedFiles['name'])) {
        $n = count($uploadedFiles['name']);
        for ($i = 0; $i < $n && $saved < $maxFiles; $i++) {
            $err = $uploadedFiles['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($err !== UPLOAD_ERR_OK) {
                continue;
            }
            $tmp = (string) ($uploadedFiles['tmp_name'][$i] ?? '');
            if ($tmp === '' || !is_readable($tmp)) {
                continue;
            }
            $size = (int) ($uploadedFiles['size'][$i] ?? 0);
            if ($size <= 0 || $size > $maxSize) {
                continue;
            }
            $orig = (string) ($uploadedFiles['name'][$i] ?? 'archivo');
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if ($ext === '' || !in_array($ext, $allowedExt, true)) {
                continue;
            }
            $mime = (string) ($uploadedFiles['type'][$i] ?? 'application/octet-stream');
            if (class_exists('finfo')) {
                $finfoObj = new finfo(FILEINFO_MIME_TYPE);
                $det = @$finfoObj->file($tmp);
                if (is_string($det) && $det !== '') {
                    $mime = $det;
                }
            }
            $safeName = 'obr_' . $reportId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dest = $storageDir . '/' . $safeName;
            if (!@move_uploaded_file($tmp, $dest)) {
                continue;
            }
            $relPath = 'uploads/org_boss_reports/' . $safeName;
            $hash = @hash_file('sha256', $dest) ?: null;
            $stmtA = $mysqli->prepare(
                'INSERT INTO org_boss_report_attachments (report_id, empresa_id, original_filename, filename, mimetype, size, path, hash, created_at)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            if ($stmtA) {
                $stmtA->bind_param('iisssiss', $reportId, $empresaId, $orig, $safeName, $mime, $size, $relPath, $hash);
                if ($stmtA->execute()) {
                    $saved++;
                }
            }
        }
    }

    $bosses = fetchOrganizationBosses($mysqli, $empresaId, $organizationId);
    $portalUrl = rtrim((string) (defined('APP_URL') ? APP_URL : ''), '/') . '/upload/informe-jefe.php?id=' . $reportId;
    $staffName = '';
    if (function_exists('getCurrentUser')) {
        $st = getCurrentUser();
        if (is_array($st)) {
            $staffName = trim((string) ($st['firstname'] ?? '') . ' ' . (string) ($st['lastname'] ?? ''));
        }
    }
    if ($staffName === '') {
        $staffName = 'Soporte técnico';
    }
    $subjMail = '[Informe] ' . $subject;
    $bodyHtmlMail = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;">'
        . '<h2 style="color:#1e3a5f;">Nuevo informe de ' . htmlspecialchars($orgName) . '</h2>'
        . '<p>El equipo de soporte (<strong>' . htmlspecialchars($staffName) . '</strong>) envió un informe para su revisión.</p>'
        . '<p><strong>Asunto:</strong> ' . htmlspecialchars($subject) . '</p>'
        . '<p><a href="' . htmlspecialchars($portalUrl) . '" style="display:inline-block;background:#2563eb;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none;">Ver informe</a></p>'
        . '</div>';
    $bodyTextMail = "Nuevo informe de $orgName\nAsunto: $subject\nVer: $portalUrl";

    foreach ($bosses as $boss) {
        $email = trim((string) ($boss['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        if (function_exists('enqueueEmailJob')) {
            enqueueEmailJob($email, $subjMail, $bodyHtmlMail, $bodyTextMail, [
                'empresa_id' => $empresaId,
                'context_type' => 'org_boss_report',
                'context_id' => $reportId,
            ]);
        } elseif (class_exists('Mailer')) {
            Mailer::send($email, $subjMail, $bodyHtmlMail, $bodyTextMail);
        }
    }
    if (function_exists('triggerEmailQueueWorkerAsync')) {
        triggerEmailQueueWorkerAsync();
    }

    return ['ok' => true, 'report_id' => $reportId];
}

/**
 * Notifica a los encargados de la organización cuando se crea un nuevo ticket.
 */
function notifyOrgManagersOfNewTicket($mysqli, int $ticketId, int $empresaId): void
{
    if ($ticketId <= 0 || $empresaId <= 0 || !isset($mysqli) || !$mysqli) {
        return;
    }
    
    // Verificar si existe la tabla de organizaciones
    $hasOrgs = dbTableExists('organizations');
    if (!$hasOrgs) {
        return;
    }
    $hasUserOrgs = dbTableExists('user_organizations');
    
    // Obtener detalles del ticket
    $stmtT = $mysqli->prepare(
        "SELECT t.ticket_number, t.subject, t.user_id, t.dept_id, d.name AS dept_name, 
                u.firstname AS user_firstname, u.lastname AS user_lastname, u.email AS user_email, u.company AS user_company
         FROM tickets t
         LEFT JOIN users u ON u.id = t.user_id AND u.empresa_id = t.empresa_id
         LEFT JOIN departments d ON d.id = t.dept_id AND d.empresa_id = t.empresa_id
         WHERE t.id = ? AND t.empresa_id = ? LIMIT 1"
    );
    if (!$stmtT) {
        return;
    }
    $stmtT->bind_param('ii', $ticketId, $empresaId);
    if (!$stmtT->execute()) {
        return;
    }
    $ticket = $stmtT->get_result()->fetch_assoc();
    if (!$ticket) {
        return;
    }
    
    $ticketNumber = $ticket['ticket_number'];
    $subject = $ticket['subject'];
    $userId = (int)$ticket['user_id'];
    $deptName = $ticket['dept_name'] ?? 'Soporte';
    $userFirstname = $ticket['user_firstname'] ?? '';
    $userLastname = $ticket['user_lastname'] ?? '';
    $userEmail = $ticket['user_email'] ?? '';
    $userFullname = trim($userFirstname . ' ' . $userLastname);
    if ($userFullname === '') {
        $userFullname = 'Cliente';
    }
    
    if ($userId <= 0) {
        return;
    }
    
    // Obtener descripción del ticket (primer mensaje del hilo)
    $bodyHtmlText = '';
    $bodyEmailText = '';
    $stmtTe = $mysqli->prepare(
        "SELECT te.body
         FROM thread_entries te
         JOIN threads th ON th.id = te.thread_id
         WHERE th.ticket_id = ? AND th.empresa_id = ?
         ORDER BY te.id ASC
         LIMIT 1"
    );
    if ($stmtTe) {
        $stmtTe->bind_param('ii', $ticketId, $empresaId);
        if ($stmtTe->execute()) {
            $teRow = $stmtTe->get_result()->fetch_assoc();
            if ($teRow && !empty($teRow['body'])) {
                $bodyHtmlText = $teRow['body'];
                $bodyEmailText = trim(str_replace("\xC2\xA0", ' ', html_entity_decode(strip_tags((string)$bodyHtmlText), ENT_QUOTES, 'UTF-8')));
            }
        }
    }
    
    // Obtener encargados de la organización (org_tickets_view = 1) a los que pertenece el usuario
    // Excluir al propio creador del ticket ($userId) para evitar auto-notificaciones.
    $managers = [];
    
    if ($hasUserOrgs) {
        $sql = "SELECT DISTINCT 
                    u_manager.id AS manager_id,
                    u_manager.email AS manager_email,
                    u_manager.firstname AS manager_firstname,
                    u_manager.lastname AS manager_lastname,
                    o.name AS organization_name
                FROM organizations o
                INNER JOIN (
                    SELECT uo.organization_id, uo.user_id, uo.empresa_id
                    FROM user_organizations uo
                    UNION
                    SELECT o_fallback.id AS organization_id, u_fallback.id AS user_id, u_fallback.empresa_id
                    FROM users u_fallback
                    INNER JOIN organizations o_fallback ON o_fallback.name = u_fallback.company AND o_fallback.empresa_id = u_fallback.empresa_id
                    WHERE NOT EXISTS (
                        SELECT 1 FROM user_organizations uo_chk
                        WHERE uo_chk.user_id = u_fallback.id AND uo_chk.empresa_id = u_fallback.empresa_id
                    )
                ) target_user_orgs ON target_user_orgs.organization_id = o.id AND target_user_orgs.empresa_id = o.empresa_id
                INNER JOIN (
                    SELECT uo_m.organization_id, u_m.id, u_m.email, u_m.firstname, u_m.lastname, u_m.empresa_id
                    FROM users u_m
                    INNER JOIN user_organizations uo_m ON uo_m.user_id = u_m.id AND uo_m.empresa_id = u_m.empresa_id
                    WHERE u_m.org_tickets_view = 1 AND u_m.status = 'active'
                    UNION
                    SELECT o_m.id AS organization_id, u_m.id, u_m.email, u_m.firstname, u_m.lastname, u_m.empresa_id
                    FROM users u_m
                    INNER JOIN organizations o_m ON o_m.name = u_m.company AND o_m.empresa_id = u_m.empresa_id
                    WHERE u_m.org_tickets_view = 1 AND u_m.status = 'active'
                      AND NOT EXISTS (
                          SELECT 1 FROM user_organizations uo_chk2
                          WHERE uo_chk2.user_id = u_m.id AND uo_chk2.empresa_id = u_m.empresa_id
                      )
                ) u_manager ON u_manager.organization_id = o.id AND u_manager.empresa_id = o.empresa_id
                WHERE target_user_orgs.user_id = ? AND o.empresa_id = ? AND u_manager.id != ?";
        $stmtM = $mysqli->prepare($sql);
        if ($stmtM) {
            $stmtM->bind_param('iii', $userId, $empresaId, $userId);
        }
    } else {
        $sql = "SELECT DISTINCT 
                    u_manager.id AS manager_id,
                    u_manager.email AS manager_email,
                    u_manager.firstname AS manager_firstname,
                    u_manager.lastname AS manager_lastname,
                    o.name AS organization_name
                FROM organizations o
                INNER JOIN users u_target ON u_target.company = o.name AND u_target.empresa_id = o.empresa_id
                INNER JOIN users u_manager ON u_manager.company = o.name AND u_manager.empresa_id = o.empresa_id
                WHERE u_target.id = ? AND o.empresa_id = ? AND u_manager.org_tickets_view = 1 AND u_manager.status = 'active' AND u_manager.id != ?";
        $stmtM = $mysqli->prepare($sql);
        if ($stmtM) {
            $stmtM->bind_param('iii', $userId, $empresaId, $userId);
        }
    }
    
    if ($stmtM && $stmtM->execute()) {
        $res = $stmtM->get_result();
        while ($row = $res->fetch_assoc()) {
            $managers[] = $row;
        }
    }
    
    if (empty($managers)) {
        return;
    }
    
    // Encolar/Enviar correo a cada encargado de la organización
    $viewUrl = rtrim((string)(defined('APP_URL') ? APP_URL : ''), '/') . '/upload/view-ticket.php?from=org&id=' . (int)$ticketId;
    $appName = defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets';
    
    $mailSentCount = 0;
    foreach ($managers as $mgr) {
        $mgrEmail = strtolower(trim((string)($mgr['manager_email'] ?? '')));
        if ($mgrEmail === '' || !filter_var($mgrEmail, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        
        $orgName = $mgr['organization_name'] ?? 'Organización';
        $mgrName = trim(($mgr['manager_firstname'] ?? '') . ' ' . ($mgr['manager_lastname'] ?? ''));
        if ($mgrName === '') {
            $mgrName = 'Encargado';
        }
        
        $emailSubject = '[Nuevo ticket] Org: ' . $orgName . ' - Usuario: ' . $userFullname;
        
        $bodyHtml = '
            <div style="font-family: Segoe UI, sans-serif; max-width: 600px; margin: 0 auto; line-height: 1.6; color: #334155;">
                <h2 style="color: #1e3a5f; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">Nuevo ticket de tu organización</h2>
                <p>Estimado/a <strong>' . htmlspecialchars($mgrName) . '</strong>,</p>
                <p>Se ha creado un nuevo ticket para la organización <strong>' . htmlspecialchars($orgName) . '</strong>, por el usuario <strong>' . htmlspecialchars($userFullname) . '</strong>.</p>
                
                <table style="width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 0.95rem;">
                    <tr><td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; width: 120px;"><strong>Número:</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($ticketNumber) . '</td></tr>
                    <tr><td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; width: 120px;"><strong>Asunto:</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($subject) . '</td></tr>
                    <tr><td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; width: 120px;"><strong>Usuario:</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($userFullname) . '</td></tr>
                    <tr><td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; width: 120px;"><strong>Departamento:</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($deptName) . '</td></tr>
                </table>';
                
        if ($bodyEmailText !== '') {
            $bodyHtml .= '
                <p><strong>Descripción:</strong></p>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px; margin: 12px 0; font-style: italic;">' . nl2br(htmlspecialchars($bodyEmailText)) . '</div>';
        }
        
        $bodyHtml .= '
                <p style="margin-top: 20px;"><a href="' . htmlspecialchars($viewUrl) . '" style="display: inline-block; background: #2563eb; color: white; padding: 10px 18px; text-decoration: none; border-radius: 6px; font-weight: bold;">Ver ticket en el Portal</a></p>
                <p style="color: #64748b; font-size: 12px; border-top: 1px solid #e2e8f0; padding-top: 12px; margin-top: 24px;">' . htmlspecialchars($appName) . '</p>
            </div>';
            
        $bodyText = "Nuevo ticket de tu organización\n\n"
            . "Se ha creado un nuevo ticket para la organización: " . $orgName . "\n"
            . "Usuario: " . $userFullname . "\n"
            . "Número: " . $ticketNumber . "\n"
            . "Asunto: " . $subject . "\n"
            . "Departamento: " . $deptName . "\n\n"
            . ($bodyEmailText !== '' ? "Descripción:\n" . $bodyEmailText . "\n\n" : "")
            . "Ver ticket: " . $viewUrl . "\n\n"
            . $appName;
            
        if (function_exists('enqueueEmailJob')) {
            if (enqueueEmailJob($mgrEmail, $emailSubject, $bodyHtml, $bodyText, ['empresa_id' => $empresaId, 'context_type' => 'org_manager_notify', 'context_id' => $ticketId])) {
                $mailSentCount++;
            }
        } else {
            Mailer::send($mgrEmail, $emailSubject, $bodyHtml, $bodyText);
            $mailSentCount++;
        }
    }
    
    if ($mailSentCount > 0 && function_exists('triggerEmailQueueWorkerAsync')) {
        triggerEmailQueueWorkerAsync(40);
    }
    
    addLog('org_manager_notify_summary', 'Se notificó a ' . $mailSentCount . ' encargados de organización para el ticket ' . $ticketNumber, 'ticket', $ticketId, 'staff', 0);
}