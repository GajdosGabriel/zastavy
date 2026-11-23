<?php

return [
    'enabled' => (bool) env('OPS_MONITOR_ENABLED', false),
    'queue_max_size' => (int) env('OPS_QUEUE_MAX_SIZE', 100),
    'heartbeat_max_age' => (int) env('OPS_HEARTBEAT_MAX_AGE', 900),
    // Heartbeaty schedulera a workera sú v samostatnom úložisku: nasadenie
    // spúšťa optimize:clear → cache:clear, ktorý vymaže predvolenú cache.
    // Bez toho ops:check tesne po nasadení hlásil falošne mŕtveho workera.
    'heartbeat_store' => env('OPS_HEARTBEAT_STORE', 'operations'),
    'failed_job_window_hours' => (int) env('OPS_FAILED_JOB_WINDOW_HOURS', 24),
];
