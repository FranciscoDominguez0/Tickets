<?php
$m = new mysqli('localhost', 'root', '12345678', 'tickets_db');
foreach(['threads', 'thread_entries', 'ticket_links'] as $t) {
    echo "--- $t ---\n";
    $res = $m->query("DESCRIBE $t");
    if ($res) while($r = $res->fetch_assoc()) printf("%-20s %-20s %-5s\n", $r['Field'], $r['Type'], $r['Null']);
    else echo "Table $t not found or error: " . $m->error . "\n";
}
?>
