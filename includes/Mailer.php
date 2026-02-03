<?php
/**
 * Envío de correo para notificaciones del sistema de tickets.
 * Soporta mail() nativo o SMTP (Gmail, Outlook, etc.).
 */

class Mailer {

    /** @var string Último error si falla el envío */
    public static $lastError = '';

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

        $from = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@localhost';
        $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Sistema';

        $subject = self::encodeHeader($subject);
        $boundary = '----=_Part_' . md5(uniqid());

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: ' . self::formatAddress($from, $fromName),
            'Reply-To: ' . $from,
            'X-Mailer: PHP/' . PHP_VERSION,
            'Date: ' . gmdate('r'),
        ];
        $headerStr = implode("\r\n", $headers);

        $message = "--$boundary\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $message .= base64_encode($bodyText !== null ? $bodyText : strip_tags($bodyHtml)) . "\r\n";
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $message .= base64_encode($bodyHtml) . "\r\n";
        $message .= "--$boundary--";

        if (defined('SMTP_HOST') && SMTP_HOST !== '') {
            return self::sendSMTP($to, $subject, $message, $headerStr, $from, $fromName);
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
    protected static function sendSMTP($to, $subject, $body, $headerStr, $from, $fromName) {
        $host = SMTP_HOST;
        $port = defined('SMTP_PORT') ? (int) SMTP_PORT : 587;
        $secure = defined('SMTP_SECURE') ? strtolower(SMTP_SECURE) : 'tls';
        $user = defined('SMTP_USER') ? SMTP_USER : '';
        $pass = defined('SMTP_PASS') ? SMTP_PASS : '';

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
        $send("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
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
                $send("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            } else {
                self::$lastError = 'Servidor SMTP no aceptó STARTTLS: ' . trim($r);
                fclose($socket);
                return false;
            }
        }
        if ($user !== '') {
            $send('AUTH LOGIN');
            $send(base64_encode($user));
            $r = $send(base64_encode($pass));
            if (strpos($r, '235') === false) {
                self::$lastError = 'SMTP autenticación fallida';
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
        $full = "Subject: $subject\r\n$headerStr\r\n\r\n$body\r\n.";
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
