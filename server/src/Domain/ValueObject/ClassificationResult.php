<?php

namespace Zieren\WYT\Domain\ValueObject;

final class ClassificationResult
{
    /**
     * @param int[] $limitIds
     */
    public function __construct(
        public readonly int $classId,
        public readonly array $limitIds
    ) {
    }
}
