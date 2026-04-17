<?php
/**
 * TicketPdfGenerator
 * Genera el PDF de un ticket como string de bytes, sin requerir sesión HTTP.
 * Usado para adjuntar el PDF a correos de notificación.
 */

class TicketPdfGenerator {

    /**
     * Genera el contenido binario del PDF para el ticket indicado.
     *
     * @param int    $tid         ID del ticket
     * @param object $mysqli      Conexión MySQL
     * @param string $projectRoot Ruta absoluta a la raíz del proyecto
     * @return string|null  Bytes del PDF, o null si no se pudo generar
     */
    public static function generate(int $tid, $mysqli, string $projectRoot): ?string {
        if ($tid <= 0 || !$mysqli || $projectRoot === '') return null;

        $autoload = $projectRoot . '/vendor/autoload.php';
        if (!is_file($autoload)) return null;

        require_once $autoload;

        if (!class_exists('Dompdf\Dompdf') || !class_exists('Dompdf\Options')) return null;

        $html = self::buildHtml($tid, $mysqli, $projectRoot);
        if ($html === null) return null;

        try {
            $opts = new Dompdf\Options();
            $opts->set('isHtml5ParserEnabled', true);
            $opts->set('isRemoteEnabled', false);
            $opts->set('chroot', $projectRoot);

            $dompdf = new Dompdf\Dompdf($opts);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $pdf = $dompdf->output();
            return (is_string($pdf) && $pdf !== '') ? $pdf : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Construye el HTML del ticket para dompdf.
     */
    private static function buildHtml(int $tid, $mysqli, string $projectRoot): ?string {
        if (!defined('TICKET_PDF_RENDER')) {
            define('TICKET_PDF_RENDER', true);
        }
        
        // Simular variable GET para que print_ticket.php la lea
        $_GET['id'] = $tid;
        
        // Declarar $mysqli explícitamente en este scope por si print_ticket lo asume
        $mysqli = $GLOBALS['mysqli'] ?? $mysqli;
        
        ob_start();
        require $projectRoot . '/upload/scp/print_ticket.php';
        $html = (string)ob_get_clean();
        
        return $html;
    }
}
