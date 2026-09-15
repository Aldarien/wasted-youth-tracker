<?php

namespace Zieren\WYT\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Zieren\WYT\Application\Action\GetConfigAction;
use Zieren\WYT\Application\Action\GetIndex;
use Zieren\WYT\Application\Action\GetReadinessAction;
use Zieren\WYT\Application\Action\PostIndex;
use Zieren\WYT\Application\Action\PostRxAction;
use Zieren\WYT\Infrastructure\Http\App;

class ContainerWiringTest extends TestCase
{
    public function testAdminActionsAreResolvable(): void
    {
        $app = App::create(dirname(__DIR__, 2) . '/bootstrap');
        $container = $app->getContainer();

        $this->assertInstanceOf(GetIndex::class, $container->get(GetIndex::class));
        $this->assertInstanceOf(PostIndex::class, $container->get(PostIndex::class));
        $this->assertInstanceOf(GetReadinessAction::class, $container->get(GetReadinessAction::class));
        $this->assertInstanceOf(GetConfigAction::class, $container->get(GetConfigAction::class));
        $this->assertInstanceOf(PostRxAction::class, $container->get(PostRxAction::class));
    }
}
