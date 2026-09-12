<?php

namespace Zieren\WYT\Application\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zieren\WYT\Application\Service\ClientConfigService;

class GetConfigAction
{
    public function __construct(
        private readonly ClientConfigService $clientConfigService
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getQueryParams()['user'] ?? '';
        $user = trim($user);
        if (!$user) {
            return $response->withStatus(400);
        }

        $response->getBody()->write($this->clientConfigService->getConfig($user));
        return $response->withHeader('Content-Type', 'text/plain; charset=iso-8859-1');
    }
}
