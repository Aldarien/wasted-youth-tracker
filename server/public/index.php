<?php
use Psr\Log\LoggerInterface;

$app = null;
try {
    $app = require_once implode(DIRECTORY_SEPARATOR, [dirname(__FILE__, 2), 'bootstrap', 'app.php']);
    $app->run();
} catch (\Throwable $error) {
    if ($app !== null) {
        $app->getContainer()->get(LoggerInterface::class)
            ->error('Application bootstrap failed', [
                'exception' => $error,
            ]);
    } else {
        error_log('Application bootstrap failed: ' . $error->getMessage());
    }
    http_response_code(500);
    echo 'Internal Server Error';
}