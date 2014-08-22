<?php

namespace Acquia\Search\Export;

// Try to find the appropriate autoloader.
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
  require __DIR__ . '/../vendor/autoload.php';
} elseif (__DIR__ . '/../../../autoload.php') {
  require __DIR__ . '/../../../autoload.php';
}

use Symfony\Component\Console\Application;
use Acquia\Search\Export\Command\ExportCommand;

$application = new Application();
$application->add(new ExportCommand());
$application->run();