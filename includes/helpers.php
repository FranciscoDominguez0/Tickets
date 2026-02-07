<?php
/**
 * FUNCIONES AUXILIARES
 */

// Proteger página (requiere login)
function requireLogin($type = 'user') {
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
    return (bool)$mysqli->query($sql);
}

function getAppSetting($key, $default = null) {
    global $mysqli;
    if (!isset($mysqli) || !$mysqli) return $default;
    if (!ensureAppSettingsTable()) return $default;
    $key = (string)$key;
    $stmt = $mysqli->prepare('SELECT `value` FROM app_settings WHERE `key` = ? LIMIT 1');
    if (!$stmt) return $default;
    $stmt->bind_param('s', $key);
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

    $stmt = $mysqli->prepare('INSERT INTO logs (action, object_type, object_id, user_type, user_id, details, ip_address, created) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    if (!$stmt) return false;
    $stmt->bind_param('ssissss', $action, $object_type, $object_id, $user_type, $user_id, $details, $ip);
    return $stmt->execute();
}
?>
