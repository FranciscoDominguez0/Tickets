<?php
require 'config.php';
$res = $mysqli->query('SELECT id, recipient_email, status, attempts, created_at, updated_at FROM email_queue ORDER BY id DESC LIMIT 5');
while($r = $res->fetch_assoc()) {
    print_r($r);
}
