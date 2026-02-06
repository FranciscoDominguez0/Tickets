<?php
require_once 'config.php';

$sql = "INSERT INTO help_topics (name, description, dept_id, is_active, created) VALUES
('Problemas técnicos', 'Errores, fallos y soporte técnico', 1, 1, '2026-01-03 09:15:00'),
('Consulta de ventas', 'Cotizaciones y preguntas comerciales', 2, 1, '2026-01-07 10:30:00'),
('Pagos y facturas', 'Pagos, facturación y cobros', 3, 1, '2026-01-12 14:45:00'),
('Recursos Humanos', 'Consultas internas de RRHH', 4, 1, '2026-01-18 08:20:00'),
('Otro', 'Cualquier otra solicitud', 5, 1, '2026-01-25 16:00:00')
ON DUPLICATE KEY UPDATE name = name;";

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    echo "Temas de ayuda insertados correctamente.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
