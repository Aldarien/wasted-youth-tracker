<?php

namespace Zieren\WYT\Tests\Unit;

use DateTimeImmutable;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Slim\Views\Twig;
use Zieren\WYT\Application\Action\GetIndex;
use Zieren\WYT\Application\Action\PostIndex;
use Zieren\WYT\Application\Service\AdminViewService;
use Zieren\WYT\Application\Service\ClassManagementService;
use Zieren\WYT\Application\Service\ClassificationManagementService;
use Zieren\WYT\Application\Service\ConfigManagementService;
use Zieren\WYT\Application\Service\LimitManagementService;
use Zieren\WYT\Application\Service\MappingManagementService;
use Zieren\WYT\Application\Service\OverrideManagementService;
use Zieren\WYT\Application\Service\PruningService;
use Zieren\WYT\Application\Service\UserManagementService;
use Zieren\WYT\Domain\Entity\User;
use Zieren\WYT\Domain\Entity\ActivityClass;
use Zieren\WYT\Domain\Entity\Classification;
use Zieren\WYT\Infrastructure\Clock\FrozenClock;

class AdminViewTest extends TestCase
{
    public function testIndexRendersTwigView(): void
    {
        $adminView = $this->createStub(AdminViewService::class);
        $adminView->method('getPageData')->willReturn([
            'users' => [new User('u1')],
            'classes' => [new ActivityClass(2, 'Work')],
            'classifications' => [2 => [new Classification(2, 2, 10, '/mail/i')]],
            'globalConfig' => [],
            'userConfig' => [],
            'limits' => ['u1' => [3 => ['name' => 'Browsing', 'is_total' => false]]],
        ]);
        $action = new GetIndex(
            Twig::create(dirname(__DIR__, 2) . '/resources/views'),
            $adminView,
            new FrozenClock(new DateTimeImmutable('@1000'))
        );

        $response = $action(new ServerRequest('GET', '/'), new Response());

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Wasted Youth Tracker - Admin', $body);
        $this->assertStringContainsString('u1', $body);
        $this->assertStringContainsString('Work', $body);
        $this->assertStringContainsString('Browsing', $body);
    }

    public function testAddUserPostRedirectsAfterHandlingMutation(): void
    {
        $users = $this->createMock(UserManagementService::class);
        $users->expects($this->once())->method('addUser')->with('u1');
        $action = new PostIndex(
            $this->createStub(ConfigManagementService::class),
            $this->createStub(ClassManagementService::class),
            $this->createStub(ClassificationManagementService::class),
            $this->createStub(LimitManagementService::class),
            $this->createStub(MappingManagementService::class),
            $this->createStub(OverrideManagementService::class),
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
            $this->createStub(ClassManagementService::class),
            $this->createStub(ClassificationManagementService::class),
            $this->createStub(LimitManagementService::class),
            $this->createStub(MappingManagementService::class),
            $this->createStub(OverrideManagementService::class),
            $pruning,
            $this->createStub(UserManagementService::class)
        );

        $request = (new ServerRequest('POST', '/'))
            ->withParsedBody(['prune' => 'DELETE', 'datePrune' => 'invalid']);

        $this->assertSame(400, $action($request, new Response())->getStatusCode());
    }

    public function testAddClassPostUsesClassManagementService(): void
    {
        $classes = $this->createMock(ClassManagementService::class);
        $classes->expects($this->once())->method('addClass')->with('School');
        $action = new PostIndex(
            $this->createStub(ConfigManagementService::class),
            $classes,
            $this->createStub(ClassificationManagementService::class),
            $this->createStub(LimitManagementService::class),
            $this->createStub(MappingManagementService::class),
            $this->createStub(OverrideManagementService::class),
            $this->createStub(PruningService::class),
            $this->createStub(UserManagementService::class)
        );

        $request = (new ServerRequest('POST', '/'))
            ->withParsedBody(['addClass' => 'Add', 'className' => 'School']);

        $this->assertSame(303, $action($request, new Response())->getStatusCode());
    }

    public function testInvalidReclassifyDateReturnsBadRequest(): void
    {
        $classes = $this->createMock(ClassManagementService::class);
        $classes->expects($this->never())->method('reclassify');
        $action = new PostIndex(
            $this->createStub(ConfigManagementService::class),
            $classes,
            $this->createStub(ClassificationManagementService::class),
            $this->createStub(LimitManagementService::class),
            $this->createStub(MappingManagementService::class),
            $this->createStub(OverrideManagementService::class),
            $this->createStub(PruningService::class),
            $this->createStub(UserManagementService::class)
        );

        $request = (new ServerRequest('POST', '/'))
            ->withParsedBody(['reclassify' => 'Reclassify', 'reclassifyFrom' => 'invalid']);

        $this->assertSame(400, $action($request, new Response())->getStatusCode());
    }
}
