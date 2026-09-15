<?php

namespace Zieren\WYT\Application\Command;

use DateTimeImmutable;
use InvalidArgumentException;
use Zieren\WYT\Application\ValueObject\BudgetName;
use Zieren\WYT\Application\ValueObject\ClassName;
use Zieren\WYT\Application\ValueObject\Minutes;
use Zieren\WYT\Application\ValueObject\SlotExpression;
use Zieren\WYT\Application\ValueObject\WindowTitlePattern;

final class FormCommandFactory
{
    public function create(array $body): Command
    {
        if (isset($body['addClass'])) {
            return new AddClassCommand(new ClassName($this->requiredString($body, 'className')));
        }
        if (isset($body['renameClass'])) {
            return new RenameClassCommand(
                $this->requiredInt($body, 'classId'),
                new ClassName($this->requiredString($body, 'className'))
            );
        }
        if (isset($body['removeClass'])) {
            return new RemoveClassCommand($this->requiredInt($body, 'classId'));
        }
        if (isset($body['reclassify'])) {
            return new ReclassifyCommand($this->requiredDate($body, 'reclassifyFrom'));
        }
        if (isset($body['addClassification'])) {
            return new AddClassificationCommand(
                $this->requiredInt($body, 'classId'),
                $this->requiredInt($body, 'priority'),
                new WindowTitlePattern($this->requiredString($body, 'regex'))
            );
        }
        if (isset($body['changeClassification'])) {
            return new ChangeClassificationCommand(
                $this->requiredInt($body, 'classificationId'),
                new WindowTitlePattern($this->requiredString($body, 'regex')),
                $this->requiredInt($body, 'priority')
            );
        }
        if (isset($body['removeClassification'])) {
            return new RemoveClassificationCommand($this->requiredInt($body, 'classificationId'));
        }
        if (isset($body['addLimit'])) {
            return new AddLimitCommand(
                $this->requiredString($body, 'userId'),
                new BudgetName($this->requiredString($body, 'limitName'))
            );
        }
        if (isset($body['renameLimit'])) {
            return new RenameLimitCommand(
                $this->requiredString($body, 'userId'),
                $this->requiredInt($body, 'limitId'),
                new BudgetName($this->requiredString($body, 'limitName'))
            );
        }
        if (isset($body['removeLimit'])) {
            return new RemoveLimitCommand(
                $this->requiredString($body, 'userId'),
                $this->requiredInt($body, 'limitId')
            );
        }
        if (isset($body['addMapping'])) {
            return new AddMappingCommand(
                $this->requiredInt($body, 'classId'),
                $this->requiredInt($body, 'limitId')
            );
        }
        if (isset($body['removeMapping'])) {
            return new RemoveMappingCommand(
                $this->requiredInt($body, 'classId'),
                $this->requiredInt($body, 'limitId')
            );
        }
        if (isset($body['setLimitConfig'])) {
            return new SetLimitConfigCommand(
                $this->requiredString($body, 'userId'),
                $this->requiredInt($body, 'limitId'),
                $this->requiredString($body, 'configKey'),
                $this->requiredString($body, 'configValue')
            );
        }
        if (isset($body['clearLimitConfig'])) {
            return new ClearLimitConfigCommand(
                $this->requiredString($body, 'userId'),
                $this->requiredInt($body, 'limitId'),
                $this->requiredString($body, 'configKey')
            );
        }
        if (isset($body['setOverrideMinutes'])) {
            return new SetOverrideMinutesCommand(
                $this->requiredString($body, 'userId'),
                $this->requiredString($body, 'overrideDate'),
                $this->requiredInt($body, 'limitId'),
                new Minutes($this->requiredInt($body, 'minutes'))
            );
        }
        if (isset($body['setOverrideSlots'])) {
            return new SetOverrideSlotsCommand(
                $this->requiredString($body, 'userId'),
                $this->requiredString($body, 'overrideDate'),
                $this->requiredInt($body, 'limitId'),
                new SlotExpression($this->requiredString($body, 'slots'))
            );
        }
        if (isset($body['unlockOverride'])) {
            return new UnlockOverrideCommand(
                $this->requiredString($body, 'userId'),
                $this->requiredString($body, 'overrideDate'),
                $this->requiredInt($body, 'limitId')
            );
        }
        if (isset($body['clearOverrides'])) {
            return new ClearOverridesCommand(
                $this->requiredString($body, 'userId'),
                $this->requiredString($body, 'overrideDate'),
                $this->requiredInt($body, 'limitId')
            );
        }
        if (isset($body['setUserConfig'])) {
            return new SetUserConfigCommand(
                $this->requiredString($body, 'selectedUser'),
                $this->requiredString($body, 'configKey'),
                $this->requiredString($body, 'configValue')
            );
        }
        if (isset($body['clearUserConfig'])) {
            return new ClearUserConfigCommand(
                $this->requiredString($body, 'selectedUser'),
                $this->requiredString($body, 'configKey')
            );
        }
        if (isset($body['setGlobalConfig'])) {
            return new SetGlobalConfigCommand(
                $this->requiredString($body, 'configKey'),
                $this->requiredString($body, 'configValue')
            );
        }
        if (isset($body['clearGlobalConfig'])) {
            return new ClearGlobalConfigCommand($this->requiredString($body, 'configKey'));
        }
        if (isset($body['addUser'])) {
            return new AddUserCommand($this->requiredString($body, 'userId'));
        }
        if (isset($body['removeUser'])) {
            return new RemoveUserCommand($this->requiredString($body, 'userId'));
        }
        if (isset($body['addRuleFromTitle'])) {
            return new CreateRuleFromTitleCommand(
                $this->requiredString($body, 'className'),
                $this->requiredInt($body, 'priority'),
                $this->requiredString($body, 'title'),
                $this->optionalInt($body, 'limitId')
            );
        }
        if (isset($body['prune'])) {
            return new PruneCommand($this->requiredDate($body, 'datePrune'));
        }

        throw new InvalidArgumentException('No recognized command in request body');
    }

    private function requiredString(array $body, string $key): string
    {
        $value = $body[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Missing form field: $key");
        }
        return trim($value);
    }

    private function requiredInt(array $body, string $key): int
    {
        $value = $body[$key] ?? null;
        if (is_int($value) || (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false)) {
            return (int) $value;
        }
        throw new InvalidArgumentException("Missing or invalid form field: $key");
    }

    private function optionalInt(array $body, string $key): ?int
    {
        if (!isset($body[$key]) || $body[$key] === '') {
            return null;
        }
        return $this->requiredInt($body, $key);
    }

    private function requiredDate(array $body, string $key): DateTimeImmutable
    {
        $value = $this->requiredString($body, $key);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false) {
            throw new InvalidArgumentException("Invalid date field: $key");
        }
        return $date;
    }
}
