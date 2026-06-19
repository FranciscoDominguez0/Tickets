<?php
$dirs = [
    'upload/scp',
    'upload/scp/superadmin',
    'upload/scp/modules',
    'upload/scp/layout',
    'upload/scp/modules/tickets'
];

$gray_colors = ['18181b', '111113', '1a1a1a', '1e1e1e', '111', '141414', '09090b', '27272a', '1f1f23', '111111', '222', '222222', '252525', '2a2a2a'];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    
    $files = glob($dir . '/*.php');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $original = $content;
        
        // We only want to replace grays inside <style> blocks or CSS variables.
        // A simple heuristic: replace if the line contains a gray color AND (contains ':' and ';')
        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            // Check if it's likely a CSS line (contains colon and semicolon, or curly braces)
            if (strpos($line, ':') !== false && (strpos($line, ';') !== false || strpos($line, '{') !== false || strpos($line, '}') !== false)) {
                // Ignore PHP strings or tags just to be safe
                if (strpos($line, '<?php') !== false || strpos($line, '?>') !== false) {
                    continue; // Skip lines with PHP tags
                }
                
                // Exclude border colors so borders are still visible
                if (preg_match('/border(-color|-top-color|-bottom-color|-left-color|-right-color)?\s*:/i', $line)) {
                    continue; 
                }

                foreach ($gray_colors as $color) {
                    // Match the color with word boundary
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
