<?php
namespace Zieren\WYT\Domain;

class Wasted
{
    const CHARSET_AND_COLLATION = 'latin1 COLLATE latin1_general_ci';
    const CREATE_TABLE_SUFFIX = 'CHARACTER SET ' . self::CHARSET_AND_COLLATION . ' ';

    const WASTED_SERVER_HEADING = 'Wasted Youth Tracker 0.1.1';
    const DEFAULT_CLASS_NAME = 'default_class';
    const DEFAULT_CLASS_ID = 1;
    const DEFAULT_CLASSIFICATION_ID = 1;
    const TOTAL_LIMIT_NAME = 'Total';
    const MYSQL_SIGNED_BIGINT_MAX = '9223372036854775807';
    const MYSQL_SIGNED_INT_MIN = -2147483648;
}
