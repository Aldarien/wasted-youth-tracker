<?php

namespace Zieren\WYT\Application\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zieren\WYT\Application\Service\RxService;

class PostRxAction
{
    public function __construct(
        private readonly RxService $rxService
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $content = (string) $request->getBody();
        $lines = preg_split("/\r\n|\n|\r/", $content);

        if (count($lines) < 2 || !$lines[0]) {
            return $response->withStatus(400);
        }

        $user = $lines[0];
        $lastError = $lines[1] ?? '';
        $titles = array_slice($lines, 2);

        $response->getBody()->write($this->rxService->handle($user, $lastError, $titles));
        return $response->withHeader('Content-Type', 'text/plain; charset=iso-8859-1');
    }
}
