<?php

use Illuminate\Support\Facades\DB;

$sql = "
SELECT c.TABLE_NAME, c.COLUMN_NAME
FROM information_schema.COLUMNS c
JOIN information_schema.TABLES t ON c.TABLE_NAME = t.TABLE_NAME AND c.TABLE_SCHEMA = t.TABLE_SCHEMA
LEFT JOIN information_schema.STATISTICS s ON c.TABLE_NAME = s.TABLE_NAME AND c.COLUMN_NAME = s.COLUMN_NAME AND c.TABLE_SCHEMA = s.TABLE_SCHEMA
WHERE c.TABLE_SCHEMA = DATABASE()
AND t.TABLE_TYPE = 'BASE TABLE'
AND (c.COLUMN_NAME LIKE '%\_id' OR c.COLUMN_NAME = 'tenant_id')
AND s.COLUMN_NAME IS NULL
AND c.TABLE_NAME NOT LIKE 'telescope_%'
AND c.TABLE_NAME NOT LIKE 'migrations'
";

$results = DB::select($sql);

echo "Missing Indexes:\n";
if (empty($results)) {
    echo "NONE FOUND\n";
} else {
    foreach ($results as $row) {
        echo $row->TABLE_NAME.' -> '.$row->COLUMN_NAME."\n";
    }
}
