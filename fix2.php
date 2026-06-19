<?php
$f = 'upload/scp/reporte_costos.php';
$c = file_get_contents($f);
$c = preg_replace('/background(-color)?:\s*#(18181b|111113|1a1a1a|1e1e1e|111|141414|09090b|27272a|1f1f23)\s*(!important)?;/i', 'background: #000000 $3;', $c);
file_put_contents($f, $c);
echo "Fixed";
