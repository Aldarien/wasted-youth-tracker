<?php

namespace Zieren\WYT\Application\ViewModel;

final class SetupProgressView
{
    public function __construct(
        public readonly bool $hasChildren,
        public readonly bool $hasBudget,
        public readonly bool $hasAppGroup,
        public readonly bool $hasMapping
    ) {
    }
}
