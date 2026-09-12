<?php

namespace Zieren\WYT\Application\Action;

use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zieren\WYT\Application\Service\ConfigManagementService;
use Zieren\WYT\Application\Service\PruningService;
use Zieren\WYT\Application\Service\UserManagementService;

class PostIndex
{
    public function __construct(
        private readonly ConfigManagementService $configManagementService,
        private readonly PruningService $pruningService,
        private readonly UserManagementService $userManagementService
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        if (isset($body['setUserConfig'])) {
            $this->configManagementService->setUserConfig(
                $this->requiredString($body, 'selectedUser'),
                $this->requiredString($body, 'configKey'),
                $this->requiredString($body, 'configValue')
            );
        } elseif (isset($body['clearUserConfig'])) {
            $this->configManagementService->clearUserConfig(
                $this->requiredString($body, 'selectedUser'),
                $this->requiredString($body, 'configKey')
            );
        } elseif (isset($body['setGlobalConfig'])) {
            $this->configManagementService->setGlobalConfig(
                $this->requiredString($body, 'configKey'),
                $this->requiredString($body, 'configValue')
            );
        } elseif (isset($body['clearGlobalConfig'])) {
            $this->configManagementService->clearGlobalConfig(
                $this->requiredString($body, 'configKey')
            );
        } elseif (isset($body['addUser'])) {
            $this->userManagementService->addUser($this->requiredString($body, 'userId'));
        } elseif (isset($body['removeUser'])) {
            $this->userManagementService->removeUser($this->requiredString($body, 'userId'));
        } elseif (isset($body['prune'])) {
            $date = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $this->requiredString($body, 'datePrune')
            );
            if ($date === false) {
                return $response->withStatus(400);
            }
            $this->pruningService->prune($date);
        }

        return $response->withHeader('Location', '/')->withStatus(303);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function requiredString(array $body, string $key): string
    {
        $value = $body[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException("Missing form field: $key");
        }
        return trim($value);
    }
}
