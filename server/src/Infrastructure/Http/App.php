<?php
namespace Zieren\WYT\Infrastructure\Http;

use GlobIterator;
use RuntimeException;
use Throwable;
use DI\ContainerBuilder;
use DI\Bridge\Slim\Bridge as SlimBridge;
use Slim\App as SlimApp;

class App extends SlimApp
{
    private const MIN_PHP_VERSION = '8.1';

    public static function create(
        string $bootstrapDirectory,
        array $customFolderNames = ['definitions' => 'settings', 'autowires' => 'setups']
    ): SlimApp
    {
        App::checkRequirements();

        $builder = new ContainerBuilder();

        $folders = array_intersect_key($customFolderNames, array_flip(['definitions', 'autowires']));

        foreach ($folders as $folderName) {
            $folder = implode(DIRECTORY_SEPARATOR, [$bootstrapDirectory, $folderName]);
            if (!is_dir($folder)) {
                continue;
            }
            $files = new GlobIterator("$folder/*.php");
            foreach ($files as $filePath => $file) {
                $builder->addDefinitions($filePath);
            }
        }

        try {
            $app = SlimBridge::create($builder->build());
        } catch (Throwable $exception) {
            throw new RuntimeException('Failed to create application: [' . $exception::class . '] ' . $exception->getMessage());
        }

        $app->addBodyParsingMiddleware();

        return $app;
    }

    public static function checkRequirements(): void
    {
        $unmet = [];
        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION) < 0) {
            $unmet[] = 'PHP version '.self::MIN_PHP_VERSION.' is required, but this is '.PHP_VERSION.'.';
        }
        if (!class_exists(\PDO::class) || !in_array('mysql', \PDO::getAvailableDrivers(), true)) {
            $unmet[] = 'The PDO MySQL driver is missing.';
        }
        if (!(getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? ''))) {
            $unmet[] = 'The DB_NAME environment variable is missing.';
        }
        if (!$unmet) {
            return;
        }
        $message = '<p><b>The following requirements are not met:</b></p>'
            . '<ul><li>' . implode('</li><li>', $unmet) . '</li></ul><hr />';

        throw new RuntimeException($message);
    }
}
