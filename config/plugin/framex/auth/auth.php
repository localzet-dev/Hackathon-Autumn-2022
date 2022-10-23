<?php

/**
 * @package     FrameX (FX) Authentication Plugin
 * @link        https://localzet.gitbook.io
 * 
 * @author      localzet <creator@localzet.ru>
 * 
 * @copyright   Copyright (c) 2018-2020 Zorin Projects 
 * @copyright   Copyright (c) 2020-2022 NONA Team
 * 
 * @license     https://www.localzet.ru/license GNU GPLv3 License
 */

// identityRepository

use app\model\User;

use FrameX\Auth\Interfaces\IdentityRepositoryInterface;

// authenticationMethod
use FrameX\Auth\Authentication\Method\HttpAuthorizationMethod;
use FrameX\Auth\Authentication\Method\HttpBasicMethod;
use FrameX\Auth\Authentication\Method\HttpBearerMethod;
use FrameX\Auth\Authentication\Method\HttpHeaderMethod;
use FrameX\Auth\Authentication\Method\JwtMethod;
use FrameX\Auth\Authentication\Method\RequestMethod;
use FrameX\Auth\Authentication\Method\SessionMethod;

// authenticationFailureHandler
use FrameX\Auth\Authentication\FailureHandler\RedirectHandler;
use FrameX\Auth\Authentication\FailureHandler\ResponseHandler;
use FrameX\Auth\Authentication\FailureHandler\ThrowExceptionHandler;


return [
    'exceptions' => [
        '/',
        '/test',
        '/auth',
        '/auth/token',
        '/auth/logout',
        '/auth/forgot',
        '/auth/create',
        // '/app',
        '/app/news',
    ],
        'default' => 'user',
    'guards' => [
        'user' => [
            'class' => FrameX\Auth\Guard\Guard::class,
            'identityRepository' => function () {
                return new User();
            },
            'authenticationMethod' => function (IdentityRepositoryInterface $identityRepository) {
                return new SessionMethod($identityRepository);
            },
            'authenticationFailureHandler' => function () {
                // return new ResponseHandler();
                return new RedirectHandler('/auth');
            },
        ]
    ]
];
