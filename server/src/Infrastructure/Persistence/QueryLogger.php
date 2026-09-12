<?php

namespace Zieren\WYT\Infrastructure\Persistence;

use Logger;

class QueryLogger
{
    public static function log(array $params): void
    {
        Logger::Instance()->debug(
            'DB query [' . $params['runtime'] . 'ms]: ' . str_replace("\r\n", '', $params['query']));
    }

    public static function error(array $params): void
    {
        Logger::Instance()->error('DB query: ' . str_replace("\r\n", '', $params['query']));
        Logger::Instance()->error('DB error: ' . $params['error']);
    }
}
