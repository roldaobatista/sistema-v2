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

        $tokens = token_get_all($content);
        $inIndex = false;
        $braceCount = 0;
        $startIndex = -1;
        $endIndex = -1;

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_FUNCTION) {
                // look ahead for 'index'
                $j = $i + 1;
                while(isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
                if (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][1] === 'index') {
                    $inIndex = true;
                    // find opening brace
                    while(isset($tokens[$j]) && $tokens[$j] !== '{') $j++;
                    if (isset($tokens[$j]) && $tokens[$j] === '{') {
                        $braceCount = 1;
                        $startIndex = $j;
                        $i = $j;
                        continue;
                    }
                }
            }

            if ($inIndex) {
                if ($token === '{') $braceCount++;
                if ($token === '}') {
                    $braceCount--;
                    if ($braceCount === 0) {
                        $endIndex = $i;
                        $inIndex = false;

                        // We have start and end of index method
                        // Reconstruct file parts
                        $before = "";
                        for($k = 0; $k <= $startIndex; $k++) {
                            $before .= is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
                        }

                        $body = "";
                        for($k = $startIndex + 1; $k < $endIndex; $k++) {
                            $body .= is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
                        }

                        $after = "";
                        for($k = $endIndex; $k < count($tokens); $k++) {
                            $after .= is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
                        }

                        if (strpos($body, '->paginate(') === false && strpos($body, '->simplePaginate(') === false) {
                            if (strpos($body, '->get()') !== false) {
                                // Find the last ->get() in the chain before return, or just the first ->get()
                                // Since we usually have Model::...->get(), replacing the first ->get() is usually right
                                // Wait, replace all ->get() to be safe? Or just one? Most index methods have only one ->get()
                                $body = preg_replace('/->get\(\)/', "->paginate(min((int) request()->input('per_page', 25), 100))", $body, 1);
                            } elseif (preg_match('/\b([A-Z][a-zA-Z0-9_]*)::all\(\)/', $body, $m)) {
                                $modelClass = $m[1];
                                $body = preg_replace('/\b' . $modelClass . '::all\(\)/', $modelClass . "::paginate(min((int) request()->input('per_page', 25), 100))", $body, 1);
                            }
                        }

                        $content = $before . $body . $after;
                        // we must re-tokenize if we changed content to continue correctly, but we only expect one index() per file, so break
                        break;
                    }
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
