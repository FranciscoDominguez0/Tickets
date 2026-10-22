<?php
namespace Notifications;

/**
 * NotificationStrategyInterface
 * 
 * Contrato que define el comportamiento que debe implementar
 * cualquier canal o medio de notificación en el sistema (Patrón Strategy).
 */
interface NotificationStrategyInterface
{
    /**
     * Envía la notificación utilizando la estrategia concreta.
     * 
     * @param NotificationPayload $payload
     * @return bool True si se procesó correctamente, false en caso contrario
     */
    public function send(NotificationPayload $payload): bool;

    /**
     * Retorna el identificador único de la estrategia (ej. 'database', 'webpush', 'email').
     * 
     * @return string
     */
    public function getName(): string;
}
