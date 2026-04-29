<?php
include 'config.php';
$res = $mysqli->query('SHOW COLUMNS FROM ticket_reports');
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . ' (' . $row['Type'] . ")\n";
}
