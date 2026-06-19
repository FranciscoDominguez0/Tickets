<?php
require __DIR__ . '/config.php';

$tid = isset($_GET['id']) ? (int)$_GET['id'] : 422;

echo "<pre>";

// Verificar ticket
$r = $mysqli->query("SELECT id, status_id, closed FROM tickets WHERE id=$tid");
$t = $r->fetch_assoc();
echo "Ticket $tid: status_id=" . $t['status_id'] . " closed=" . $t['closed'] . "\n";

// Obtener ticket status name
$rs = $mysqli->query("SELECT name FROM ticket_status WHERE id=" . $t['status_id']);
$sn = $rs->fetch_assoc();
echo "Status name: " . $sn['name'] . "\n";

// Obtener thread
$r2 = $mysqli->query("SELECT id FROM threads WHERE ticket_id=$tid LIMIT 1");
$th = $r2->fetch_assoc();
$thread_id = (int)($th['id'] ?? 0);
echo "thread_id=$thread_id\n";

// Ver attachments
$r3 = $mysqli->query("SELECT a.id, a.original_filename, a.path FROM attachments a JOIN thread_entries te ON te.id=a.thread_entry_id WHERE te.thread_id=$thread_id");
echo "Attachments:\n";
while ($row = $r3->fetch_assoc()) {
    $path = $row['path'];
    // Verificar si el archivo existe
    $bases = [
        defined('ATTACHMENTS_DIR') ? rtrim(ATTACHMENTS_DIR, '/\\') . '/' . ltrim(str_replace('uploads/attachments/', '', $path), '/\\') : '',
        dirname(__DIR__,0) . '/' . ltrim($path, '/\\'),
        __DIR__ . '/' . ltrim($path, '/\\'),
        $path,
    ];
    $exists = 'NO ENCONTRADO';
    foreach ($bases as $b) {
        if ($b !== '' && is_file($b)) {
            $exists = 'EXISTE: ' . $b;
            break;
        }
    }
    echo "  id=" . $row['id'] . " file=" . $row['original_filename'] . " path=" . $path . "\n    -> " . $exists . "\n";
}

// Test inline URL
echo "\nURL de preview usada: tickets.php?id=$tid&download=XX&inline=1&v=2\n";
echo "Verificar manualmente si al acceder a esa URL con sesión activa devuelve el archivo o redirige al login\n";

echo "</pre>";
