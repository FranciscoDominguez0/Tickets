<?php
/**
 * ErrorHandler — Sistema de Tickets
 *
 * Captura global de errores PHP, excepciones no controladas y fatal errors.
 * En producción (APP_DEBUG=false): muestra página amigable con código ERR-.
 * En desarrollo (APP_DEBUG=true) : muestra stack trace completo.
 * En ambos modos: loguea el error y envía notificación por email al admin.
 *
 * Uso: ErrorHandler::register();  ← llamar una sola vez desde config.php
 */

class ErrorHandler
{
    // ─────────────────────────────────────────────────────────────────────────
    //  Registro global
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Registra todos los handlers globales de PHP.
     * Debe llamarse lo antes posible, justo después de definir APP_DEBUG.
     */
    public static function register(): void
    {
        // En producción, PHP no debe mostrar errores en pantalla
        if (!self::isDebug()) {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL);
        }

        // Excepciones no controladas
        set_exception_handler([self::class, 'handleException']);

        // Errores PHP (warnings, notices, etc.) → convertidos en excepción interna
        set_error_handler([self::class, 'handleError']);

        // Fatal errors (E_ERROR, E_PARSE, E_CORE_ERROR...) via shutdown
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Handlers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Maneja cualquier Throwable (Exception o Error) no capturado.
     */
    public static function handleException(\Throwable $e): void
    {
        $code = self::generateErrorCode();

        self::logError($code, $e);

        // Determinar HTTP status code según tipo de excepción
        $httpCode = self::resolveHttpCode($e);

        self::renderErrorPage($httpCode, $code, $e);
    }

    /**
     * Convierte errores PHP en ErrorException para tratarlos uniformemente.
     * Los errores con @ de supresión (error_reporting() == 0) se ignoran.
     *
     * @throws \ErrorException
     */
    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        // Respetar operador @
        if (!(error_reporting() & $errno)) {
            return false;
        }

        // Solo capturar errores graves + warnings en producción
        $capture = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_WARNING, E_USER_WARNING];
        if (!in_array($errno, $capture, true)) {
            // Notices, deprecated, etc.: solo loguear en producción, no interrumpir
            if (!self::isDebug()) {
                self::logError(self::generateErrorCode(), [
                    'type'    => 'PHPNotice',
                    'errno'   => $errno,
                    'message' => $errstr,
                    'file'    => $errfile,
                    'line'    => $errline,
                ]);
                return true; // evitar que PHP continue con su handler
            }
            return false; // en debug, PHP muestra el error normalmente
        }

        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    /**
     * Captura Fatal Errors que set_error_handler() no puede interceptar.
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

        if ($error !== null && in_array($error['type'], $fatalTypes, true)) {
            $code = self::generateErrorCode();

            self::logError($code, [
                'type'    => 'FatalError',
                'errno'   => $error['type'],
                'message' => $error['message'],
                'file'    => $error['file'],
                'line'    => $error['line'],
            ]);

            // Solo renderizar si los headers aún no fueron enviados
            if (!headers_sent()) {
                self::renderErrorPage(500, $code, null, $error);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Generación de código de error único
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Genera un código de error único con formato: ERR-YYYYMMDD-XXXXXX
     *
     * Usa random_bytes para garantizar unicidad criptográfica.
     * Ejemplo: ERR-20260803-A82F91
     */
    public static function generateErrorCode(): string
    {
        $date = date('Ymd');
        $rand = strtoupper(bin2hex(random_bytes(3))); // 3 bytes → 6 hex chars
        return "ERR-{$date}-{$rand}";
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Logging
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Escribe el error completo en el log (nunca expuesto al usuario).
     *
     * @param string                   $code  Código ERR- único
     * @param \Throwable|array<string,mixed> $error Excepción o array de datos de error
     */
    public static function logError(string $code, \Throwable|array $error): void
    {
        // Obtener datos del usuario autenticado si existe sesión
        $userId   = null;
        $userName = null;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $userId   = $_SESSION['user_id'] ?? $_SESSION['staff_id'] ?? null;
            $userName = $_SESSION['user_name'] ?? $_SESSION['staff_name'] ?? null;
        }

        if ($error instanceof \Throwable) {
            $entry = [
                'error_code'  => $code,
                'date'        => date('Y-m-d H:i:s'),
                'type'        => get_class($error),
                'message'     => $error->getMessage(),
                'file'        => self::sanitizePath($error->getFile()),
                'line'        => $error->getLine(),
                'trace'       => self::isDebug() ? $error->getTraceAsString() : '[omitted in production]',
                'previous'    => $error->getPrevious() ? $error->getPrevious()->getMessage() : null,
            ];
        } else {
            $entry = [
                'error_code'  => $code,
                'date'        => date('Y-m-d H:i:s'),
                'type'        => $error['type'] ?? 'UnknownError',
                'message'     => $error['message'] ?? '',
                'file'        => self::sanitizePath((string)($error['file'] ?? '')),
                'line'        => (int)($error['line'] ?? 0),
            ];
        }

        // Contexto de la petición HTTP
        $entry['request'] = [
            'url'        => self::getCurrentUrl(),
            'method'     => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'ip'         => self::getClientIp(),
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
            'referrer'   => substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 500),
        ];

        // Contexto del usuario
        $entry['user'] = [
            'user_id'   => $userId,
            'user_name' => $userName,
        ];

        Logger::write($entry);

        // ── Notificación por email ──────────────────────────────────────────
        // Solo para errores HTTP 500/503 (no para 404, 403, notices, etc.)
        // Se omite en modo debug para no saturar en desarrollo.
        if (!self::isDebug()) {
            $type = $entry['type'] ?? '';
            $skipTypes = ['HTTP400', 'HTTP403', 'HTTP404', 'PHPNotice'];
            if (!in_array($type, $skipTypes, true)) {
                self::sendErrorEmail($entry);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Envío de email de alerta
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Envía un email al administrador cuando ocurre un error crítico.
     *
     * Protecciones:
     * - Rate limiting: un email por código ERR- como máximo (nunca se repite).
     * - Falla silenciosa: si Mailer no está disponible, no interrumpe la app.
     * - No genera errores recursivos: desactiva handlers temporalmente.
     *
     * La dirección de destino se lee de (en orden de prioridad):
     *   1. Constante ERROR_NOTIFY_EMAIL definida en config.php
     *   2. Setting de base de datos 'system.error_notify_email'
     *   3. Setting 'mail.admin_email'
     *
     * @param array<string,mixed> $entry Datos del error ya loguados
     */
    private static function sendErrorEmail(array $entry): void
    {
        // Rate limiting en sesión: evitar enviar el mismo código dos veces
        static $sent = [];
        $code = (string)($entry['error_code'] ?? '');
        if ($code === '' || isset($sent[$code])) {
            return;
        }
        $sent[$code] = true;

        // Obtener email de destino
        $to = '';
        if (defined('ERROR_NOTIFY_EMAIL') && filter_var(ERROR_NOTIFY_EMAIL, FILTER_VALIDATE_EMAIL)) {
            $to = ERROR_NOTIFY_EMAIL;
        } elseif (function_exists('getAppSetting')) {
            $v = (string)getAppSetting('system.error_notify_email', '');
            if (filter_var($v, FILTER_VALIDATE_EMAIL)) {
                $to = $v;
            } else {
                $v2 = (string)getAppSetting('mail.admin_email', '');
                if (filter_var($v2, FILTER_VALIDATE_EMAIL)) {
                    $to = $v2;
                }
            }
        }

        if ($to === '') {
            // Sin destinatario configurado, no se puede enviar
            return;
        }

        if (!class_exists('Mailer')) {
            @require_once __DIR__ . '/Mailer.php';
        }
        if (!class_exists('Mailer')) {
            return;
        }

        $appName  = defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets';
        $date     = htmlspecialchars((string)($entry['date'] ?? date('Y-m-d H:i:s')), ENT_QUOTES);
        $type     = htmlspecialchars((string)($entry['type'] ?? 'Error'), ENT_QUOTES);
        $message  = htmlspecialchars((string)($entry['message'] ?? ''), ENT_QUOTES);
        $file     = htmlspecialchars((string)($entry['file'] ?? ''), ENT_QUOTES);
        $line     = (int)($entry['line'] ?? 0);
        $url      = htmlspecialchars((string)($entry['request']['url'] ?? ''), ENT_QUOTES);
        $method   = htmlspecialchars((string)($entry['request']['method'] ?? ''), ENT_QUOTES);
        $ip       = htmlspecialchars((string)($entry['request']['ip'] ?? ''), ENT_QUOTES);
        $userId   = htmlspecialchars((string)($entry['user']['user_id'] ?? 'Anónimo'), ENT_QUOTES);
        $userName = htmlspecialchars((string)($entry['user']['user_name'] ?? '—'), ENT_QUOTES);
        $logPath  = htmlspecialchars(basename(Logger::getLogPath()), ENT_QUOTES);

        $subject = "[{$appName}] Error crítico: {$code}";

        $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><style>
  body{font-family:'Segoe UI',Arial,sans-serif;background:#f1f5f9;margin:0;padding:20px}
  .card{background:#fff;border-radius:10px;max-width:620px;margin:0 auto;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)}
  .header{background:#b91c1c;padding:24px 28px;color:#fff}
  .header h1{margin:0;font-size:20px;font-weight:700}
  .header p{margin:6px 0 0;opacity:.85;font-size:13px}
  .body{padding:24px 28px}
  .code-box{background:#0f172a;color:#34d399;font-family:monospace;font-size:18px;font-weight:700;
            text-align:center;padding:14px;border-radius:8px;letter-spacing:.1em;margin-bottom:20px}
  table{width:100%;border-collapse:collapse;font-size:13px}
  td{padding:8px 10px;border-bottom:1px solid #f1f5f9;vertical-align:top}
  td:first-child{width:35%;color:#64748b;font-weight:600;white-space:nowrap}
  td:last-child{color:#1e293b;word-break:break-all}
  .msg{background:#fef2f2;border-left:4px solid #ef4444;padding:10px 14px;border-radius:4px;
       font-family:monospace;font-size:12px;color:#7f1d1d;margin-bottom:16px;word-break:break-all}
  .footer{background:#f8fafc;padding:14px 28px;font-size:11px;color:#94a3b8;text-align:center}
</style></head>
<body>
<div class="card">
  <div class="header">
    <h1>⚠ Error crítico detectado</h1>
    <p>{$appName} — {$date}</p>
  </div>
  <div class="body">
    <div class="code-box">{$code}</div>
    <div class="msg">{$type}: {$message}</div>
    <table>
      <tr><td>Archivo</td><td>{$file} <strong>línea {$line}</strong></td></tr>
      <tr><td>URL</td><td>[{$method}] {$url}</td></tr>
      <tr><td>IP</td><td>{$ip}</td></tr>
      <tr><td>Usuario</td><td>{$userName} (ID: {$userId})</td></tr>
      <tr><td>Log del día</td><td>storage/logs/{$logPath}</td></tr>
    </table>
  </div>
  <div class="footer">
    Busca <strong>{$code}</strong> en el archivo de log para ver el detalle completo.<br>
    Este aviso fue generado automáticamente. No respondas este correo.
  </div>
</div>
</body></html>
HTML;

        // ── Estrategia de envío ───────────────────────────────────────────────
        // Intento A: Mailer con SMTP de la DB (cuenta configurada en el panel SCP)
        // Intento B: Mailer con constantes SMTP de config.php (funciona sin DB)
        // Intento C: mail() nativo PHP (último recurso — requiere sendmail en php.ini)
        // Intento D: Registrar el fallo en el log para diagnóstico

        $emailSent = false;

        if (!class_exists('Mailer')) {
            @require_once __DIR__ . '/Mailer.php';
        }

        $dbAlive = isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof \mysqli;

        // ── Intento A: Mailer via DB ──────────────────────────────────────────
        if (!$emailSent && $dbAlive && class_exists('Mailer')) {
            try {
                $emailSent = Mailer::send($to, $subject, $html);
            } catch (\Throwable) {
                $emailSent = false;
            }
        }

        // ── Intento B: Mailer via constantes SMTP de config.php ───────────────
        // Funciona incluso cuando la DB está caída porque usa solo constantes PHP.
        if (!$emailSent && class_exists('Mailer')
            && defined('SMTP_HOST') && SMTP_HOST !== ''
            && defined('SMTP_USER') && SMTP_USER !== ''
            && defined('SMTP_PASS') && SMTP_PASS !== ''
        ) {
            try {
                $from = defined('MAIL_FROM') && MAIL_FROM !== '' ? MAIL_FROM : SMTP_USER;
                $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : (defined('APP_NAME') ? APP_NAME : 'Sistema de Tickets');
                $emailSent = Mailer::sendWithOptions($to, $subject, $html, null, [
                    'from'     => $from,
                    'fromName' => $fromName,
                    'smtp'     => [
                        'host'   => SMTP_HOST,
                        'port'   => defined('SMTP_PORT')   ? SMTP_PORT   : 587,
                        'secure' => defined('SMTP_SECURE') ? SMTP_SECURE : 'tls',
                        'user'   => SMTP_USER,
                        'pass'   => SMTP_PASS,
                    ],
                ]);
            } catch (\Throwable) {
                $emailSent = false;
            }
        }

        // ── Intento C: mail() nativo PHP ──────────────────────────────────────
        if (!$emailSent) {
            try {
                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $headers .= "From: " . (defined('APP_NAME') ? APP_NAME : 'Sistema') . " <noreply@localhost>\r\n";
                $headers .= "X-Priority: 1\r\n";
                $emailSent = @mail($to, $subject, $html, $headers);
            } catch (\Throwable) {
                $emailSent = false;
            }
        }

        // ── Intento D: Registrar fallo en el log ──────────────────────────────
        if (!$emailSent) {
            Logger::write([
                'type'       => 'EmailNotificationFailed',
                'message'    => "No se pudo enviar alerta por email para {$code} a {$to}. "
                              . "Verifica SMTP_PASS en config.php o la cuenta SMTP en el panel SCP. "
                              . "Mailer::lastError: " . (class_exists('Mailer') ? (Mailer::$lastError ?: 'sin error reportado') : 'Mailer no disponible'),
                'error_code' => $code,
                'db_alive'   => $dbAlive ? 'si' : 'no',
                'smtp_host_defined' => (defined('SMTP_HOST') && SMTP_HOST !== '') ? 'si' : 'no',
                'smtp_pass_defined' => (defined('SMTP_PASS') && SMTP_PASS !== '') ? 'si' : 'no (CONFIGURA SMTP_PASS en config.php)',
            ]);
        }
    }


    /**
     * Muestra la página de error al usuario.
     * En producción: página amigable con código ERR-.
     * En debug: stack trace completo con todos los detalles.
     *
     * @param int             $httpCode  Código HTTP (400, 403, 404, 500, 503)
     * @param string          $errorCode Código ERR- generado
     * @param \Throwable|null $e         Excepción original (puede ser null en fatals)
     * @param array<string,mixed>|null $fatalData Datos del fatal error (desde shutdown)
     */
    public static function renderErrorPage(
        int $httpCode,
        string $errorCode,
        ?\Throwable $e = null,
        ?array $fatalData = null
    ): void {
        // Limpiar cualquier output parcial
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($httpCode);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }

        if (self::isDebug()) {
            // ── MODO DEBUG ──────────────────────────────────────────────────
            self::renderDebugPage($httpCode, $errorCode, $e, $fatalData);
        } else {
            // ── MODO PRODUCCIÓN ─────────────────────────────────────────────
            $pageFile = __DIR__ . '/error_pages/' . $httpCode . '.php';
            $fallback = __DIR__ . '/error_pages/500.php';

            if (file_exists($pageFile)) {
                require $pageFile;
            } elseif (file_exists($fallback)) {
                require $fallback;
            } else {
                // Fallback mínimo si no existen las vistas
                echo self::minimalErrorHtml($httpCode, $errorCode);
            }
        }

        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Utilidades privadas
    // ─────────────────────────────────────────────────────────────────────────

    private static function isDebug(): bool
    {
        return defined('APP_DEBUG') && APP_DEBUG === true;
    }

    /**
     * Determina el código HTTP según el tipo de excepción.
     */
    private static function resolveHttpCode(\Throwable $e): int
    {
        $class = get_class($e);

        // Convenio: si la excepción tiene getCode() con un código HTTP válido, usarlo
        $code = (int)$e->getCode();
        $validHttpCodes = [400, 401, 403, 404, 405, 408, 422, 429, 500, 502, 503, 504];

        if (in_array($code, $validHttpCodes, true)) {
            return $code;
        }

        // Mapeo por nombre de clase (añadir los propios del proyecto aquí)
        $map = [
            'RuntimeException'          => 500,
            'InvalidArgumentException'  => 400,
            'UnexpectedValueException'  => 500,
            'ErrorException'            => 500,
        ];

        return $map[$class] ?? 500;
    }

    /**
     * Elimina la ruta absoluta del servidor para no exponerla en logs.
     * Deja solo la ruta relativa desde la raíz del proyecto.
     */
    private static function sanitizePath(string $path): string
    {
        $root = str_replace('\\', '/', dirname(__DIR__));
        $path = str_replace('\\', '/', $path);

        if (str_starts_with($path, $root)) {
            return '...' . substr($path, strlen($root));
        }

        return basename($path); // En el peor caso, solo el nombre del archivo
    }

    /**
     * Construye la URL completa de la petición actual.
     */
    private static function getCurrentUrl(): string
    {
        if (php_sapi_name() === 'cli') {
            return 'CLI';
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            ? 'https' : 'http';

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';

        return $scheme . '://' . $host . $uri;
    }

    /**
     * Obtiene la IP real del cliente (soporta proxies / Nginx Proxy Manager).
     */
    private static function getClientIp(): string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', (string)$_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Página de debug HTML completa (solo en APP_DEBUG=true).
     *
     * @param int             $httpCode
     * @param string          $errorCode
     * @param \Throwable|null $e
     * @param array<string,mixed>|null $fatalData
     */
    private static function renderDebugPage(
        int $httpCode,
        string $errorCode,
        ?\Throwable $e,
        ?array $fatalData
    ): void {
        $type    = $e ? get_class($e) : ($fatalData['type'] ?? 'FatalError');
        $message = $e ? htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
                      : htmlspecialchars((string)($fatalData['message'] ?? ''), ENT_QUOTES, 'UTF-8');
        $file    = $e ? htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8')
                      : htmlspecialchars((string)($fatalData['file'] ?? ''), ENT_QUOTES, 'UTF-8');
        $line    = $e ? $e->getLine() : (int)($fatalData['line'] ?? 0);
        $trace   = $e ? htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') : '(fatal error — no trace available)';

        echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Error {$httpCode} — Debug Mode</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',system-ui,sans-serif;background:#0f172a;color:#e2e8f0;padding:2rem}
  .badge{display:inline-block;padding:.25rem .6rem;border-radius:4px;font-size:.75rem;font-weight:700;background:#ef4444;color:#fff;margin-bottom:1rem}
  .card{background:#1e293b;border:1px solid #334155;border-radius:8px;padding:1.5rem;margin-bottom:1.5rem}
  h1{font-size:1.5rem;color:#f87171;margin-bottom:.5rem}
  h2{font-size:1rem;color:#94a3b8;margin-bottom:1rem;text-transform:uppercase;letter-spacing:.05em}
  .message{font-size:1.1rem;color:#fbbf24;background:#1c1917;padding:1rem;border-radius:6px;border-left:4px solid #f59e0b;word-break:break-word}
  .location{font-size:.875rem;color:#64748b;margin-top:.75rem}
  .location strong{color:#94a3b8}
  pre{background:#0f172a;border:1px solid #334155;border-radius:6px;padding:1rem;overflow-x:auto;font-size:.8rem;color:#7dd3fc;line-height:1.6;white-space:pre-wrap;word-break:break-all}
  .meta{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem}
  .meta-item{background:#0f172a;border-radius:6px;padding:.75rem}
  .meta-item label{display:block;font-size:.7rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem}
  .meta-item span{font-size:.85rem;color:#e2e8f0;word-break:break-all}
  .errcode{font-family:monospace;font-size:1.1rem;color:#34d399;background:#052e16;padding:.5rem 1rem;border-radius:6px;display:inline-block}
  footer{margin-top:2rem;font-size:.75rem;color:#475569;text-align:center}
</style>
</head>
<body>
<div class="badge">⚠ APP_DEBUG = true — SOLO VISIBLE EN DESARROLLO</div>
<div class="card">
  <h2>Error {$httpCode}</h2>
  <h1>{$type}</h1>
  <div class="message">{$message}</div>
  <p class="location"><strong>Archivo:</strong> {$file}  <strong>Línea:</strong> {$line}</p>
  <p class="location" style="margin-top:.5rem"><strong>Código:</strong> <span class="errcode">{$errorCode}</span></p>
</div>
<div class="card">
  <h2>Stack Trace</h2>
  <pre>{$trace}</pre>
</div>
<div class="card">
  <h2>Contexto de la Petición</h2>
  <div class="meta">
    <div class="meta-item"><label>URL</label><span>{$_SERVER['REQUEST_URI']}</span></div>
    <div class="meta-item"><label>Método</label><span>{$_SERVER['REQUEST_METHOD']}</span></div>
    <div class="meta-item"><label>IP</label><span>{self::getClientIp()}</span></div>
    <div class="meta-item"><label>Timestamp</label><span>{date('Y-m-d H:i:s')}</span></div>
  </div>
</div>
<footer>Este detalle solo se muestra cuando <code>APP_DEBUG = true</code>. Deshabilítalo en producción.</footer>
</body>
</html>
HTML;
    }

    /**
     * HTML mínimo de emergencia si no existen las vistas de error.
     */
    private static function minimalErrorHtml(int $httpCode, string $errorCode): string
    {
        $messages = [
            400 => 'Solicitud incorrecta',
            403 => 'Acceso denegado',
            404 => 'Página no encontrada',
            503 => 'Servicio no disponible',
        ];
        $msg = $messages[$httpCode] ?? 'Error inesperado';

        return <<<HTML
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>Error {$httpCode}</title></head>
<body style="font-family:sans-serif;text-align:center;padding:4rem">
<h1>Error {$httpCode} — {$msg}</h1>
<p>Ha ocurrido un error inesperado. Por favor contacte a soporte.</p>
<p style="font-family:monospace;color:#666">Código: {$errorCode}</p>
</body></html>
HTML;
    }
}
