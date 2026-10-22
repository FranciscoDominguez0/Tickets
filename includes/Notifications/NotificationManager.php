<?php
namespace Notifications;

use mysqli;

/**
 * NotificationManager (Contexto en el Patrón Strategy)
 * 
 * Orquestador central de notificaciones. Permite registrar diferentes
 * estrategias (Database / Campana, WebPush celular, Email, etc.) y ejecutarlas
 * de forma unificada o selectiva.
 */
class NotificationManager
{
    private static ?NotificationManager $instance = null;

    /**
     * @var NotificationStrategyInterface[]
     */
    private array $strategies = [];

    public function __construct(?mysqli $db = null)
    {
        // Registrar estrategias por defecto
        $this->addStrategy(new DatabaseStrategy($db));
        $this->addStrategy(new WebPushStrategy($db));
    }

    /**
     * Obtiene la instancia singleton del administrador
     */
    public static function getInstance(?mysqli $db = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($db);
        }
        return self::$instance;
    }

    /**
     * Registra una nueva estrategia de notificación
     */
    public function addStrategy(NotificationStrategyInterface $strategy): self
    {
        $this->strategies[$strategy->getName()] = $strategy;
        return $this;
    }

    /**
     * Remueve una estrategia registrada por su nombre
     */
    public function removeStrategy(string $name): self
    {
        unset($this->strategies[$name]);
        return $this;
    }

    /**
     * Obtiene una estrategia por nombre
     */
    public function getStrategy(string $name): ?NotificationStrategyInterface
    {
        return $this->strategies[$name] ?? null;
    }

    /**
     * Envía una notificación usando las estrategias registradas
     * 
     * @param NotificationPayload $payload Datos de la notificación
     * @param string[]|null $onlyStrategies Lista de estrategias específicas a usar (null = todas)
     * @return array<string, bool> Resultado por cada estrategia ['database' => true, 'webpush' => true]
     */
    public function send(NotificationPayload $payload, ?array $onlyStrategies = null): array
    {
        $results = [];

        foreach ($this->strategies as $name => $strategy) {
            if ($onlyStrategies !== null && !in_array($name, $onlyStrategies, true)) {
                continue;
            }

            $results[$name] = $strategy->send($payload);
        }

        return $results;
    }

    /**
     * Envía la misma notificación a una lista de destinatarios
     * 
     * @param int[] $recipientIds Lista de IDs de usuarios o staff
     * @param NotificationPayload $basePayload
     * @param string[]|null $onlyStrategies
     * @return array
     */
    public function sendToMultiple(array $recipientIds, NotificationPayload $basePayload, ?array $onlyStrategies = null): array
    {
        $recipientIds = array_unique(array_filter(array_map('intval', $recipientIds)));
        $out = [];

        foreach ($recipientIds as $rid) {
            if ($rid <= 0) continue;

            $payload = new NotificationPayload(
                $basePayload->empresaId,
                $basePayload->recipientType,
                $rid,
                $basePayload->title,
                $basePayload->message,
                $basePayload->type,
                $basePayload->relatedId,
                $basePayload->url,
                $basePayload->extra
            );

            $out[$rid] = $this->send($payload, $onlyStrategies);
        }

        return $out;
    }

    /**
     * Atajo estático para despachar una notificación rápidamente
     */
    public static function dispatch(
        int $empresaId,
        string $recipientType,
        int $recipientId,
        string $title,
        string $message,
        string $type = 'general',
        int $relatedId = 0,
        string $url = '',
        ?array $onlyStrategies = null
    ): array {
        $payload = new NotificationPayload(
            $empresaId,
            $recipientType,
            $recipientId,
            $title,
            $message,
            $type,
            $relatedId,
            $url
        );

        return self::getInstance()->send($payload, $onlyStrategies);
    }
}
