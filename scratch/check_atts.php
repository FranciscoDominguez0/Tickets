<?php
require '../config.php';
$res = $mysqli->query("DESCRIBE attachments");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
