<?php

namespace Zieren\WYT\Tests\Unit;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Zieren\WYT\Application\Action\GetReadinessAction;
use Zieren\WYT\Infrastructure\Persistence\Connection;

final class GetReadinessActionTest extends TestCase
{
    public function testReturnsUnavailableWithoutExposingDatabaseError(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('query')
            ->with('SELECT 1')
            ->willThrowException(new RuntimeException('database secret'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Readiness check failed',
                $this->callback(static fn (array $context): bool =>
                    $context['exception'] instanceof RuntimeException
                )
            );

        $response = (new GetReadinessAction($connection, $logger))(
            new ServerRequest('GET', '/ready'),
            new Response()
        );

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"status":"unavailable"}', (string) $response->getBody());
        $this->assertStringNotContainsString('database secret', (string) $response->getBody());
    }
}
