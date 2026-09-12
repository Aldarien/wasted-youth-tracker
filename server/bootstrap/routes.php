<?php

use Slim\App;
use Zieren\WYT\Application\Action\GetConfigAction;
use Zieren\WYT\Application\Action\GetIndex;
use Zieren\WYT\Application\Action\PostIndex;
use Zieren\WYT\Application\Action\PostRxAction;

return static function (App $app) {
    $app->get('/', GetIndex::class);
    $app->post('/', PostIndex::class);
    $app->get('/config', GetConfigAction::class);
    $app->post('/rx', PostRxAction::class);
};
