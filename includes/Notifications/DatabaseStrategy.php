<?php
namespace Notifications;

use mysqli;
use Throwable;

/**
 * DatabaseStrategy
 * 
 * Estrategia de notificación que persiste en la Base de Datos
 * para alimentar la campana del header, el contador de no leídos
 * y la conexión SSE / Polling en tiempo real.
 */
class DatabaseStrategy implements NotificationStrategyInterface
{
    private ?mysqli $db;

    public function __construct(?mysqli $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            global $mysqli;
            $this->db = $mysqli ?? null;
        }
    }

    public function getName(): string
    {
        return 'database';
    }

    public function send(NotificationPayload $payload): bool
    {
        if (!$this->db) {
            return false;
        }

        try {
            if ($payload->recipientType === 'staff') {
                return $this->sendToStaff($payload);
            } else {
                return $this->sendToUser($payload);
            }
        } catch (Throwable $e) {
            if (class_exists('\\Logger') && method_exists('\\Logger', 'error')) {
                \Logger::error('Error en DatabaseStrategy: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            } else {
                @error_log('Error en DatabaseStrategy: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
            return false;
        }
    }

    private function sendToStaff(NotificationPayload $p): bool
    {
        if (function_exists('dbTableExists') && !dbTableExists('notifications')) {
            return false;
        }

        $hasEmpresa = function_exists('dbColumnExists') ? dbColumnExists('notifications', 'empresa_id') : true;

        if ($hasEmpresa) {
            $stmt = $this->db->prepare(
                "INSERT INTO notifications (empresa_id, staff_id, message, type, related_id, is_read, created_at) " .
                "VALUES (?, ?, ?, ?, ?, 0, NOW())"
            );
            if (!$stmt) return false;
            $eid = $p->empresaId;
            $sid = $p->recipientId;
            $msg = $p->message;
            $type = $p->type;
            $rel = $p->relatedId;
            $stmt->bind_param('iissi', $eid, $sid, $msg, $type, $rel);
            return $stmt->execute();
        } else {
            $stmt = $this->db->prepare(
                "INSERT INTO notifications (staff_id, message, type, related_id, is_read, created_at) " .
                "VALUES (?, ?, ?, ?, 0, NOW())"
            );
            if (!$stmt) return false;
            $sid = $p->recipientId;
            $msg = $p->message;
            $type = $p->type;
            $rel = $p->relatedId;
            $stmt->bind_param('issi', $sid, $msg, $type, $rel);
            return $stmt->execute();
        }
    }

    private function sendToUser(NotificationPayload $p): bool
    {
        if (function_exists('dbTableExists') && !dbTableExists('user_notifications')) {
            return false;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO user_notifications (empresa_id, user_id, type, message, ticket_id, is_read, created_at) " .
            "VALUES (?, ?, ?, ?, ?, 0, NOW())"
        );
        if (!$stmt) return false;

        $eid = $p->empresaId;
        $uid = $p->recipientId;
        $type = $p->type;
        $msg = $p->message;
        $tid = $p->relatedId;
        $stmt->bind_param('iissi', $eid, $uid, $type, $msg, $tid);
        return $stmt->execute();
    }
}
