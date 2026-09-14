<?php

use Slim\App;
use Zieren\WYT\Application\Action\GetConfigAction;
use Zieren\WYT\Application\Action\GetIndex;
use Zieren\WYT\Application\Action\PostIndex;
use Zieren\WYT\Application\Action\PostRxAction;
use Zieren\WYT\Application\Action\GetReadinessAction;

return static function (App $app) {
    $app->get('/health', static function ($request, $response) {
        $response->getBody()->write('{"status":"ok"}');
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
    $app->get('/ready', GetReadinessAction::class);
    $app->get('/', GetIndex::class);
    $app->post('/', PostIndex::class);
    $app->get('/config', GetConfigAction::class);
    $app->post('/rx', PostRxAction::class);
};
