<?php
/**
 * Envío de correo para notificaciones del sistema de tickets.
 * Soporta mail() nativo o SMTP (Gmail, Outlook, etc.).
 */

require_once __DIR__ . '/helpers.php';

class Mailer {

    /** @var string Último error si falla el envío */
    public static $lastError = '';

    protected static $defaultAccountCache = [];

    /**
     * Limpia la caché estática de cuentas SMTP.
     * Necesario cuando el worker procesa correos de múltiples empresas
     * para que cada empresa use su propia configuración SMTP.
     */
    public static function resetCache() {
        self::$defaultAccountCache = [];
    }

    protected static function getDefaultEmailAccount($empresaId = null) {
        $eid = 0;
        if ($empresaId !== null) {
            $eid = (int)$empresaId;
        } elseif (function_exists('empresaId')) {
            $eid = (int)empresaId();
        } elseif (isset($_SESSION['empresa_id'])) {
            $eid = (int)$_SESSION['empresa_id'];
        }
        if ($eid <= 0) $eid = 1;

        if (!isset($GLOBALS['mysqli']) || !$GLOBALS['mysqli']) return false;
        $mysqli = $GLOBALS['mysqli'];

        $chk = $mysqli->query("SHOW TABLES LIKE 'email_accounts'");
        if (!$chk || $chk->num_rows < 1) return false;

        $emailAccHasEmpresa = false;
        $colE = $mysqli->query("SHOW COLUMNS FROM email_accounts LIKE 'empresa_id'");
        $emailAccHasEmpresa = ($colE && $colE->num_rows > 0);
        $cacheKey = $emailAccHasEmpresa ? $eid : 0;

        if (array_key_exists($cacheKey, self::$defaultAccountCache)) {
            return self::$defaultAccountCache[$cacheKey];
        }
        self::$defaultAccountCache[$cacheKey] = false;

        if ($emailAccHasEmpresa) {
            $stmtD = $mysqli->prepare("SELECT * FROM email_accounts WHERE empresa_id = ? AND is_default = 1 ORDER BY id ASC LIMIT 1");
            if ($stmtD) {
                $stmtD->bind_param('i', $eid);
                if ($stmtD->execute()) {
                    $row = $stmtD->get_result()->fetch_assoc();
                    if (is_array($row) && !empty($row['email'])) {
                        self::$defaultAccountCache[$cacheKey] = $row;
                        return self::$defaultAccountCache[$cacheKey];
                    }
                }
            }
        } else {
            $res = $mysqli->query("SELECT * FROM email_accounts WHERE is_default = 1 ORDER BY id ASC LIMIT 1");
            if ($res) {
                $row = $res->fetch_assoc();
                if (is_array($row) && !empty($row['email'])) {
                    self::$defaultAccountCache[$cacheKey] = $row;
                    return self::$defaultAccountCache[$cacheKey];
                }
            }
        }

        $mailFrom = trim((string)getAppSetting('mail.from', defined('MAIL_FROM') ? (string)MAIL_FROM : ''));
        if ($mailFrom !== '' && filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
            if ($emailAccHasEmpresa) {
                $stmt = $mysqli->prepare('SELECT * FROM email_accounts WHERE empresa_id = ? AND email = ? ORDER BY id ASC LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('is', $eid, $mailFrom);
                    if ($stmt->execute()) {
                        $row = $stmt->get_result()->fetch_assoc();
                        if (is_array($row) && !empty($row['email'])) {
                            self::$defaultAccountCache[$cacheKey] = $row;
                            return self::$defaultAccountCache[$cacheKey];
                        }
                    }
                }
            } else {
                $stmt = $mysqli->prepare('SELECT * FROM email_accounts WHERE email = ? ORDER BY id ASC LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('s', $mailFrom);
                    if ($stmt->execute()) {
                        $row = $stmt->get_result()->fetch_assoc();
                        if (is_array($row) && !empty($row['email'])) {
                            self::$defaultAccountCache[$cacheKey] = $row;
                            return self::$defaultAccountCache[$cacheKey];
                        }
                    }
                }
            }
        }

        if ($emailAccHasEmpresa) {
            $stmtAny = $mysqli->prepare('SELECT * FROM email_accounts WHERE empresa_id = ? ORDER BY is_default DESC, id ASC LIMIT 1');
            if ($stmtAny) {
                $stmtAny->bind_param('i', $eid);
                if ($stmtAny->execute()) {
                    $row = $stmtAny->get_result()->fetch_assoc();
                    if (is_array($row) && !empty($row['email'])) {
                        self::$defaultAccountCache[$cacheKey] = $row;
                        return self::$defaultAccountCache[$cacheKey];
                    }
                }
            }
        } else {
            $resAny = $mysqli->query('SELECT * FROM email_accounts ORDER BY is_default DESC, id ASC LIMIT 1');
            if ($resAny) {
                $row = $resAny->fetch_assoc();
                if (is_array($row) && !empty($row['email'])) {
                    self::$defaultAccountCache[$cacheKey] = $row;
                    return self::$defaultAccountCache[$cacheKey];
                }
            }
        }

        return self::$defaultAccountCache[$cacheKey];
    }

    protected static function getSystemDefaults() {
        $acc = self::getDefaultEmailAccount();

        $from = '';
        $fromName = '';
        $smtp = [
            'host' => '',
            'port' => 587,
            'secure' => 'tls',
            'user' => '',
            'pass' => '',
        ];

        if (is_array($acc)) {
            $from = trim((string)($acc['email'] ?? ''));
            $fromName = trim((string)($acc['name'] ?? ''));
            $smtp['host'] = trim((string)($acc['smtp_host'] ?? ''));
            $smtp['port'] = ($acc['smtp_port'] ?? '') !== '' ? (int)$acc['smtp_port'] : 587;
            $smtp['secure'] = strtolower(trim((string)($acc['smtp_secure'] ?? 'tls')));
            $smtp['user'] = trim((string)($acc['smtp_user'] ?? ''));
            $smtp['pass'] = (string)($acc['smtp_pass'] ?? '');
        }

        if ($from === '') {
            $from = (string)getAppSetting('mail.from', defined('MAIL_FROM') ? (string)MAIL_FROM : 'noreply@localhost');
        }
        if ($fromName === '') {
            $fromName = (string)getAppSetting('mail.from_name', defined('MAIL_FROM_NAME') ? (string)MAIL_FROM_NAME : 'Sistema');
        }

        if ($smtp['host'] === '') {
            $smtp['host'] = defined('SMTP_HOST') ? (string)SMTP_HOST : '';
            $smtp['port'] = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
            $smtp['secure'] = defined('SMTP_SECURE') ? strtolower((string)SMTP_SECURE) : 'tls';
            $smtp['user'] = defined('SMTP_USER') ? (string)SMTP_USER : '';
            $smtp['pass'] = defined('SMTP_PASS') ? (string)SMTP_PASS : '';
        }

        return [
            'from' => $from,
            'fromName' => $fromName,
            'smtp' => $smtp,
        ];
    }

    /**
     * Envía un correo.
     *
     * @param string $to      Email del destinatario
     * @param string $subject Asunto
     * @param string $bodyHtml Cuerpo en HTML
     * @param string|null $bodyText Cuerpo en texto plano (opcional)
     * @return bool true si se envió correctamente
     */
    public static function send($to, $subject, $bodyHtml, $bodyText = null) {
        self::$lastError = '';
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = 'Email destinatario inválido';
            return false;
        }

        $sys = self::getSystemDefaults();
        $opts = [
            'from' => (string)($sys['from'] ?? (defined('MAIL_FROM') ? MAIL_FROM : 'noreply@localhost')),
            'fromName' => (string)($sys['fromName'] ?? (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Sistema')),
            'smtp' => is_array($sys['smtp'] ?? null) ? $sys['smtp'] : [],
        ];
        return self::sendWithOptions($to, $subject, $bodyHtml, $bodyText, $opts);
    }

    /**
     * Envía un correo usando la configuración SMTP de una empresa específica.
     * Usado por el worker de la cola para no depender de la sesión.
     */
    public static function sendWithEmpresa($to, $subject, $bodyHtml, $bodyText = null, $empresaId = null, array $extraOptions = []) {
        self::$lastError = '';
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = 'Email destinatario inválido';
            return false;
        }

        // Cargar cuenta SMTP específica de esta empresa
        $acc = self::getDefaultEmailAccount($empresaId);
        $sys = self::buildSystemDefaultsFromAccount($acc);
        $opts = [
            'from' => (string)($sys['from'] ?? 'noreply@localhost'),
            'fromName' => (string)($sys['fromName'] ?? 'Sistema'),
            'smtp' => is_array($sys['smtp'] ?? null) ? $sys['smtp'] : [],
        ];
        if (!empty($extraOptions)) {
            $opts = array_merge($opts, $extraOptions);
        }
        return self::sendWithOptions($to, $subject, $bodyHtml, $bodyText, $opts);
    }

    /**
     * Construye los defaults del sistema a partir de una cuenta.
     * Extracción de lógica de getSystemDefaults() para reutilización.
     */
    protected static function buildSystemDefaultsFromAccount($acc) {
        $from = '';
        $fromName = '';
        $smtp = [
            'host' => '',
            'port' => 587,
            'secure' => 'tls',
            'user' => '',
            'pass' => '',
        ];

        if (is_array($acc)) {
            $from = trim((string)($acc['email'] ?? ''));
            $fromName = trim((string)($acc['name'] ?? ''));
            $smtp['host'] = trim((string)($acc['smtp_host'] ?? ''));
            $smtp['port'] = ($acc['smtp_port'] ?? '') !== '' ? (int)$acc['smtp_port'] : 587;
            $smtp['secure'] = strtolower(trim((string)($acc['smtp_secure'] ?? 'tls')));
            $smtp['user'] = trim((string)($acc['smtp_user'] ?? ''));
            $smtp['pass'] = (string)($acc['smtp_pass'] ?? '');
        }

        if ($from === '') {
            $from = (string)getAppSetting('mail.from', defined('MAIL_FROM') ? (string)MAIL_FROM : 'noreply@localhost');
        }
        if ($fromName === '') {
            $fromName = (string)getAppSetting('mail.from_name', defined('MAIL_FROM_NAME') ? (string)MAIL_FROM_NAME : 'Sistema');
        }

        if ($smtp['host'] === '') {
            $smtp['host'] = defined('SMTP_HOST') ? (string)SMTP_HOST : '';
            $smtp['port'] = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
            $smtp['secure'] = defined('SMTP_SECURE') ? strtolower((string)SMTP_SECURE) : 'tls';
            $smtp['user'] = defined('SMTP_USER') ? (string)SMTP_USER : '';
            $smtp['pass'] = defined('SMTP_PASS') ? (string)SMTP_PASS : '';
        }

        return [
            'from' => $from,
            'fromName' => $fromName,
            'smtp' => $smtp,
        ];
    }

    public static function sendWithOptions($to, $subjectRaw, $bodyHtml, $bodyText = null, array $options = []) {
        self::$lastError = '';
        $to = trim((string)$to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = 'Email destinatario inválido';
            return false;
        }

        $sys = self::getSystemDefaults();
        if (!isset($options['from']) || trim((string)$options['from']) === '') {
            $options['from'] = (string)($sys['from'] ?? (defined('MAIL_FROM') ? MAIL_FROM : 'noreply@localhost'));
        }
        if (!isset($options['fromName']) || trim((string)$options['fromName']) === '') {
            $options['fromName'] = (string)($sys['fromName'] ?? (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Sistema'));
        }
        if (!isset($options['smtp']) || !is_array($options['smtp']) || trim((string)($options['smtp']['host'] ?? '')) === '') {
            $options['smtp'] = is_array($sys['smtp'] ?? null) ? $sys['smtp'] : [];
        }

        $from = trim((string)($options['from'] ?? (defined('MAIL_FROM') ? MAIL_FROM : 'noreply@localhost')));
        if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $from = 'noreply@localhost';
        }
        $fromName = (string)($options['fromName'] ?? (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Sistema'));

        $attachments = $options['attachments'] ?? [];
        if (!is_array($attachments)) $attachments = [];

        $subject = self::encodeHeader((string)$subjectRaw);
        $headerLines = [
            'MIME-Version: 1.0',
            'Subject: ' . $subject,
            'From: ' . self::formatAddress($from, $fromName),
            'Reply-To: ' . $from,
            'X-Mailer: PHP/' . PHP_VERSION,
            'Date: ' . gmdate('r'),
        ];

        $bodyTextFinal = $bodyText !== null ? (string)$bodyText : strip_tags((string)$bodyHtml);
        $bodyHtmlFinal = (string)$bodyHtml;

        // ── Envío prioritario vía Resend API (HTTP REST) ─────────────────────
        // Evita problemas de puertos SMTP bloqueados, OpenSSL de socket y configuración compleja.
        $resendApiKey = self::getResendApiKey($options);
        if ($resendApiKey !== '') {
            $resendFrom = self::getResendFrom($options);
            // Si el from configurado es vacío, o de webmail público (gmail/hotmail/etc.),
            // se usa onboarding@resend.dev para que Resend no lo rechace por dominio no verificado.
            $fromForResend = $resendFrom;
            if (!empty($options['from'])
                && strpos((string)$options['from'], '@') !== false
                && !preg_match('/@(gmail|hotmail|yahoo|outlook|live|icloud)\./i', (string)$options['from'])
            ) {
                $fromForResend = (string)$options['from'];
            }
            $replyTo = (!empty($options['from']) && filter_var($options['from'], FILTER_VALIDATE_EMAIL)) ? (string)$options['from'] : null;

            $okResend = self::sendViaResend($to, (string)$subjectRaw, $bodyHtmlFinal, $bodyTextFinal, $fromForResend, $fromName, $attachments, $resendApiKey, $replyTo);
            if ($okResend) {
                return true;
            }

            // Si falló Resend y no hay host SMTP de respaldo, terminar con el error de Resend
            $smtpHostCheck = (string)($options['smtp']['host'] ?? (defined('SMTP_HOST') ? SMTP_HOST : ''));
            if ($smtpHostCheck === '') {
                return false;
            }
        }

        if (!empty($attachments)) {
            $mixed = '----=_Mixed_' . md5(uniqid('', true));
            $headerLines[] = 'Content-Type: multipart/mixed; boundary="' . $mixed . '"';
            $headerStr = implode("\r\n", $headerLines);

            $message = "--$mixed\r\n";
            
            if ($bodyHtmlFinal === '') {
                // Solo texto + adjuntos
                $message .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
                $message .= chunk_split(base64_encode($bodyTextFinal)) . "\r\n";
            } else {
                // Alternative (Texto + HTML) dentro de Mixed
                $alt = '----=_Alt_' . md5(uniqid('', true));
                $related = '----=_Rel_' . md5(uniqid('', true));
                $hasInline = false;
                foreach ($attachments as $att) {
                    if (is_array($att) && !empty($att['cid'])) { $hasInline = true; break; }
                }

                $message .= "Content-Type: multipart/alternative; boundary=\"$alt\"\r\n\r\n";

                $message .= "--$alt\r\n";
                $message .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
                $message .= chunk_split(base64_encode($bodyTextFinal)) . "\r\n";

                $message .= "--$alt\r\n";
                if ($hasInline) {
                    $message .= "Content-Type: multipart/related; boundary=\"$related\"\r\n\r\n";
                    $message .= "--$related\r\n";
                    $message .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
                    $message .= chunk_split(base64_encode($bodyHtmlFinal)) . "\r\n";
                    foreach ($attachments as $att) {
                        if (!is_array($att) || empty($att['cid'])) continue;
                        $filename = (string)($att['filename'] ?? 'archivo');
                        $contentType = (string)($att['contentType'] ?? 'application/octet-stream');
                        $content = $att['content'] ?? '';
                        $cid = (string)($att['cid'] ?? '');
                        if ($content === '' || !is_string($content)) continue;
                        $message .= "--$related\r\n";
                        $message .= 'Content-Type: ' . $contentType . '; name="' . addslashes($filename) . "\"\r\n";
                        $message .= "Content-Transfer-Encoding: base64\r\n";
                        $message .= 'Content-ID: <' . $cid . ">\r\n";
                        $message .= 'Content-Disposition: inline; filename="' . addslashes($filename) . "\"\r\n\r\n";
                        $message .= chunk_split(base64_encode($content)) . "\r\n";
                    }
                    $message .= "--$related--\r\n";
                } else {
                    $message .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
                    $message .= chunk_split(base64_encode($bodyHtmlFinal)) . "\r\n";
                }
                $message .= "--$alt--\r\n";
            }

            // Adjuntos normales
            foreach ($attachments as $att) {
                if (!is_array($att)) continue;
                // Si no hay HTML, los inline también se adjuntan normal
                if ($bodyHtmlFinal !== '' && !empty($att['cid'])) continue;
                
                $filename = (string)($att['filename'] ?? 'archivo');
                $contentType = (string)($att['contentType'] ?? 'application/octet-stream');
                $content = $att['content'] ?? '';
                if ($content === '' || !is_string($content)) continue;
                
                $message .= "--$mixed\r\n";
                $message .= 'Content-Type: ' . $contentType . '; name="' . addslashes($filename) . "\"\r\n";
                $message .= "Content-Transfer-Encoding: base64\r\n";
                $message .= 'Content-Disposition: attachment; filename="' . addslashes($filename) . "\"\r\n\r\n";
                $message .= chunk_split(base64_encode($content)) . "\r\n";
            }

            $message .= "--$mixed--";
        } else {
            if ($bodyHtmlFinal === '') {
                // Solo texto plano, sin multipart
                $headerLines[] = 'Content-Type: text/plain; charset=UTF-8';
                $headerLines[] = 'Content-Transfer-Encoding: base64';
                $headerStr = implode("\r\n", $headerLines);
                
                $message = chunk_split(base64_encode($bodyTextFinal));
            } else {
                // Multipart alternative
                $alt = '----=_Alt_' . md5(uniqid('', true));
                $headerLines[] = 'Content-Type: multipart/alternative; boundary="' . $alt . '"';
                $headerStr = implode("\r\n", $headerLines);

                $message = "--$alt\r\n";
                $message .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
                $message .= chunk_split(base64_encode($bodyTextFinal)) . "\r\n";
                $message .= "--$alt\r\n";
                $message .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
                $message .= chunk_split(base64_encode($bodyHtmlFinal)) . "\r\n";
                $message .= "--$alt--";
            }
        }

        $smtp = $options['smtp'] ?? null;
        $smtpHost = '';
        $smtpPort = 587;
        $smtpSecure = 'tls';
        $smtpUser = '';
        $smtpPass = '';
        if (is_array($smtp)) {
            $smtpHost = (string)($smtp['host'] ?? '');
            $smtpPort = isset($smtp['port']) ? (int)$smtp['port'] : 587;
            $smtpSecure = strtolower((string)($smtp['secure'] ?? 'tls'));
            $smtpUser = (string)($smtp['user'] ?? '');
            $smtpPass = (string)($smtp['pass'] ?? '');
        }

        if ($smtpHost !== '') {
            return self::sendSMTPConfig($to, $subject, $message, $headerStr, $from, $smtpHost, $smtpPort, $smtpSecure, $smtpUser, $smtpPass);
        }

        return self::sendMail($to, $subject, $message, $headerStr);
    }

    protected static function encodeHeader($str) {
        return '=?UTF-8?B?' . base64_encode($str) . '?=';
    }

    protected static function formatAddress($email, $name = null) {
        if ($name && $name !== $email) {
            return self::encodeHeader($name) . ' <' . $email . '>';
        }
        return $email;
    }

    /**
     * Envío con mail() de PHP.
     */
    protected static function sendMail($to, $subject, $body, $headers) {
        $ok = @mail($to, $subject, $body, $headers);
        if (!$ok) {
            self::$lastError = 'mail() falló. En XAMPP/Windows configura SMTP en php.ini o usa SMTP en config.php.';
        }
        return $ok;
    }

    /**
     * Envío vía SMTP (cuando SMTP_HOST está definido).
     */
    protected static function sendSMTPConfig($to, $subject, $body, $headerStr, $from, $host, $port, $secure, $user, $pass) {
        $host = (string)$host;
        $port = (int)$port;
        $secure = strtolower((string)$secure);
        $user = (string)$user;
        $pass = (string)$pass;

        // Diagnóstico rápido: OpenSSL / transportes disponibles
        $transports = function_exists('stream_get_transports') ? (array) stream_get_transports() : [];
        if (in_array($secure, ['ssl', 'tls'], true) && !extension_loaded('openssl')) {
            self::$lastError = 'SMTP requiere la extensión OpenSSL habilitada en PHP (php_openssl). En XAMPP descomenta extension=openssl en php.ini y reinicia Apache.';
            return false;
        }
        if ($secure === 'ssl' && !in_array('ssl', $transports, true)) {
            self::$lastError = 'PHP no tiene habilitado el transporte ssl. Habilita OpenSSL en php.ini (extension=openssl) y reinicia Apache.';
            return false;
        }

        $prefix = ($secure === 'ssl') ? 'ssl://' : '';
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $prefix . $host . ':' . $port,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
        );
        if (!$socket) {
            self::$lastError = "Conexión SMTP fallida: $errstr ($errno)";
            return false;
        }

        stream_set_timeout($socket, 15);
        $read = function () use ($socket) {
            $line = '';
            while ($out = fgets($socket, 8192)) {
                $line .= $out;
                if (isset($out[3]) && $out[3] === ' ') break;
            }
            return $line;
        };
        $send = function ($cmd) use ($socket, $read) {
            fwrite($socket, $cmd . "\r\n");
            return $read();
        };

        $read();
        $ehloResp = $send("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if ($secure === 'tls' && $port != 465) {
            $r = $send('STARTTLS');
            if (strpos($r, '220') !== false) {
                $crypto = 0;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT')) $crypto |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT')) $crypto |= STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT;
                if ($crypto === 0) $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                $ok = @stream_socket_enable_crypto($socket, true, $crypto);
                if (!$ok) {
                    self::$lastError = 'No se pudo activar TLS. Verifica la extensión openssl en php.ini o prueba SMTP 465 con SSL.';
                    fclose($socket);
                    return false;
                }
                $ehloResp = $send("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            } else {
                self::$lastError = 'Servidor SMTP no aceptó STARTTLS: ' . trim($r);
                fclose($socket);
                return false;
            }
        }
        if ($user !== '') {
            $authCaps = strtoupper((string)$ehloResp);
            $supportsPlain = (strpos($authCaps, 'AUTH') !== false && strpos($authCaps, 'PLAIN') !== false);
            $supportsLogin = (strpos($authCaps, 'AUTH') !== false && strpos($authCaps, 'LOGIN') !== false);

            $authed = false;
            $lastAuthResp = '';

            if ($supportsPlain) {
                $token = base64_encode("\0" . $user . "\0" . $pass);
                $lastAuthResp = $send('AUTH PLAIN ' . $token);
                $authed = (strpos($lastAuthResp, '235') !== false);
            }

            if (!$authed) {
                if (!$supportsLogin && !$supportsPlain) {
                    $supportsLogin = true;
                }
                if ($supportsLogin) {
                    $send('AUTH LOGIN');
                    $send(base64_encode($user));
                    $lastAuthResp = $send(base64_encode($pass));
                    $authed = (strpos($lastAuthResp, '235') !== false);
                }
            }

            if (!$authed) {
                self::$lastError = 'SMTP autenticación fallida' . ($lastAuthResp !== '' ? (': ' . trim($lastAuthResp)) : '');
                fclose($socket);
                return false;
            }
        }
        $send('MAIL FROM:<' . $from . '>');
        $send('RCPT TO:<' . $to . '>');
        $r = $send('DATA');
        if (strpos($r, '354') === false) {
            self::$lastError = 'SMTP DATA rechazado';
            fclose($socket);
            return false;
        }
        $full = "$headerStr\r\n\r\n$body\r\n.";
        fwrite($socket, $full . "\r\n");
        $r = $read();
        fclose($socket);
        if (strpos($r, '250') === false) {
            self::$lastError = 'SMTP rechazó el mensaje: ' . trim($r);
            return false;
        }
        return true;
    }

    /**
     * Obtiene la clave de API de Resend desde opciones, constantes o .env.
     */
    public static function getResendApiKey(array $options = []): string {
        if (!empty($options['resend_api_key'])) {
            return trim((string)$options['resend_api_key']);
        }
        if (defined('RESEND_API_KEY') && trim((string)RESEND_API_KEY) !== '') {
            return trim((string)RESEND_API_KEY);
        }
        if (function_exists('get_env')) {
            $k = (string)get_env('RESEND_API_KEY', '');
            if ($k !== '') return trim($k);
        }
        $k = getenv('RESEND_API_KEY') ?: ($_ENV['RESEND_API_KEY'] ?? ($_SERVER['RESEND_API_KEY'] ?? ''));
        return trim((string)$k);
    }

    /**
     * Obtiene el remitente de Resend (por defecto onboarding@resend.dev).
     */
    public static function getResendFrom(array $options = []): string {
        if (!empty($options['resend_from'])) {
            return trim((string)$options['resend_from']);
        }
        if (defined('RESEND_FROM') && trim((string)RESEND_FROM) !== '') {
            return trim((string)RESEND_FROM);
        }
        if (function_exists('get_env')) {
            $f = (string)get_env('RESEND_FROM', '');
            if ($f !== '') return trim($f);
        }
        return 'onboarding@resend.dev';
    }

    /**
     * Envía correo mediante la API REST de Resend (https://api.resend.com/emails).
     *
     * @param string $to
     * @param string $subject
     * @param string $bodyHtml
     * @param string $bodyText
     * @param string $fromEmail
     * @param string $fromName
     * @param array $attachments
     * @param string $apiKey
     * @param string|null $replyTo
     * @return bool
     */
    public static function sendViaResend($to, $subject, $bodyHtml, $bodyText, $fromEmail, $fromName, array $attachments, $apiKey, $replyTo = null): bool {
        $fromEmail = trim((string)$fromEmail);
        if ($fromEmail === '' || strpos($fromEmail, '@') === false) {
            $fromEmail = 'onboarding@resend.dev';
        }

        $formattedFrom = $fromName ? ($fromName . ' <' . $fromEmail . '>') : $fromEmail;

        $payload = [
            'from'    => $formattedFrom,
            'to'      => [$to],
            'subject' => (string)$subject,
        ];

        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $payload['reply_to'] = $replyTo;
        }

        if ($bodyHtml !== null && $bodyHtml !== '') {
            $payload['html'] = (string)$bodyHtml;
        }
        if ($bodyText !== null && $bodyText !== '') {
            $payload['text'] = (string)$bodyText;
        } elseif (empty($payload['html'])) {
            $payload['text'] = '';
        }

        if (!empty($attachments)) {
            $resendAtts = [];
            foreach ($attachments as $att) {
                if (!is_array($att)) continue;
                $fn = (string)($att['filename'] ?? 'attachment');
                $rawContent = $att['content'] ?? '';
                if ($rawContent === '' || !is_string($rawContent)) continue;
                $resendAtts[] = [
                    'filename' => $fn,
                    'content'  => base64_encode($rawContent),
                ];
            }
            if (!empty($resendAtts)) {
                $payload['attachments'] = $resendAtts;
            }
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (extension_loaded('curl')) {
            $ch = curl_init('https://api.resend.com/emails');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $jsonPayload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . trim($apiKey),
                    'Content-Type: application/json',
                    'User-Agent: SistemaTickets/1.0',
                ],
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                self::$lastError = 'Error cURL con Resend API: ' . $curlError;
                return false;
            }
        } else {
            $opts = [
                'http' => [
                    'method'        => 'POST',
                    'header'        => "Authorization: Bearer " . trim($apiKey) . "\r\n" .
                                       "Content-Type: application/json\r\n" .
                                       "User-Agent: SistemaTickets/1.0\r\n",
                    'content'       => $jsonPayload,
                    'timeout'       => 15,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => true,
                ],
            ];
            $context = stream_context_create($opts);
            $response = @file_get_contents('https://api.resend.com/emails', false, $context);
            $httpCode = 0;
            if (isset($http_response_header)) {
                foreach ($http_response_header as $hdr) {
                    if (preg_match('#HTTP/\S+\s+(\d+)#i', $hdr, $m)) {
                        $httpCode = (int)$m[1];
                        break;
                    }
                }
            }
            if ($response === false) {
                self::$lastError = 'Error de conexión HTTP con Resend API';
                return false;
            }
        }

        $data = json_decode((string)$response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }

        $msg = is_array($data) ? ($data['message'] ?? ($data['error'] ?? 'HTTP ' . $httpCode)) : ('HTTP ' . $httpCode . ' ' . substr((string)$response, 0, 150));
        self::$lastError = 'Resend API (' . $httpCode . '): ' . $msg;
        return false;
    }
}
