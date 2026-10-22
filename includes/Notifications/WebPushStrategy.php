<?php
namespace Notifications;

use mysqli;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Throwable;

/**
 * WebPushStrategy
 * 
 * Estrategia de notificación para dispositivos móviles y escritorio
 * mediante el estándar Web Push (VAPID / Service Worker).
 * Llega a las notificaciones nativas del celular incluso cuando la PWA
 * está cerrada o el teléfono bloqueado.
 */
class WebPushStrategy implements NotificationStrategyInterface
{
    private ?mysqli $db;
    private array $vapidConfig;

    public function __construct(?mysqli $db = null, ?array $vapidConfig = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            global $mysqli;
            $this->db = $mysqli ?? null;
        }

        if ($vapidConfig !== null) {
            $this->vapidConfig = $vapidConfig;
        } else {
            $this->vapidConfig = [
                'subject'    => defined('VAPID_SUBJECT') ? VAPID_SUBJECT : 'mailto:admin@localhost.com',
                'publicKey'  => defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : '',
                'privateKey' => defined('VAPID_PRIVATE_KEY') ? VAPID_PRIVATE_KEY : '',
            ];
        }
    }

    public function getName(): string
    {
        return 'webpush';
    }

    public function send(NotificationPayload $payload): bool
    {
        if (!$this->db) {
            return false;
        }

        if (empty($this->vapidConfig['publicKey']) || empty($this->vapidConfig['privateKey'])) {
            return false;
        }

        if (!class_exists('\\Minishlink\\WebPush\\WebPush')) {
            return false;
        }

        if (function_exists('ensurePushSubscriptionsTable')) {
            ensurePushSubscriptionsTable();
        }

        if (function_exists('dbTableExists') && !dbTableExists('push_subscriptions')) {
            return false;
        }

        try {
            // 1. Obtener todas las suscripciones push activas para este usuario
            $stmt = $this->db->prepare(
                "SELECT id, endpoint, public_key, auth_token, content_encoding " .
                "FROM push_subscriptions " .
                "WHERE empresa_id = ? AND user_type = ? AND user_id = ?"
            );
            if (!$stmt) {
                return false;
            }

            $eid = $payload->empresaId;
            $uType = $payload->recipientType;
            $uId = $payload->recipientId;
            $stmt->bind_param('isi', $eid, $uType, $uId);
            $stmt->execute();
            $res = $stmt->get_result();

            $subs = [];
            while ($row = $res->fetch_assoc()) {
                $subs[] = $row;
            }

            if (empty($subs)) {
                return true; // No hay dispositivos suscritos, no es un fallo
            }

            // 2. Preparar payload visual para el Service Worker del celular
            $appUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
            $iconUrl = $appUrl . '/publico/img/pwa/icon-192.png';
            $badgeUrl = $appUrl . '/publico/img/pwa/icon-192.png';

            // Determinar la URL de destino al hacer click en la notificación del celular
            $targetUrl = $payload->url;
            if (empty($targetUrl)) {
                if ($payload->recipientType === 'staff') {
                    $path = $payload->relatedId > 0
                        ? 'scp/tickets.php?id=' . $payload->relatedId
                        : 'scp/index.php';
                } else {
                    $path = $payload->relatedId > 0
                        ? 'tickets.php?id=' . $payload->relatedId
                        : 'tickets.php';
                }
                $targetUrl = $path;
            }

            if (!preg_match('/^https?:\/\//i', $targetUrl)) {
                $cleanUrl = ltrim($targetUrl, '/');
                $baseAppUrl = rtrim($appUrl, '/');
                if (str_ends_with($baseAppUrl, '/upload')) {
                    if (stripos($cleanUrl, 'upload/') === 0) {
                        $cleanUrl = substr($cleanUrl, 7);
                    }
                } else {
                    if (stripos($cleanUrl, 'upload/') !== 0) {
                        $cleanUrl = 'upload/' . $cleanUrl;
                    }
                }
                $targetUrl = $baseAppUrl . '/' . $cleanUrl;
            }

            $pushData = [
                'title'      => 'Tickets - ' . $payload->title,
                'body'       => $payload->message,
                'icon'       => $iconUrl,
                'badge'      => $badgeUrl,
                'url'        => $targetUrl,
                'type'       => $payload->type,
                'related_id' => $payload->relatedId,
                'timestamp'  => time() * 1000,
            ];

            $jsonPayload = json_encode($pushData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // 3. Inicializar cliente WebPush
            $webPush = new WebPush([
                'VAPID' => $this->vapidConfig,
            ]);
            $webPush->setReuseVAPIDHeaders(true);

            $subIdMap = [];
            foreach ($subs as $s) {
                try {
                    $subscription = Subscription::create([
                        'endpoint'        => $s['endpoint'],
                        'publicKey'       => $s['public_key'],
                        'authToken'       => $s['auth_token'],
                        'contentEncoding' => !empty($s['content_encoding']) ? $s['content_encoding'] : 'aes128gcm',
                    ]);
                    $webPush->queueNotification($subscription, $jsonPayload);
                    $subIdMap[$s['endpoint']] = (int) $s['id'];
                } catch (Throwable $e) {
                    continue;
                }
            }

            // 4. Enviar a los servicios push (FCM / Apple / Mozilla)
            $reports = $webPush->flush();
            $expiredIds = [];

            foreach ($reports as $report) {
                $endpoint = $report->getEndpoint();
                if ($report->isSubscriptionExpired()) {
                    if (isset($subIdMap[$endpoint])) {
                        $expiredIds[] = $subIdMap[$endpoint];
                    }
                }
            }

            // 5. Limpieza automática de suscripciones vencidas o desinstaladas
            if (!empty($expiredIds)) {
                $inList = implode(',', array_map('intval', $expiredIds));
                $this->db->query("DELETE FROM push_subscriptions WHERE id IN ($inList)");
            }

            return true;
        } catch (Throwable $e) {
            // Falla silenciosa: no interrumpir ejecución ni llenar archivos de logs
            return false;
        }
    }
}
