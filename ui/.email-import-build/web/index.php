<?php

/*
 * Front controller API na https://zastavy-vlajky.sk/web/api/…
 *
 * Doména servíruje ui/dist, Laravel leží v <repo>/api. Adresár api hľadáme
 * smerom nahor od tohto súboru, aby to fungovalo z ui/public/web aj z ui/dist/web.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$base = null;
for ($dir = __DIR__; $dir !== dirname($dir); $dir = dirname($dir)) {
    if (is_file($dir.'/api/bootstrap/app.php')) {
        $base = $dir.'/api';
        break;
    }
}

if ($base === null) {
    http_response_code(500);
    exit('API not found');
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $base.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $base.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
