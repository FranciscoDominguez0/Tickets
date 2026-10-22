<?php
/**
 * Logger — Sistema de Tickets
 *
 * Escribe entradas JSON Lines en storage/logs/app-YYYY-MM-DD.log
 * Rotación diaria automática por nombre de archivo.
 * Acceso web bloqueado via .htaccess en el directorio.
 */

class Logger
{
    /** Ruta base al directorio de logs */
    private static string $logDir = '';

    // ─────────────────────────────────────────────────────────────────────────
    //  Configuración
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Inicializa (y crea si es necesario) el directorio de logs.
     * Se llama automáticamente en write().
     */
    private static function init(): void
    {
        if (self::$logDir !== '') {
            return;
        }

        // Directorio relativo a la raíz del proyecto
        self::$logDir = dirname(__DIR__) . '/storage/logs';

        if (!is_dir(self::$logDir)) {
            // Crea con permisos restrictivos; suprime errores de race-condition
            @mkdir(self::$logDir, 0750, true);
        }

        // Asegurar .htaccess de protección si por algún motivo no existe
        $htaccess = self::$logDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            $content = "# Deny access to log files from web\n"
                     . "<FilesMatch \"\\.log$\">\n"
                     . "    Order Allow,Deny\n"
                     . "    Deny from all\n"
                     . "</FilesMatch>\n"
                     . "Options -Indexes\n";
            @file_put_contents($htaccess, $content);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Escritura
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Escribe una entrada en el log del día.
     *
     * @param array<string,mixed> $data Datos a loguear
     */
    public static function write(array $data): void
    {
        self::init();

        // Añade timestamp si no viene ya
        if (!isset($data['logged_at'])) {
            $data['logged_at'] = date('Y-m-d H:i:s');
        }

        $line  = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        $path  = self::getLogPath();

        // FILE_APPEND + LOCK_EX para evitar race conditions
        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);

        // También registrar en error_log de PHP/Apache para visibilidad directa en Docker logs
        $code = (string)($data['error_code'] ?? 'APP');
        $msg  = (string)($data['message'] ?? 'Unknown error');
        $type = (string)($data['type'] ?? 'Error');
        $file = (string)($data['file'] ?? '');
        $loc  = $file !== '' ? " in {$file}:" . ($data['line'] ?? '0') : '';
        @error_log("[{$code}] {$type}: {$msg}{$loc}");
    }

    /**
     * Registra un error en el log.
     *
     * @param string $message Mensaje de error
     * @param array<string,mixed> $context Datos adicionales de contexto
     */
    public static function error(string $message, array $context = []): void
    {
        self::write(array_merge([
            'type'       => 'Error',
            'error_code' => 'ERROR',
            'message'    => $message,
        ], $context));
    }

    /**
     * Registra una advertencia en el log.
     *
     * @param string $message Mensaje de advertencia
     * @param array<string,mixed> $context Datos adicionales de contexto
     */
    public static function warning(string $message, array $context = []): void
    {
        self::write(array_merge([
            'type'       => 'Warning',
            'error_code' => 'WARN',
            'message'    => $message,
        ], $context));
    }

    /**
     * Registra un mensaje informativo en el log.
     *
     * @param string $message Mensaje informativo
     * @param array<string,mixed> $context Datos adicionales de contexto
     */
    public static function info(string $message, array $context = []): void
    {
        self::write(array_merge([
            'type'       => 'Info',
            'error_code' => 'INFO',
            'message'    => $message,
        ], $context));
    }

    /**
     * Registra un mensaje de depuración en el log.
     *
     * @param string $message Mensaje de debug
     * @param array<string,mixed> $context Datos adicionales de contexto
     */
    public static function debug(string $message, array $context = []): void
    {
        self::write(array_merge([
            'type'       => 'Debug',
            'error_code' => 'DEBUG',
            'message'    => $message,
        ], $context));
    }

    /**
     * Retorna la ruta absoluta del archivo de log del día actual.
     */
    public static function getLogPath(): string
    {
        self::init();
        return self::$logDir . '/app-' . date('Y-m-d') . '.log';
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Utilidad: Limpieza de logs antiguos
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Elimina archivos de log más antiguos que $keepDays días.
     * Útil para llamar desde un cron job.
     *
     * @param int $keepDays Número de días de logs a conservar (default: 30)
     */
    public static function cleanup(int $keepDays = 30): void
    {
        self::init();

        $cutoff = time() - ($keepDays * 86400);

        foreach (glob(self::$logDir . '/app-*.log') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }
}
