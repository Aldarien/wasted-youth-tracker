<?php
use Zieren\WYT\Infrastructure\Http\App;

require_once __DIR__ . '/composer.php';

$app = App::create(__DIR__);
$routes = require_once __DIR__ . '/routes.php';
$routes($app);

return $app;
