<?php

namespace Zieren\WYT\Tests\Integration;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Slim\App;

final class HealthEndpointTest extends TestCase
{
    private static ?App $app = null;

    public function testHealthEndpointIsRegisteredAndReturnsRequestId(): void
    {
        $app = $this->app();

        $response = $app->handle(new ServerRequest('GET', '/health'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        $this->assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        $this->assertSame(
            'camera=(), microphone=(), geolocation=()',
            $response->getHeaderLine('Permissions-Policy')
        );
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{32}$/',
            $response->getHeaderLine('X-Request-Id')
        );
        $this->assertJsonStringEqualsJsonString(
            '{"status":"ok"}',
            (string) $response->getBody()
        );
    }

    public function testReadinessEndpointChecksDatabase(): void
    {
        $app = $this->app();

        $response = $app->handle(new ServerRequest('GET', '/ready'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(
            '{"status":"ready"}',
            (string) $response->getBody()
        );
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{32}$/',
            $response->getHeaderLine('X-Request-Id')
        );
    }

    private function app(): App
    {
        return self::$app ??= require dirname(__DIR__, 2) . '/bootstrap/app.php';
    }
}
