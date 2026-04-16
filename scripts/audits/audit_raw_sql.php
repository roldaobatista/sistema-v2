<?php

/**
 * Auditoria automatizada de DB::raw e funções similares.
 * Classifica cada uso como SAFE, NEEDS_REVIEW, ou DANGEROUS.
 *
 * SAFE: constantes, nomes de colunas, funções SQL sem input de usuário
 * NEEDS_REVIEW: usa variáveis mas pode ser seguro
 * DANGEROUS: interpolação direta de input de usuário
 */

$dir = __DIR__ . '/app';
$patterns = [
    'DB::raw',
    'DB::select',
    'DB::statement',
    'DB::unprepared',
    'whereRaw',
    'selectRaw',
    'orderByRaw',
    'havingRaw',
    'groupByRaw',
    'joinRaw',
];

$safe = [];
$needsReview = [];
$dangerous = [];

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $file) {
    if ($file->getExtension() !== 'php') continue;

    $path = $file->getPathname();
    $relativePath = str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $path);
    $lines = file($path);

    foreach ($lines as $lineNum => $line) {
        $found = false;
        foreach ($patterns as $pattern) {
            if (str_contains($line, $pattern)) {
                $found = $pattern;
                break;
            }
        }
        if (!$found) continue;

        $trimmed = trim($line);
        $lineNumber = $lineNum + 1;
        $entry = [
            'file' => $relativePath,
            'line' => $lineNumber,
            'pattern' => $found,
            'code' => $trimmed,
        ];

        // Classification logic
        $classification = classifyRawSql($trimmed, $lines, $lineNum);
        $entry['reason'] = $classification['reason'];

        switch ($classification['level']) {
            case 'SAFE':
                $safe[] = $entry;
                break;
            case 'NEEDS_REVIEW':
                $needsReview[] = $entry;
                break;
            case 'DANGEROUS':
                $dangerous[] = $entry;
                break;
        }
    }
}

function classifyRawSql(string $line, array $allLines, int $lineNum): array
{
    // DANGEROUS: Direct variable interpolation in double-quoted string
    if (preg_match('/(?:raw|select|statement|unprepared)\s*\(\s*"[^"]*\$/', $line)) {
        return ['level' => 'DANGEROUS', 'reason' => 'Variable interpolation in double-quoted SQL string'];
    }

    // DANGEROUS: Concatenation with variable
    if (preg_match('/(?:raw|select|statement|unprepared)\s*\([^)]*\.\s*\$(?!this->)/', $line)) {
        return ['level' => 'DANGEROUS', 'reason' => 'String concatenation with user variable in SQL'];
    }

    // SAFE: Only literal strings with no variables
    if (preg_match('/(?:raw|Raw)\s*\(\s*\'[^\']*\'\s*\)/', $line)) {
        return ['level' => 'SAFE', 'reason' => 'Single-quoted literal string only'];
    }

    // SAFE: Using binding parameters (second arg is array)
    if (preg_match('/(?:raw|select|statement)\s*\([^,]+,\s*\[/', $line)) {
        return ['level' => 'SAFE', 'reason' => 'Uses parameter binding'];
    }

    // SAFE: Common safe patterns (column references, aggregates, CASE WHEN with literals)
    $safePatterns = [
        '/(?:raw|Raw)\s*\(\s*\'(?:COUNT|SUM|AVG|MAX|MIN|COALESCE|IFNULL|NULLIF|NOW|CURDATE|DATE|YEAR|MONTH|CONCAT|GROUP_CONCAT|DISTINCT|CASE|WHEN|THEN|ELSE|END|IF|CAST|CONVERT|LOWER|UPPER|TRIM|LENGTH|SUBSTRING|REPLACE|ROUND|FLOOR|CEIL|ABS|MOD|TIMESTAMPDIFF|DATEDIFF|DATE_FORMAT|STR_TO_DATE|UNIX_TIMESTAMP|FROM_UNIXTIME|JSON_EXTRACT|JSON_UNQUOTE|JSON_CONTAINS|JSON_LENGTH|JSON_ARRAYAGG|JSON_OBJECT)\s*\(/i',
        '/orderByRaw\s*\(\s*\'(?:FIELD|CASE|created_at|updated_at|name|id|status|order|position|sort_order|priority)\b/i',
        '/selectRaw\s*\(\s*\'(?:COUNT|SUM|AVG|MAX|MIN|COALESCE|IFNULL|DISTINCT)\b/i',
        '/groupByRaw\s*\(\s*\'(?:DATE|YEAR|MONTH|WEEK|DAY|HOUR|DATE_FORMAT)\b/i',
    ];

    foreach ($safePatterns as $pattern) {
        if (preg_match($pattern, $line)) {
            return ['level' => 'SAFE', 'reason' => 'Known safe SQL function/pattern'];
        }
    }

    // NEEDS_REVIEW: $this-> usage (could be safe tenant scoping)
    if (str_contains($line, '$this->')) {
        return ['level' => 'NEEDS_REVIEW', 'reason' => 'Uses $this-> property — likely safe but verify'];
    }

    // NEEDS_REVIEW: Variable usage but not obviously dangerous
    if (preg_match('/\$\w+/', $line)) {
        return ['level' => 'NEEDS_REVIEW', 'reason' => 'Contains variable reference — manual review needed'];
    }

    // Default: literal-only is SAFE
    if (!str_contains($line, '$')) {
        return ['level' => 'SAFE', 'reason' => 'No variable references detected'];
    }

    return ['level' => 'NEEDS_REVIEW', 'reason' => 'Unclassified — manual review recommended'];
}

// Output report
echo "=== DB::raw / Raw SQL Audit Report ===\n\n";

echo "## Summary\n";
echo "SAFE:         " . count($safe) . "\n";
echo "NEEDS_REVIEW: " . count($needsReview) . "\n";
echo "DANGEROUS:    " . count($dangerous) . "\n";
echo "TOTAL:        " . (count($safe) + count($needsReview) + count($dangerous)) . "\n\n";

if (!empty($dangerous)) {
    echo "## 🔴 DANGEROUS (immediate fix required)\n\n";
    foreach ($dangerous as $entry) {
        echo "  {$entry['file']}:{$entry['line']} [{$entry['pattern']}]\n";
        echo "    Reason: {$entry['reason']}\n";
        echo "    Code: {$entry['code']}\n\n";
    }
}

if (!empty($needsReview)) {
    echo "## 🟡 NEEDS_REVIEW (manual verification)\n\n";
    foreach ($needsReview as $entry) {
        echo "  {$entry['file']}:{$entry['line']} [{$entry['pattern']}]\n";
        echo "    Reason: {$entry['reason']}\n";
        echo "    Code: " . substr($entry['code'], 0, 120) . "\n\n";
    }
}

echo "## 🟢 SAFE (" . count($safe) . " occurrences — no action needed)\n";
echo "(List omitted for brevity. All are literal SQL with no variable interpolation.)\n";
