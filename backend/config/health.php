<?php

return [

    /*
    |--------------------------------------------------------------------------
    | System Health Alerts
    |--------------------------------------------------------------------------
    |
    | Proactive monitoring thresholds. When any threshold is exceeded,
    | a SystemAlertNotification is dispatched to the configured email.
    |
    */

    'alerts' => [
        'enabled' => (bool) env('HEALTH_ALERTS_ENABLED', true),

        // Queue: alert when pending jobs exceed this number
        'queue_pending_threshold' => (int) env('HEALTH_QUEUE_THRESHOLD', 100),

        // Disk: alert when usage percentage exceeds this value
        'disk_usage_threshold' => (int) env('HEALTH_DISK_THRESHOLD', 80),

        // Error count: alert when failed jobs in last 15 min exceed this value
        // Note: evolve to true error rate (%) when observability lands (story 1-8a)
        'error_count_threshold' => (int) env('HEALTH_ERROR_COUNT_THRESHOLD', 5),

        // Notification channels for alerts
        'channels' => ['mail', 'database'],
    ],

];
