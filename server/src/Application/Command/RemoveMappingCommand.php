<?php

namespace Zieren\WYT\Application\Command;

final class RemoveMappingCommand implements Command
{
    public function __construct(
        public readonly int $classId,
        public readonly int $limitId
    ) {
    }
}
