<?php

namespace Zieren\WYT\Tests\Unit;

use DateTimeImmutable;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Slim\Views\Twig;
use Zieren\WYT\Application\Action\GetIndex;
use Zieren\WYT\Application\Action\PostIndex;
use Zieren\WYT\Application\Command\AddClassCommand;
use Zieren\WYT\Application\Command\AddUserCommand;
use Zieren\WYT\Application\ValueObject\ClassName;
use Zieren\WYT\Application\Command\Command;
use Zieren\WYT\Application\Command\CommandBus;
use Zieren\WYT\Application\Command\FormCommandFactory;
use Zieren\WYT\Application\Service\AdminViewService;
use Zieren\WYT\Application\ViewModel\AdminPageView;
use Zieren\WYT\Application\ViewModel\BudgetStatusView;
use Zieren\WYT\Application\ViewModel\ChildStatusView;
use Zieren\WYT\Application\ViewModel\DashboardView;
use Zieren\WYT\Application\ViewModel\SetupProgressView;
use Zieren\WYT\Domain\Entity\User;
use Zieren\WYT\Domain\Entity\ActivityClass;
use Zieren\WYT\Domain\Entity\Classification;
use Zieren\WYT\Infrastructure\Clock\FrozenClock;

class AdminViewTest extends TestCase
{
    public function testIndexRendersTwigView(): void
    {
        $adminView = $this->createStub(AdminViewService::class);
        $adminView->method('getPageData')->willReturn(new AdminPageView(
            [new User('u1')],
            [new ActivityClass(2, 'Work')],
            [2 => [new Classification(2, 2, 10, '/mail/i')]],
            [],
            [],
            ['u1' => [3 => ['name' => 'Browsing', 'is_total' => false]]],
            new DashboardView(
                new SetupProgressView(true, false, false, false),
                1,
                1,
                0,
                [],
                [],
                [
                    'u1' => new ChildStatusView(null, '', [
                        new BudgetStatusView('Browsing', 300, false, false),
                    ]),
                ]
            )
        ));
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

    public function testAddUserPostDispatchesCommandAndRedirects(): void
    {
        $bus = new class() extends CommandBus {
            public array $commands = [];
            public function dispatch(Command $command): void
            {
                $this->commands[] = $command;
            }
        };
        $action = new PostIndex($bus, new FormCommandFactory());

        $request = (new ServerRequest('POST', '/'))
            ->withParsedBody(['addUser' => 'Add', 'userId' => 'u1']);
        $response = $action($request, new Response());

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/', $response->getHeaderLine('Location'));
        $this->assertCount(1, $bus->commands);
        $this->assertInstanceOf(AddUserCommand::class, $bus->commands[0]);
        $this->assertSame('u1', $bus->commands[0]->userId);
    }

    public function testInvalidPruneDateReturnsBadRequest(): void
    {
        $bus = new CommandBus();
        $action = new PostIndex($bus, new FormCommandFactory());

        $request = (new ServerRequest('POST', '/'))
            ->withParsedBody(['prune' => 'DELETE', 'datePrune' => 'invalid']);

        $this->assertSame(400, $action($request, new Response())->getStatusCode());
    }

    public function testAddClassPostDispatchesCommandAndRedirects(): void
    {
        $bus = new class() extends CommandBus {
            public array $commands = [];
            public function dispatch(Command $command): void
            {
                $this->commands[] = $command;
            }
        };
        $action = new PostIndex($bus, new FormCommandFactory());

        $request = (new ServerRequest('POST', '/'))
            ->withParsedBody(['addClass' => 'Add', 'className' => 'School']);
        $response = $action($request, new Response());

        $this->assertSame(303, $response->getStatusCode());
        $this->assertCount(1, $bus->commands);
        $this->assertInstanceOf(AddClassCommand::class, $bus->commands[0]);
        $this->assertSame('School', $bus->commands[0]->name->value);
    }

    public function testInvalidReclassifyDateReturnsBadRequest(): void
    {
        $bus = new CommandBus();
        $action = new PostIndex($bus, new FormCommandFactory());

        $request = (new ServerRequest('POST', '/'))
            ->withParsedBody(['reclassify' => 'Reclassify', 'reclassifyFrom' => 'invalid']);

        $this->assertSame(400, $action($request, new Response())->getStatusCode());
    }
}
