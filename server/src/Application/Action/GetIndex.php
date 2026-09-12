<?php
namespace Zieren\WYT\Application\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;
use Zieren\WYT\Application\Service\AdminViewService;
use Zieren\WYT\Domain\Clock;

class GetIndex
{
    public function __construct(
        private readonly Twig $view,
        private readonly AdminViewService $adminViewService,
        private readonly Clock $clock
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->view->render($response, 'index.twig', array_merge(
            $this->adminViewService->getPageData(),
            ['pruneFromDate' => $this->clock->now()]
        ));
    }
}
