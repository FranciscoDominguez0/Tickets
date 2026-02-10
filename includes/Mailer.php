<?php
/**
 * Envío de correo para notificaciones del sistema de tickets.
 * Soporta mail() nativo o SMTP (Gmail, Outlook, etc.).
 */

require_once __DIR__ . '/helpers.php';

class Mailer {

    /** @var string Último error si falla el envío */
    public static $lastError = '';

    protected static $defaultAccountCache = null;

    protected static function getDefaultEmailAccount() {
        if (self::$defaultAccountCache !== null) return self::$defaultAccountCache;

        self::$defaultAccountCache = false;
        if (!isset($GLOBALS['mysqli']) || !$GLOBALS['mysqli']) return self::$defaultAccountCache;
        $mysqli = $GLOBALS['mysqli'];

        $chk = $mysqli->query("SHOW TABLES LIKE 'email_accounts'");
        if (!$chk || $chk->num_rows < 1) return self::$defaultAccountCache;

        $res = $mysqli->query("SELECT * FROM email_accounts WHERE is_default = 1 ORDER BY id ASC LIMIT 1");
        if ($res) {
            $row = $res->fetch_assoc();
            if (is_array($row) && !empty($row['email'])) {
                self::$defaultAccountCache = $row;
                return self::$defaultAccountCache;
            }
        }

        $mailFrom = trim((string)getAppSetting('mail.from', defined('MAIL_FROM') ? (string)MAIL_FROM : ''));
        if ($mailFrom !== '' && filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
            $stmt = $mysqli->prepare('SELECT * FROM email_accounts WHERE email = ? ORDER BY id ASC LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $mailFrom);
                if ($stmt->execute()) {
                    $row = $stmt->get_result()->fetch_assoc();
                    if (is_array($row) && !empty($row['email'])) {
                        self::$defaultAccountCache = $row;
                        return self::$defaultAccountCache;
                    }
                }
            }
        }

        $resAny = $mysqli->query('SELECT * FROM email_accounts ORDER BY is_default DESC, id ASC LIMIT 1');
        if ($resAny) {
            $row = $resAny->fetch_assoc();
            if (is_array($row) && !empty($row['email'])) {
                self::$defaultAccountCache = $row;
                return self::$defaultAccountCache;
            }
        }

        return self::$defaultAccountCache;
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

        if (!empty($attachments)) {
            $mixed = '----=_Mixed_' . md5(uniqid('', true));
            $alt = '----=_Alt_' . md5(uniqid('', true));
            $related = '----=_Rel_' . md5(uniqid('', true));
            $headerLines[] = 'Content-Type: multipart/mixed; boundary="' . $mixed . '"';
            $headerStr = implode("\r\n", $headerLines);

            $hasInline = false;
            foreach ($attachments as $att) {
                if (is_array($att) && !empty($att['cid'])) { $hasInline = true; break; }
            }

            $message = "--$mixed\r\n";
            $message .= "Content-Type: multipart/alternative; boundary=\"$alt\"\r\n\r\n";

            $message .= "--$alt\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
            $message .= base64_encode($bodyTextFinal) . "\r\n";

            $message .= "--$alt\r\n";
            if ($hasInline) {
                $message .= "Content-Type: multipart/related; boundary=\"$related\"\r\n\r\n";
                $message .= "--$related\r\n";
                $message .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
                $message .= base64_encode($bodyHtmlFinal) . "\r\n";
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
                $message .= base64_encode($bodyHtmlFinal) . "\r\n";
            }

            $message .= "--$alt--\r\n";

            foreach ($attachments as $att) {
                if (!is_array($att) || !empty($att['cid'])) continue;
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
            $alt = '----=_Alt_' . md5(uniqid('', true));
            $headerLines[] = 'Content-Type: multipart/alternative; boundary="' . $alt . '"';
            $headerStr = implode("\r\n", $headerLines);

            $message = "--$alt\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
            $message .= base64_encode($bodyTextFinal) . "\r\n";
            $message .= "--$alt\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
            $message .= base64_encode($bodyHtmlFinal) . "\r\n";
            $message .= "--$alt--";
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
}
