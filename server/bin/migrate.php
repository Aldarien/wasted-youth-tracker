<?php

use Zieren\WYT\Infrastructure\Http\App;
use Zieren\WYT\Infrastructure\Persistence\DatabaseInitializer;

require_once __DIR__ . '/../bootstrap/composer.php';

$app = App::create(__DIR__ . '/../bootstrap');
$app->getContainer()->get(DatabaseInitializer::class)->initialize();

echo "Database initialized.\n";
