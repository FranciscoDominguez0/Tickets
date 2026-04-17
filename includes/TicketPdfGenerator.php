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
        // Cargar datos del ticket
        $stmt = $mysqli->prepare(
            "SELECT t.*, u.firstname AS user_first, u.lastname AS user_last, u.email AS user_email,"
            . " s.firstname AS staff_first, s.lastname AS staff_last, s.email AS staff_email,"
            . " d.name AS dept_name, ts.name AS status_name,"
            . " p.name AS priority_name"
            . " FROM tickets t"
            . " JOIN users u ON t.user_id = u.id"
            . " LEFT JOIN staff s ON t.staff_id = s.id"
            . " JOIN departments d ON t.dept_id = d.id"
            . " JOIN ticket_status ts ON t.status_id = ts.id"
            . " JOIN priorities p ON t.priority_id = p.id"
            . " WHERE t.id = ? LIMIT 1"
        );
        if (!$stmt) return null;
        $stmt->bind_param('i', $tid);
        $stmt->execute();
        $t = $stmt->get_result()->fetch_assoc();
        if (!$t) return null;

        // Thread / mensajes
        $stmt = $mysqli->prepare("SELECT id FROM threads WHERE ticket_id = ? LIMIT 1");
        $stmt->bind_param('i', $tid);
        $stmt->execute();
        $threadRow = $stmt->get_result()->fetch_assoc();
        $thread_id = (int)($threadRow['id'] ?? 0);

        $entries = [];
        if ($thread_id > 0) {
            $stmt = $mysqli->prepare(
                "SELECT te.id, te.user_id, te.staff_id, te.body, te.is_internal, te.created,"
                . " u.firstname AS user_first, u.lastname AS user_last,"
                . " s.firstname AS staff_first, s.lastname AS staff_last"
                . " FROM thread_entries te"
                . " LEFT JOIN users u ON te.user_id = u.id"
                . " LEFT JOIN staff s ON te.staff_id = s.id"
                . " WHERE te.thread_id = ? AND te.is_internal = 0"
                . " ORDER BY te.created ASC"
            );
            $stmt->bind_param('i', $thread_id);
            $stmt->execute();
            $entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        // Datos de la empresa / logo
        $companyName = function_exists('getAppSetting') ? trim((string)getAppSetting('company.name', '')) : '';
        if ($companyName === '') $companyName = defined('APP_NAME') ? (string)APP_NAME : 'Sistema de Tickets';
        $companyWebsite = function_exists('getAppSetting') ? trim((string)getAppSetting('company.website', '')) : '';
        if ($companyWebsite === '') $companyWebsite = defined('APP_URL') ? (string)APP_URL : '';

        $logoMode    = function_exists('getAppSetting') ? (string)getAppSetting('company.logo_mode', '') : '';
        $logoSetting = function_exists('getAppSetting') ? (string)getAppSetting('company.logo', '') : '';
        $logoRel     = 'publico/img/vigitec-logo.png';
        if ($logoMode === '') {
            $logoMode = $logoSetting !== '' ? 'custom' : 'default';
        }
        if ($logoMode === 'custom' && $logoSetting !== '') {
            $candidate = ltrim(str_replace('\\', '/', (string)$logoSetting), '/');
            if ($candidate !== '' && is_file($projectRoot . '/' . $candidate)) {
                $logoRel = $candidate;
            }
        }
        $logoUrl = '/' . ltrim($logoRel, '/');

        $userName  = trim((string)($t['user_first'] ?? '') . ' ' . (string)($t['user_last'] ?? ''));
        if ($userName === '') $userName = (string)($t['user_email'] ?? '');
        $staffName = trim((string)($t['staff_first'] ?? '') . ' ' . (string)($t['staff_last'] ?? ''));
        if ($staffName === '') $staffName = '— Sin asignar —';

        $ticketClientSignatureUrl = '';
        $sigPath = ltrim(str_replace('\\', '/', trim((string)($t['client_signature'] ?? ''))), '/');
        if ($sigPath !== '' && is_file($projectRoot . '/' . $sigPath)) {
            $ticketClientSignatureUrl = '/' . ltrim($sigPath, '/');
        }

        $esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

        // Sanitize rich text (usa la función global si existe, de lo contrario usa strip_tags)
        $sanitize = function_exists('sanitizeRichText')
            ? 'sanitizeRichText'
            : fn($v) => nl2br($esc($v));

        // ---- HTML ----
        $html  = '<!DOCTYPE html><html lang="es"><head>';
        $html .= '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html .= '<title>Ticket ' . $esc($t['ticket_number'] ?? ('#' . $tid)) . '</title>';
        $html .= '<style>';
        $html .= ':root{--ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--paper:#ffffff;--soft:#f8fafc;--brand:#2563eb;}';
        $html .= 'html,body{background:var(--paper);color:var(--ink);font-family:"Lato","Segoe UI",Arial,sans-serif;font-size:14px;margin:0;padding:0;}';
        $html .= '.sheet{max-width:920px;margin:22px auto;padding:0 18px;}';
        $html .= '.topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 0 12px;border-bottom:2px solid var(--line);}';
        $html .= '.brand{display:flex;align-items:center;gap:12px;min-width:0;}';
        $html .= '.logo{width:64px;height:64px;border:1px solid var(--line);border-radius:10px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#fff;}';
        $html .= '.logo img{max-width:100%;max-height:100%;padding:10px;object-fit:contain;}';
        $html .= '.brand h1{font-size:16px;margin:0;font-weight:900;line-height:1.1;}';
        $html .= '.brand .web{color:var(--muted);font-weight:600;margin-top:2px;}';
        $html .= '.meta{text-align:right;}';
        $html .= '.meta .no{font-weight:900;font-size:15px;}';
        $html .= '.meta .sub{color:var(--muted);font-weight:600;margin-top:3px;}';
        $html .= '.summary{margin-top:14px;background:var(--soft);border:1px solid var(--line);border-radius:12px;padding:12px 14px;}';
        $html .= '.summary-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 16px;}';
        $html .= '.kv{display:flex;gap:8px;}';
        $html .= '.kv .k{min-width:120px;color:var(--muted);font-weight:800;text-transform:uppercase;letter-spacing:.06em;font-size:11px;}';
        $html .= '.kv .v{font-weight:700;color:var(--ink);}';
        $html .= '.thread{margin-top:14px;}';
        $html .= '.entry{border:1px solid var(--line);border-radius:12px;padding:12px 14px;margin-bottom:10px;}';
        $html .= '.entry.staff{border-color:#fed7aa;background:#fff7ed;}';
        $html .= '.entry.user{border-color:#bfdbfe;background:#eff6ff;}';
        $html .= '.entry-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px;}';
        $html .= '.who{font-weight:900;}';
        $html .= '.when{color:var(--muted);font-weight:700;font-size:12px;white-space:nowrap;}';
        $html .= '.body{white-space:pre-wrap;word-break:break-word;line-height:1.35;}';
        $html .= '.body img{max-width:100%;height:auto;display:block;}';
        $html .= '.footer{margin-top:10px;color:var(--muted);font-weight:700;font-size:12px;text-align:center;}';
        $html .= '.closed-note{margin-top:12px;border:1px solid var(--line);border-radius:12px;background:#f8fafc;padding:10px 12px;}';
        $html .= '.closed-note .k{font-weight:900;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:4px;}';
        $html .= '.closed-note .v{white-space:pre-wrap;word-break:break-word;}';
        $html .= '.sig-box{margin-top:30px;margin-left:auto;width:280px;page-break-inside:avoid;}';
        $html .= '.sig-title{font-size:11px;text-transform:uppercase;letter-spacing:.05em;font-weight:800;color:var(--muted);text-align:center;border-bottom:1px solid var(--line);padding-bottom:6px;margin-bottom:8px;}';
        $html .= '.sig-body{text-align:center;padding:4px;}';
        $html .= '.sig-img{display:inline-block;max-width:100%;max-height:120px;width:auto;height:auto;filter:contrast(1.1) grayscale(0.5);}';
        $html .= '@page{margin:18mm;}';
        $html .= '</style></head><body><div class="sheet">';

        // Topbar
        $html .= '<div class="topbar"><div class="brand">';
        if ($logoUrl !== '') {
            $html .= '<div class="logo"><img src="' . $esc($logoUrl) . '" alt="' . $esc($companyName) . '"></div>';
        }
        $html .= '<div style="min-width:0;"><h1>' . $esc($companyName) . '</h1><div class="web">' . $esc($companyWebsite) . '</div></div>';
        $html .= '</div>';
        $html .= '<div class="meta">';
        $html .= '<div class="no">Ticket ' . $esc($t['ticket_number'] ?? ('#' . $tid)) . '</div>';
        $html .= '<div class="sub">' . $esc($t['subject'] ?? '') . '</div>';
        $html .= '<div class="sub">Generado: ' . date('d/m/Y H:i') . '</div>';
        $html .= '</div></div>';

        // Resumen
        $html .= '<div class="summary"><div class="summary-grid">';
        $html .= '<div class="kv"><div class="k">Cliente</div><div class="v">' . $esc($userName) . ' (' . $esc($t['user_email'] ?? '') . ')</div></div>';
        $html .= '<div class="kv"><div class="k">Departamento</div><div class="v">' . $esc($t['dept_name'] ?? '') . '</div></div>';
        $html .= '<div class="kv"><div class="k">Estado</div><div class="v">' . $esc($t['status_name'] ?? '') . '</div></div>';
        $html .= '<div class="kv"><div class="k">Prioridad</div><div class="v">' . $esc($t['priority_name'] ?? '') . '</div></div>';
        $html .= '<div class="kv"><div class="k">Asignado</div><div class="v">' . $esc($staffName) . '</div></div>';
        $html .= '<div class="kv"><div class="k">Creado</div><div class="v">' . (!empty($t['created']) ? $esc(date('d/m/Y H:i', strtotime((string)$t['created']))) : '—') . '</div></div>';
        $html .= '<div class="kv"><div class="k">Cerrado</div><div class="v">' . (!empty($t['closed']) ? $esc(date('d/m/Y H:i', strtotime((string)$t['closed']))) : '—') . '</div></div>';
        $html .= '</div></div>';

        // Hilo
        $html .= '<div class="thread">';
        if (empty($entries)) {
            $html .= '<div class="entry"><div class="who">Sin mensajes</div><div class="body">Aún no hay mensajes en el hilo.</div></div>';
        } else {
            foreach ($entries as $e) {
                $isStaff = !empty($e['staff_id']);
                $author  = $isStaff
                    ? (trim((string)($e['staff_first'] ?? '') . ' ' . (string)($e['staff_last'] ?? '')) ?: 'Agente')
                    : (trim((string)($e['user_first'] ?? '') . ' ' . (string)($e['user_last'] ?? '')) ?: 'Usuario');
                $cls  = $isStaff ? 'staff' : 'user';
                $when = !empty($e['created']) ? date('d/m/Y H:i', strtotime((string)$e['created'])) : '';
                $html .= '<div class="entry ' . $esc($cls) . '">';
                $html .= '<div class="entry-head"><div><span class="who">' . $esc($author) . '</span></div><div class="when">' . $esc($when) . '</div></div>';
                $html .= '<div class="body">' . (is_callable($sanitize) ? $sanitize((string)($e['body'] ?? '')) : nl2br($esc((string)($e['body'] ?? '')))) . '</div>';
                $html .= '</div>';
            }
        }
        $html .= '</div>';

        // Motivo de cierre
        if (!empty($t['close_message'])) {
            $html .= '<div class="closed-note"><div class="k">Motivo de cierre</div><div class="v">' . nl2br($esc((string)$t['close_message'])) . '</div></div>';
        }

        // Firma del cliente
        if ($ticketClientSignatureUrl !== '') {
            $html .= '<div class="sig-box"><div class="sig-title">Firma del cliente</div><div class="sig-body"><img src="' . $esc($ticketClientSignatureUrl) . '" alt="Firma del cliente" class="sig-img"></div></div>';
        }

        $html .= '<div class="footer">' . $esc($companyName) . ' · ' . $esc($companyWebsite) . '</div>';
        $html .= '</div></body></html>';

        return $html;
    }
}
