<?php
require 'config.php';
require 'includes/helpers.php';
$res = $mysqli->query("SELECT * FROM email_accounts");
if ($res) {
    while($row = $res->fetch_assoc()) {
        $row['smtp_pass'] = '***'; // censor
        echo json_encode($row) . "\n";
    }
}
$resQueue = $mysqli->query("SELECT * FROM email_queue ORDER BY id DESC LIMIT 5");
if ($resQueue) {
    echo "\nLatest Email Queues:\n";
    while($row = $resQueue->fetch_assoc()) echo json_encode($row) . "\n";
}
