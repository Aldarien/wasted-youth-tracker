<?php

namespace Zieren\WYT\Domain;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
