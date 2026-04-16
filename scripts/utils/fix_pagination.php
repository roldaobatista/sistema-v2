<?php
$dir = __DIR__ . '/backend/app/Http/Controllers';
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

        // Find public function index() and its contents until the next 'public function' or class end
        if (preg_match('/(public function index\s*\([^\)]*\)\s*\{)(.*?)(?=\s*public function|\s*protected function|\s*private function|\}\s*$)/is', $content, $matches)) {
            $declaration = $matches[1];
            $body = $matches[2];

            if (strpos($body, '->paginate(') === false && strpos($body, '->simplePaginate(') === false) {
                // If it uses ->get()
                if (strpos($body, '->get()') !== false) {
                    $newBody = str_replace('->get()', "->paginate(min((int) request()->input('per_page', 25), 100))", $body);
                    $content = str_replace($declaration . $body, $declaration . $newBody, $content);
                }
                // If it uses Model::all()
                elseif (preg_match('/\b([A-Z][a-zA-Z0-9_]*)::all\(\)/', $body, $m)) {
                    $modelClass = $m[1];
                    $newBody = preg_replace('/\b' . $modelClass . '::all\(\)/', $modelClass . "::paginate(min((int) request()->input('per_page', 25), 100))", $body);
                    $content = str_replace($declaration . $body, $declaration . $newBody, $content);
                }
            }
        }

        if($content !== $original) {
            file_put_contents($file->getPathname(), $content);
            echo "Fixed Pagination: " . $file->getFilename() . "\n";
            $count++;
        }
    }
}

echo "Total Pagination fixes: $count\n";
