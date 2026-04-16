<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Audit log pruning (audit:prune)
    |--------------------------------------------------------------------------
    |
    | Registros mais antigos que prune_retention_months são exportados para
    | arquivo compactado no disco configurado e depois removidos do MySQL.
    |
    */

    'prune_retention_months' => (int) env('AUDIT_PRUNE_RETENTION_MONTHS', 6),

    'prune_disk' => env('AUDIT_PRUNE_DISK', 'local'),

    'prune_chunk_size' => (int) env('AUDIT_PRUNE_CHUNK_SIZE', 1000),

    'prune_export_format' => env('AUDIT_PRUNE_EXPORT_FORMAT', 'json'),

    'prune_storage_path' => 'audit-archive',

];
