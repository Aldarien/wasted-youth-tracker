<?php

namespace Zieren\WYT\Infrastructure\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class RequestLoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly int $slowRequestMs = 1000
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $requestId = bin2hex(random_bytes(16));
        $startedAt = microtime(true);
        $context = [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
        ];
        $upstreamRequestId = $this->upstreamRequestId($request);
        if ($upstreamRequestId !== null) {
            $context['upstream_request_id'] = $upstreamRequestId;
        }

        try {
            $response = $handler->handle($request);
        } catch (Throwable $exception) {
            $this->logger->error('HTTP request failed', [
                ...$context,
                'duration_ms' => $this->duration($startedAt),
                'exception' => $exception,
            ]);

            throw $exception;
        }

        $durationMs = $this->duration($startedAt);
        $context = [
            ...$context,
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
        ];
        $this->logger->info('HTTP request completed', $context);
        if ($durationMs >= $this->slowRequestMs) {
            $this->logger->warning('Slow HTTP request', $context);
        }

        return $response->withHeader('X-Request-Id', $requestId);
    }

    private function upstreamRequestId(ServerRequestInterface $request): ?string
    {
        $requestId = trim($request->getHeaderLine('X-Request-Id'));
        if ($requestId !== '' && preg_match('/^[A-Za-z0-9._-]{1,128}$/', $requestId) === 1) {
            return $requestId;
        }

        return null;
    }

    private function duration(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }
}
