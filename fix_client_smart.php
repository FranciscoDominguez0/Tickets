<?php
$dirs = [
    'upload',
    'upload/css',
    'upload/js'
];

$gray_colors = ['18181b', '111113', '1a1a1a', '1e1e1e', '111', '141414', '09090b', '27272a', '1f1f23', '111111', '222', '222222', '252525', '2a2a2a'];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    
    $files = array_merge(glob($dir . '/*.php') ?: [], glob($dir . '/*.css') ?: []);
    
    foreach ($files as $file) {
        if (strpos($file, 'config.php') !== false) continue;
        if (strpos($file, 'fix') !== false) continue;
        
        $content = file_get_contents($file);
        $original = $content;
        
        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            if (strpos($line, '<?php') !== false || strpos($line, '?>') !== false) {
                continue; // Skip lines with PHP code
            }
            
            // For each line, we look for background or background-color followed by a gray color
            foreach ($gray_colors as $color) {
                // This regex looks for 'background:' or 'background-color:' followed by any characters except ';' or 'border'
                // and then matches the specific hex code, replacing only the hex code.
                $lines[$i] = preg_replace('/(background(?:-color)?\s*:[^;}]*?)#' . $color . '\b/i', '$1#000000', $lines[$i]);
            }
        }
        
        $new_content = implode("\n", $lines);
        if ($new_content !== $original) {
            file_put_contents($file, $new_content);
            echo "Fixed $file\n";
        }
    }
}
echo "Done.";
