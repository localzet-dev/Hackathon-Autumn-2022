<?php

/**
 * @author    localzet<creator@localzet.ru>
 * @copyright localzet<creator@localzet.ru>
 * @link      https://www.localzet.ru/
 * @license   https://www.localzet.ru/license GNU GPLv3 License
 */

use support\Request;

return [
    'debug' => false,
    // 'version' => 'version',
    // 'core_version' => 'core_version',
    'error_reporting' => E_ALL,
    'default_timezone' => 'Europe/Moscow',
    'request_class' => Request::class,
    'public_path' => base_path() . DIRECTORY_SEPARATOR . 'public',
    'runtime_path' => base_path(false) . DIRECTORY_SEPARATOR . 'runtime',
    'controller_suffix' => '',
    'controller_reuse' => true,

    'name' => 'UniSocial',

    'default_mode' => 'dark',
    'dark_pages' => ['sign-in'],

    'oauth_server' => 'https://t-uni.ru/app/oauth/',
    'oauth_params' => '?tokenback=http%3A%2F%2Flocalhost%3A88%2F',

    'domain' => 'https://rx.localzet.ru',
    'assets' => 'https://rx.localzet.ru/assets',
    'fonts' => 'https://src.localzet.ru/fonts',

    'logo' => '/1.svg',

    'base' => base_path() . DIRECTORY_SEPARATOR . 'app/view/base.phtml',
    'aside' => base_path() . DIRECTORY_SEPARATOR . 'app/view/aside.phtml',

];
