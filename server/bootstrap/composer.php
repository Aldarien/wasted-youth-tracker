<?php
$composerFolder = implode(DIRECTORY_SEPARATOR, [dirname(__FILE__, 2), 'vendor']);
if (!is_dir($composerFolder)) {
    throw new RuntimeException('Missing composer. Run composer install in the root of the project.');
}
require_once implode(DIRECTORY_SEPARATOR, [$composerFolder, 'autoload.php']);
