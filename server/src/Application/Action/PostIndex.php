<?php

namespace Zieren\WYT\Application\Action;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zieren\WYT\Application\Command\CommandBus;
use Zieren\WYT\Application\Command\FormCommandFactory;

class PostIndex
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly FormCommandFactory $commandFactory
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        try {
            $command = $this->commandFactory->create($body);
            $this->commandBus->dispatch($command);
        } catch (InvalidArgumentException) {
            return $response->withStatus(400);
        }

        return $response->withHeader('Location', '/')->withStatus(303);
    }
}
