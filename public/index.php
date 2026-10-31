<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

// Correction du sous-dossier /admin sur le serveur (lorsque .htaccess redirige /admin/... vers /admin/public/index.php)
if (isset($_SERVER['REQUEST_URI']) && (str_starts_with($_SERVER['REQUEST_URI'], '/admin/') || $_SERVER['REQUEST_URI'] === '/admin')) {
    $_SERVER['SCRIPT_NAME'] = '/admin/index.php';
    $_SERVER['PHP_SELF'] = '/admin/index.php';
}

$app->handleRequest(Request::capture());
