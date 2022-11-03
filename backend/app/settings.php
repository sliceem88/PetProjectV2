<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Monolog\Logger;

return function (ContainerBuilder $containerBuilder) {
    // Global Settings Object
    $containerBuilder->addDefinitions([
        'settings' => [
            'displayErrorDetails' => true,
            'logger'              => [
                'name'  => 'slim-app',
                'path'  => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
                'level' => Logger::DEBUG,
            ],
            'db'                  => [
                'name'     => 'gql',
                'host'     => 'localhost',
                'username' => 'alex',
                'password' => 'password1',
                'driver'   => 'pdo_mysql'
            ]
        ],
    ]);
};
