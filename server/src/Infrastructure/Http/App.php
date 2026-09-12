<?php
namespace Zieren\WYT\Infrastructure\Http;

use GlobIterator;
use RuntimeException;
use Throwable;
use DI\ContainerBuilder;
use DI\Bridge\Slim\Bridge as SlimBridge;
use Slim\App as SlimApp;
use SplFileInfo;

class App extends SlimApp
{
    private const MIN_PHP_VERSION = '8.1';

    public static function create(
        string $bootstrapDirectory,
        array $customFolderNames = ['definitions' => 'settings', 'autowires' => 'setups']
    ): self
    {
        App::checkRequirements(dirname($bootstrapDirectory));

        $builder = new ContainerBuilder();

        $folders = array_intersect_key($customFolderNames, array_flip(['definitions', 'autowires']));

        foreach ($folders as $folderName) {
            $folder = implode(DIRECTORY_SEPARATOR, [$bootstrapDirectory, $folderName]);
            if (!is_dir($folder)) {
                continue;
            }
            /** @var SplFileInfo[] $files */
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

        return $app;
    }

    public static function checkRequirements(?string $applicationDirectory = null): void
    {
        $unmet = [];
        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION) < 0) {
            $unmet[] = 'PHP version '.self::MIN_PHP_VERSION.' is required, but this is '.PHP_VERSION.'.';
        }
        if (!function_exists('mysqli_connect')) {
            $unmet[] = 'The mysqli extension is missing.';
        }
        $configPath = $applicationDirectory === null
            ? 'common/config.php'
            : implode(DIRECTORY_SEPARATOR, [$applicationDirectory, 'common', 'config.php']);
        if (!file_exists($configPath)) {
            $unmet[] = 'The file <code>common/config.php</code> is missing.';
        }
        if (!$unmet) {
            return;
        }
        $message = '<p><b>The following requirements are not met:</b></p>'
            . '<ul><li>' . implode('</li><li>', $unmet) . '</li></ul><hr />';

        throw new RuntimeException($message);
    }
}
