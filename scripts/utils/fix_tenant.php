<?php
$dir = __DIR__ . '/backend/app/Http/Requests';
if (!is_dir($dir)) {
    echo "Directory not found.\n";
    exit(1);
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$count = 0;

foreach($files as $file) {
    if($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $original = $content;

        // Remove 'tenant_id' => '...', or "tenant_id" => "...",
        $content = preg_replace('/([\'"]tenant_id[\'"]\s*=>\s*[\'"].*?[\'"]\s*,?\s*\n?)/', '', $content);

        // Remove 'tenant_id' => [ ... ],
        // We use a non-greedy match for the array contents
        $content = preg_replace('/([\'"]tenant_id[\'"]\s*=>\s*\[.*?\]\s*,?\s*\n?)/s', '', $content);

        // In addition, if there is a 'tenant_id' => Rule::exists(...),
        $content = preg_replace('/([\'"]tenant_id[\'"]\s*=>\s*Rule::.*?,\s*\n?)/s', '', $content);

        // If there's an empty line left where tenant_id was, it's fine, but we can clean it up slightly

        // Also check if anyone is doing $this->merge(['tenant_id' => ...])
        $content = preg_replace('/([\'"]tenant_id[\'"]\s*=>\s*\$this->user\(\)->.*?,\s*\n?)/', '', $content);

        if($content !== $original) {
            file_put_contents($file->getPathname(), $content);
            echo "Fixed: " . $file->getFilename() . "\n";
            $count++;
        }
    }
}

echo "Total FormRequests fixed for tenant_id exposure: $count\n";
