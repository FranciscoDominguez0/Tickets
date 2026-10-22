<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/Notifications/NotificationPayload.php';
require_once __DIR__ . '/../includes/Notifications/NotificationStrategyInterface.php';
require_once __DIR__ . '/../includes/Notifications/WebPushStrategy.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

echo "====================================================\n";
echo " DIAGNÓSTICO DE NOTIFICACIONES PUSH (WEB PUSH PWA) \n";
echo "====================================================\n\n";

// 1. Verificar Composer WebPush
echo "1. Comprobando librería Minishlink\\WebPush... ";
if (!class_exists('\Minishlink\WebPush\WebPush')) {
    echo "NO INSTALADA ❌\n";
    echo "--> Solución: Ejecuta en la terminal de tu servidor:\n";
    echo "    docker exec -it php_app composer install --no-dev --optimize-autoloader\n";
    exit(1);
}
echo "INSTALADA CORRECTAMENTE ✅\n\n";

// 2. Verificar claves VAPID
echo "2. Comprobando claves VAPID en config.php...\n";
$pub = defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : '';
$priv = defined('VAPID_PRIVATE_KEY') ? VAPID_PRIVATE_KEY : '';
echo " - VAPID Public:  " . (empty($pub) ? "VACÍA ❌" : substr($pub, 0, 20) . "... (" . strlen($pub) . " chars) ✅") . "\n";
echo " - VAPID Private: " . (empty($priv) ? "VACÍA ❌" : substr($priv, 0, 10) . "... (" . strlen($priv) . " chars) ✅") . "\n\n";

if (empty($pub) || empty($priv)) {
    echo "ERROR: Las claves VAPID no están configuradas.\n";
    exit(1);
}

// 3. Verificar registros en base de datos
echo "3. Comprobando dispositivos suscritos en 'push_subscriptions'...\n";
global $mysqli;
$res = $mysqli->query("SELECT id, empresa_id, user_type, user_id, endpoint, created_at, updated_at FROM push_subscriptions");
$count = $res ? $res->num_rows : 0;
echo "Dispositivos registrados encontrados: $count\n";

if ($count === 0) {
    echo "\n⚠️ NO HAY DISPOSITIVOS REGISTRADOS EN LA BASE DE DATOS.\n";
    echo "Por esta razón no llega la notificación cuando la app está cerrada:\n";
    echo "El servidor no tiene ningún endpoint de celular a dónde enviar.\n\n";
    echo "--> Para registrar tu celular:\n";
    echo "1. Entra desde tu teléfono a: " . (defined('APP_URL') ? APP_URL : 'tu_dominio') . "/upload/scp/index.php\n";
    echo "2. Si sale el aviso de notificaciones, dale a PERMITIR.\n";
    echo "3. Vuelve a ejecutar este script para comprobar que aparezca tu registro.\n";
    exit(1);
}

$subs = [];
while ($row = $res->fetch_assoc()) {
    $subs[] = $row;
    echo " - ID: {$row['id']} | Rol: {$row['user_type']} | User ID: {$row['user_id']} | Registrado: {$row['updated_at']}\n";
    echo "   Endpoint: " . substr($row['endpoint'], 0, 50) . "...\n";
}

echo "\n4. Enviando notificación de prueba a todos los dispositivos registrados...\n";
$vapid = [
    'subject'    => defined('VAPID_SUBJECT') ? VAPID_SUBJECT : 'mailto:admin@localhost.com',
    'publicKey'  => $pub,
    'privateKey' => $priv,
];

try {
    $webPush = new WebPush(['VAPID' => $vapid]);
    $webPush->setReuseVAPIDHeaders(true);

    $appUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
    $testPayload = json_encode([
        'title'      => 'Prueba Sistema de Tickets',
        'body'       => '¡Notificación Push directa recibida con la app cerrada! (' . date('H:i:s') . ')',
        'icon'       => $appUrl . '/publico/img/pwa/icon-192.png',
        'badge'      => $appUrl . '/publico/img/pwa/icon-192.png',
        'url'        => $appUrl . '/upload/scp/tickets.php',
        'type'       => 'test',
        'related_id' => 0,
        'timestamp'  => time() * 1000,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    foreach ($subs as $s) {
        $sub = Subscription::create([
            'endpoint'        => $s['endpoint'],
            'publicKey'       => $s['public_key'] ?? '',
            'authToken'       => $s['auth_token'] ?? '',
            'contentEncoding' => !empty($s['content_encoding']) ? $s['content_encoding'] : 'aes128gcm',
        ]);
        $webPush->queueNotification($sub, $testPayload);
    }

    echo "Despachando a los servidores push (FCM / Mozilla / Apple)...\n";
    $reports = $webPush->flush();

    foreach ($reports as $report) {
        $endpoint = substr($report->getEndpoint(), 0, 50) . '...';
        if ($report->isSuccess()) {
            echo " ✅ ÉXITO enviado a: $endpoint\n";
            echo "    (¡Revisa tu celular! Debe sonar/vibrar la notificación)\n";
        } else {
            echo " ❌ FALLO en: $endpoint\n";
            echo "    Razón: " . $report->getReason() . "\n";
            if ($report->isSubscriptionExpired()) {
                echo "    Aviso: Esta suscripción ya caducó o el usuario la eliminó.\n";
            }
        }
    }
} catch (\Throwable $e) {
    echo "\nEXCEPCIÓN al enviar: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\nDiagnóstico finalizado.\n";
