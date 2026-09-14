<?php

namespace Zieren\WYT\Application\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Zieren\WYT\Infrastructure\Persistence\Connection;

final class GetReadinessAction
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        try {
            $this->connection->query('SELECT 1');
        } catch (Throwable $exception) {
            $this->logger->error('Readiness check failed', [
                'exception' => $exception,
            ]);
            $response->getBody()->write('{"status":"unavailable"}');

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(503);
        }

        $response->getBody()->write('{"status":"ready"}');

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
}
