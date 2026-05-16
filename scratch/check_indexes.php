<?php
$m = new mysqli('localhost', 'root', '12345678', 'tickets_db');
$res = $m->query("SHOW INDEX FROM ticket_links");
while($r = $res->fetch_assoc()) {
    printf("%-20s %-20s %-20s\n", $r['Key_name'], $r['Column_name'], $r['Non_unique']);
}
?>
