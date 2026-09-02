<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disk pre používateľské súbory
    |--------------------------------------------------------------------------
    |
    | Obrázky produktov a prílohy objednávok. Oddelené od 'filesystems.default',
    | ktorý slúži internému úložisku aplikácie (napr. coupon_settings.json) a má
    | ostať lokálny. Predvolene sa berie FILESYSTEM_DISK, takže prepnutie
    | prostredia na S3 stačí spraviť jednou premennou.
    |
    */

    'disk' => env('MEDIA_DISK', env('FILESYSTEM_DISK', 'public')),

    /*
    |--------------------------------------------------------------------------
    | Verejné čítanie z S3
    |--------------------------------------------------------------------------
    |
    | true = objekty v buckete sú čitateľné anonymne (bucket policy alebo
    | CloudFront) a URL sa generuje ako priama AWS_URL adresa — cacheovateľná,
    | vhodná pre obrázky e-shopu.
    | false = bucket je súkromný, URL sa podpisuje na obmedzený čas.
    |
    */

    'public_read' => filter_var(env('MEDIA_PUBLIC_READ', false), FILTER_VALIDATE_BOOLEAN),

    // Platnosť podpísanej URL v minútach (použije sa len pri public_read=false).
    'signed_ttl' => (int) env('MEDIA_SIGNED_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Prílohy objednávok
    |--------------------------------------------------------------------------
    |
    | Prílohy sú vždy súkromné — sťahujú sa cez API endpoint, nikdy nie priamym
    | odkazom do bucketu.
    |
    */

    'attachments' => [
        'max_files' => (int) env('ORDER_ATTACHMENTS_MAX_FILES', 5),
        // v kilobajtoch (validačné pravidlo `max:` pracuje s KB)
        'max_size' => (int) env('ORDER_ATTACHMENTS_MAX_SIZE', 10240),
        'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ai', 'eps', 'cdr', 'psd', 'zip', 'rar', 'doc', 'docx', 'xls', 'xlsx'],
    ],

];
