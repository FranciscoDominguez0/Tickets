<?php
namespace Notifications;

/**
 * NotificationPayload
 * 
 * Objeto de transferencia de datos (DTO) que encapsula todos los datos
 * necesarios para cualquier estrategia de notificación.
 */
class NotificationPayload
{
    public int $empresaId;
    public string $recipientType; // 'staff' | 'user'
    public int $recipientId;
    public string $title;
    public string $message;
    public string $type;          // 'ticket_created', 'ticket_assigned', 'ticket_reply', etc.
    public int $relatedId;        // ID del ticket u objeto relacionado
    public string $url;           // URL destino al hacer click
    public array $extra;          // Metadatos adicionales opcionales

    public function __construct(
        int $empresaId,
        string $recipientType,
        int $recipientId,
        string $title,
        string $message,
        string $type = 'general',
        int $relatedId = 0,
        string $url = '',
        array $extra = []
    ) {
        $this->empresaId = $empresaId > 0 ? $empresaId : 1;
        $this->recipientType = in_array($recipientType, ['staff', 'user'], true) ? $recipientType : 'staff';
        $this->recipientId = $recipientId;
        $this->title = trim($title) !== '' ? trim($title) : 'Notificación';
        $this->message = trim($message);
        $this->type = trim($type) !== '' ? trim($type) : 'general';
        $this->relatedId = $relatedId;
        $this->url = $url;
        $this->extra = $extra;
    }

    /**
     * Convierte el payload a array para JSON / Web Push
     */
    public function toArray(): array
    {
        return [
            'empresa_id'     => $this->empresaId,
            'recipient_type' => $this->recipientType,
            'recipient_id'   => $this->recipientId,
            'title'          => $this->title,
            'message'        => $this->message,
            'type'           => $this->type,
            'related_id'     => $this->relatedId,
            'url'            => $this->url,
            'extra'          => $this->extra,
        ];
    }
}
