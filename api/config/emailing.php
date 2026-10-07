<?php

return [
    'enabled' => (bool) env('EMAILING_ENABLED', false),
    // Testovací režim: kampane prebehnú celé a zapíšu sa ako odoslané, ale email sa reálne neodošle.
    'dry_run' => (bool) env('EMAILING_DRY_RUN', false),
    'hourly_limit' => max(1, (int) env('EMAILING_HOURLY_LIMIT', 100)),
    'batch_size' => max(1, min(50, (int) env('EMAILING_BATCH_SIZE', 10))),
];
