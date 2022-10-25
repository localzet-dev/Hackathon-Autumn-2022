<?php

/**
 * @author    localzet<creator@localzet.ru>
 * @copyright localzet<creator@localzet.ru>
 * @link      https://www.localzet.ru/
 * @license   https://www.localzet.ru/license GNU GPLv3 License
 */

return [
    'type' => 'mysql', // MySQL (другие будут позже)
    'connections' => [
        'mysql' => [
            'driver'      => 'mysql',
            'host'        => 'localhost',
            'port'        => 3306,
            'database'    => 'Hackathon',
            'username'    => 'user',
            'password'    => 'pass',
            'unix_socket' => '',
            'charset'     => 'utf8',
            'collation'   => 'utf8_unicode_ci',
            'prefix'      => '',
            'strict'      => true,
            'engine'      => null,
   
        ]
    ]
];
