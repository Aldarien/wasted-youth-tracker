<?php
namespace Zieren\WYT\Application\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;
use Zieren\WYT\Application\Service\UserManagementService;
use Zieren\WYT\Domain\Clock;

class GetIndex
{
    public function __construct(
        private readonly Twig $view,
        private readonly UserManagementService $userManagementService,
        private readonly Clock $clock
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->view->render($response, 'index.twig', [
            'users' => $this->userManagementService->getUsers(),
            'pruneFromDate' => $this->clock->now(),
        ]);
    }
}
