<?php
require_once 'config.php';
$r = $mysqli->query('SHOW COLUMNS FROM tickets LIKE "client_signature"');
echo 'client_signature: ' . ($r && $r->num_rows > 0 ? 'YES' : 'NO') . PHP_EOL;
$r2 = $mysqli->query('SHOW COLUMNS FROM tickets LIKE "close_message"');
echo 'close_message: ' . ($r2 && $r2->num_rows > 0 ? 'YES' : 'NO') . PHP_EOL;
$r3 = $mysqli->query('SHOW COLUMNS FROM tickets LIKE "closed_at"');
echo 'closed_at: ' . ($r3 && $r3->num_rows > 0 ? 'YES' : 'NO') . PHP_EOL;