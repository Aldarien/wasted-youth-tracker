<?php

$testConfig = __DIR__ . '/config_tests.php';
if (!is_file($testConfig)) {
    $testConfig = __DIR__ . '/config_tests-sample.php';
}
require_once $testConfig;
require_once dirname(__DIR__) . '/vendor/autoload.php';
