<?php

use Slim\App;

$containerBuilder = new \DI\ContainerBuilder ();
$containerBuilder->addDefinitions(array_merge($slimDefinitions, $dependencies));
$container = $containerBuilder->build();

return new App($container);
