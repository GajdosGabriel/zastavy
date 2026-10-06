<?php

return [
    'enabled' => (bool) env('EMAILING_ENABLED', false),
    'hourly_limit' => max(1, (int) env('EMAILING_HOURLY_LIMIT', 100)),
    'batch_size' => max(1, min(50, (int) env('EMAILING_BATCH_SIZE', 10))),
];
