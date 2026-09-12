<?php

namespace Zieren\WYT\Tests\Unit;

use DateTimeImmutable;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Slim\Views\Twig;
use Zieren\WYT\Application\Action\GetIndex;
use Zieren\WYT\Application\Action\PostIndex;
use Zieren\WYT\Application\Service\ConfigManagementService;
use Zieren\WYT\Application\Service\PruningService;
use Zieren\WYT\Application\Service\UserManagementService;
use Zieren\WYT\Domain\Entity\User;
use Zieren\WYT\Infrastructure\Clock\FrozenClock;

class AdminViewTest extends TestCase
{
    public function testIndexRendersTwigView(): void
    {
        $users = $this->createStub(UserManagementService::class);
        $users->method('getUsers')->willReturn([new User('u1')]);
        $action = new GetIndex(
            Twig::create(dirname(__DIR__, 2) . '/resources/views'),
            $users,
            new FrozenClock(new DateTimeImmutable('@1000'))
        );

        $response = $action(new ServerRequest('GET', '/'), new Response());

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Wasted Youth Tracker - Admin', $body);
        $this->assertStringContainsString('u1', $body);
    }

    public function testAddUserPostRedirectsAfterHandlingMutation(): void
    {
        $users = $this->createMock(UserManagementService::class);
        $users->expects($this->once())->method('addUser')->with('u1');
        $action = new PostIndex(
            $this->createStub(ConfigManagementService::class),
            $this->createStub(PruningService::class),
            $users
        );

        $request = (new ServerRequest('POST', '/'))
            ->withParsedBody(['addUser' => 'Add', 'userId' => 'u1']);
        $response = $action($request, new Response());

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/', $response->getHeaderLine('Location'));
    }

    public function testInvalidPruneDateReturnsBadRequest(): void
    {
        $pruning = $this->createMock(PruningService::class);
        $pruning->expects($this->never())->method('prune');
        $action = new PostIndex(
            $this->createStub(ConfigManagementService::class),
            $pruning,
            $this->createStub(UserManagementService::class)
        );

        $request = (new ServerRequest('POST', '/'))
            ->withParsedBody(['prune' => 'DELETE', 'datePrune' => 'invalid']);

        $this->assertSame(400, $action($request, new Response())->getStatusCode());
    }
}
