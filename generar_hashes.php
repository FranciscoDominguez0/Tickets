<?php
/**
 * Script para generar hashes bcrypt de contraseñas
 * Ejecuta: php generar_hashes.php
 */

echo "========================================\n";
echo "GENERADOR DE HASHES BCRYPT\n";
echo "========================================\n\n";

// Contraseñas a hashear
$passwords = [
    'cliente123' => 'Cliente',
    'admin123' => 'Agente Admin'
];

foreach ($passwords as $password => $tipo) {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    echo "Tipo: {$tipo}\n";
    echo "Contraseña: {$password}\n";
    echo "Hash: {$hash}\n";
    echo "----------------------------------------\n\n";
}

echo "\n========================================\n";
echo "SQL GENERADO:\n";
echo "========================================\n\n";

$hash_cliente = password_hash('cliente123', PASSWORD_BCRYPT);
$hash_admin = password_hash('admin123', PASSWORD_BCRYPT);

echo "-- CLIENTE\n";
echo "INSERT INTO users (email, password, firstname, lastname, company, status, created) \n";
echo "VALUES (\n";
echo "  'cliente@example.com',\n";
echo "  '{$hash_cliente}',\n";
echo "  'Juan',\n";
echo "  'Cliente',\n";
echo "  'Acme Corp',\n";
echo "  'active',\n";
echo "  NOW()\n";
echo ");\n\n";

echo "-- AGENTE\n";
echo "INSERT INTO staff (username, password, email, firstname, lastname, dept_id, role, is_active, created) \n";
echo "VALUES (\n";
echo "  'admin',\n";
echo "  '{$hash_admin}',\n";
echo "  'admin@company.com',\n";
echo "  'Admin',\n";
echo "  'System',\n";
echo "  1,\n";
echo "  'admin',\n";
echo "  1,\n";
echo "  NOW()\n";
echo ");\n";
