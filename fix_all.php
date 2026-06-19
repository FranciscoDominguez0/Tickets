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
        
        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            // Only look at lines inside dark mode overrides or style blocks
            // Actually, any background declaration matching the gray colors
            if (preg_match('/background(-color)?\s*:/i', $line)) {
                // If it's a linear-gradient, replace it only if it's purely gray (avoid touching reds)
                // Let's just target the hex codes directly
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
