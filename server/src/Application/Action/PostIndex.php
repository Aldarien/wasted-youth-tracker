<?php

namespace Zieren\WYT\Application\Action;

use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zieren\WYT\Application\Service\ConfigManagementService;
use Zieren\WYT\Application\Service\ClassManagementService;
use Zieren\WYT\Application\Service\ClassificationManagementService;
use Zieren\WYT\Application\Service\LimitManagementService;
use Zieren\WYT\Application\Service\MappingManagementService;
use Zieren\WYT\Application\Service\OverrideManagementService;
use Zieren\WYT\Application\Service\PruningService;
use Zieren\WYT\Application\Service\UserManagementService;

class PostIndex
{
    public function __construct(
        private readonly ConfigManagementService $configManagementService,
        private readonly ClassManagementService $classManagementService,
        private readonly ClassificationManagementService $classificationManagementService,
        private readonly LimitManagementService $limitManagementService,
        private readonly MappingManagementService $mappingManagementService,
        private readonly OverrideManagementService $overrideManagementService,
        private readonly PruningService $pruningService,
        private readonly UserManagementService $userManagementService
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        if (isset($body['addClass'])) {
            $this->classManagementService->addClass($this->requiredString($body, 'className'));
        } elseif (isset($body['renameClass'])) {
            $this->classManagementService->renameClass(
                $this->requiredInt($body, 'classId'),
                $this->requiredString($body, 'className')
            );
        } elseif (isset($body['removeClass'])) {
            $this->classManagementService->removeClass($this->requiredInt($body, 'classId'));
        } elseif (isset($body['reclassify'])) {
            $date = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $this->requiredString($body, 'reclassifyFrom')
            );
            if ($date === false) {
                return $response->withStatus(400);
            }
            $this->classManagementService->reclassify($date);
        } elseif (isset($body['addClassification'])) {
            $this->classificationManagementService->addClassification(
                $this->requiredInt($body, 'classId'),
                $this->requiredInt($body, 'priority'),
                $this->requiredString($body, 'regex')
            );
        } elseif (isset($body['changeClassification'])) {
            $this->classificationManagementService->changeClassification(
                $this->requiredInt($body, 'classificationId'),
                $this->requiredString($body, 'regex'),
                $this->requiredInt($body, 'priority')
            );
        } elseif (isset($body['removeClassification'])) {
            $this->classificationManagementService->removeClassification(
                $this->requiredInt($body, 'classificationId')
            );
        } elseif (isset($body['addLimit'])) {
            $this->limitManagementService->addLimit(
                $this->requiredString($body, 'userId'),
                $this->requiredString($body, 'limitName')
            );
        } elseif (isset($body['renameLimit'])) {
            $this->limitManagementService->renameLimit(
                $this->requiredString($body, 'userId'),
                $this->requiredInt($body, 'limitId'),
                $this->requiredString($body, 'limitName')
            );
        } elseif (isset($body['removeLimit'])) {
            $this->limitManagementService->removeLimit(
                $this->requiredString($body, 'userId'),
                $this->requiredInt($body, 'limitId')
            );
        } elseif (isset($body['addMapping'])) {
            $this->mappingManagementService->addMapping(
                $this->requiredInt($body, 'classId'),
                $this->requiredInt($body, 'limitId')
            );
        } elseif (isset($body['removeMapping'])) {
            $this->mappingManagementService->removeMapping(
                $this->requiredInt($body, 'classId'),
                $this->requiredInt($body, 'limitId')
            );
        } elseif (isset($body['setLimitConfig'])) {
            $this->limitManagementService->setLimitConfig(
                $this->requiredString($body, 'userId'),
                $this->requiredInt($body, 'limitId'),
                $this->requiredString($body, 'configKey'),
                $this->requiredString($body, 'configValue')
            );
        } elseif (isset($body['clearLimitConfig'])) {
            $this->limitManagementService->clearLimitConfig(
                $this->requiredString($body, 'userId'),
                $this->requiredInt($body, 'limitId'),
                $this->requiredString($body, 'configKey')
            );
        } elseif (isset($body['setOverrideMinutes'])) {
            $this->overrideManagementService->setMinutes(
                $this->requiredString($body, 'userId'),
                $this->requiredString($body, 'overrideDate'),
                $this->requiredInt($body, 'limitId'),
                $this->requiredInt($body, 'minutes')
            );
        } elseif (isset($body['setOverrideSlots'])) {
            $this->overrideManagementService->setSlots(
                $this->requiredString($body, 'userId'),
                $this->requiredString($body, 'overrideDate'),
                $this->requiredInt($body, 'limitId'),
                $this->requiredString($body, 'slots')
            );
        } elseif (isset($body['unlockOverride'])) {
            $this->overrideManagementService->unlock(
                $this->requiredString($body, 'userId'),
                $this->requiredString($body, 'overrideDate'),
                $this->requiredInt($body, 'limitId')
            );
        } elseif (isset($body['clearOverrides'])) {
            $this->overrideManagementService->clearOverrides(
                $this->requiredString($body, 'userId'),
                $this->requiredString($body, 'overrideDate'),
                $this->requiredInt($body, 'limitId')
            );
        } elseif (isset($body['setUserConfig'])) {
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

    /**
     * @param array<string, mixed> $body
     */
    private function requiredInt(array $body, string $key): int
    {
        $value = $body[$key] ?? null;
        if (is_int($value) || (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false)) {
            return (int) $value;
        }
        throw new \InvalidArgumentException("Missing or invalid form field: $key");
    }
}
