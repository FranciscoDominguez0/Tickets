<?php
$dirs = [
    'upload',
    'upload/css',
    'upload/js'
];

$gray_colors = ['18181b', '111113', '1a1a1a', '1e1e1e', '111', '141414', '09090b', '27272a', '1f1f23', '111111', '222', '222222', '252525', '2a2a2a'];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    
    // We only process .php and .css files in these specific folders (non-recursive to avoid scp/)
    $files = array_merge(glob($dir . '/*.php') ?: [], glob($dir . '/*.css') ?: []);
    
    foreach ($files as $file) {
        // Skip some obvious files we don't want to break
        if (strpos($file, 'config.php') !== false) continue;
        
        $content = file_get_contents($file);
        $original = $content;
        
        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            // Only replace inside style tags, CSS properties or CSS variables
            if (strpos($line, ':') !== false && (strpos($line, ';') !== false || strpos($line, '{') !== false || strpos($line, '}') !== false)) {
                
                if (strpos($line, '<?php') !== false || strpos($line, '?>') !== false) {
                    continue; // Skip lines with PHP tags to avoid code corruption
                }
                
                // Exclude borders
                if (preg_match('/border(-color|-top-color|-bottom-color|-left-color|-right-color)?\s*:/i', $line)) {
                    continue; 
                }

                foreach ($gray_colors as $color) {
                    $lines[$i] = preg_replace('/#' . $color . '\b/i', '#000000', $lines[$i]);
                }
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
