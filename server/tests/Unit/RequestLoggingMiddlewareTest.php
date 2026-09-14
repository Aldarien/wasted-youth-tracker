<?php

namespace Zieren\WYT\Tests\Unit;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Zieren\WYT\Infrastructure\Http\RequestLoggingMiddleware;

final class RequestLoggingMiddlewareTest extends TestCase
{
    public function testLogsCompletedRequestAndGeneratesServerOwnedRequestId(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'HTTP request completed',
                $this->callback(static function (array $context): bool {
                    return $context['method'] === 'GET'
                        && $context['path'] === '/health'
                        && $context['status'] === 204
                        && preg_match('/^[a-f0-9]{32}$/', $context['request_id']) === 1
                        && $context['upstream_request_id'] === 'proxy-request-1'
                        && is_float($context['duration_ms']);
                })
            );

        $request = (new ServerRequest('GET', '/health'))
            ->withHeader('X-Request-Id', 'proxy-request-1');
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(204);
            }
        };

        $response = (new RequestLoggingMiddleware($logger))->process($request, $handler);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $response->getHeaderLine('X-Request-Id'));
        $this->assertNotSame('proxy-request-1', $response->getHeaderLine('X-Request-Id'));
    }

    public function testLogsAndRethrowsRequestException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'HTTP request failed',
                $this->callback(static function (array $context): bool {
                    return preg_match('/^[a-f0-9]{32}$/', $context['request_id']) === 1
                        && $context['method'] === 'POST'
                        && $context['path'] === '/rx'
                        && $context['exception'] instanceof RuntimeException
                        && is_float($context['duration_ms']);
                })
            );

        $exception = new RuntimeException('request failed');
        $handler = new class($exception) implements RequestHandlerInterface {
            public function __construct(private readonly RuntimeException $exception)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw $this->exception;
            }
        };

        $this->expectExceptionObject($exception);
        (new RequestLoggingMiddleware($logger))->process(
            new ServerRequest('POST', '/rx'),
            $handler
        );
    }

    public function testDoesNotLogQueryStringsOrParsedRequestBodies(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'HTTP request completed',
                $this->callback(static function (array $context): bool {
                    return $context['path'] === '/login'
                        && !array_key_exists('query', $context)
                        && !array_key_exists('body', $context)
                        && !array_key_exists('password', $context);
                })
            );

        $request = (new ServerRequest('POST', '/login?password=secret'))
            ->withParsedBody(['username' => 'user', 'password' => 'secret']);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        };

        (new RequestLoggingMiddleware($logger))->process($request, $handler);
    }

    public function testLogsSlowRequestsAsWarnings(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'Slow HTTP request',
                $this->callback(static fn (array $context): bool =>
                    $context['status'] === 200 && $context['duration_ms'] >= 1
                )
            );

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                usleep(5000);
                return new Response(200);
            }
        };

        (new RequestLoggingMiddleware($logger, 1))->process(
            new ServerRequest('GET', '/slow'),
            $handler
        );
    }
}
