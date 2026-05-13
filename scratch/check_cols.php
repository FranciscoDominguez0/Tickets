<?php
require 'config.php';
$res = $mysqli->query("DESCRIBE thread_entries");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
