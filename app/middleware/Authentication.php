<?php

/**
 * @package     T-University Project
 * @link        https://localzet.gitbook.io
 * 
 * @author      localzet <creator@localzet.ru>
 * 
 * @copyright   Copyright (c) 2018-2020 Zorin Projects 
 * @copyright   Copyright (c) 2020-2022 NONA Team
 * 
 * @license     https://www.localzet.ru/license GNU GPLv3 License
 */

namespace app\middleware;

use app\model\Token;
use app\repository\Tokens;
use Exception;

use FrameX\Auth\Middleware\Authentication as MiddlewareAuthentication;
use support\View;

use FrameX\Auth\Auth;
use FrameX\Auth\Interfaces\GuardInterface;
use FrameX\Auth\Interfaces\IdentityInterface;
use FrameX\JWT\JwtToken;

use localzet\FrameX\Http\Request;
use localzet\FrameX\Http\Response;
use localzet\FrameX\MiddlewareInterface;


/**
 * Middleware авторизации
 */
class Authentication implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        // Ну а если токена нет - проверяем исключительные пути
        if ($this->isOptionalRoute($request)) {
            return $handler($request);
        }

        // Получение имени гварда из SetAuthGuard
        $request->guard = $this->getGuard();

        // Получение личности
        $user = $request->guard->getAuthenticationMethod()->authenticate($request);
        $request->user = $request->guard->getAuthenticationMethod()->authenticate($request);

        // Если есть токен и удалось получить личность
        if ($request->user instanceof IdentityInterface) {
            // Запускаем процедуру входа
            $request->guard->login($request->user);

            // $request->token = JwtToken::getToken();
            // $request->tokenData = JwtToken::getExtend();

            $this->login($request);
            View::assign('user', $request->user);

            return $handler($request);
        }

        // throw new Exception(print_r($user, true));

        return $request->guard->getAuthenticationFailedHandler()->handle($request);
    }

    /**
     * Дополнительная аутентификация
     * @return void
     */
    protected function login($request)
    {
        $request->token = $request->session()->get('access_token');
        $request->tokenData = JwtToken::getExtend(token: $request->token);

        $request->token = Token::firstWhere('access_token', '=', $request->token);
        if (!$request->token) {
            View::assign('error', "Неизвестный токен");
            return redirect('auth', 401);
            // throw new Exception("Неизвестный токен", 401);
        }

        // Проверка соответствия пользователя в токене и из БД
        if ((int) $request->token->user_id !== (int) $request->user->id) {
            View::assign('error', "Подмена данных токена");
            return redirect('auth', 401);
            // throw new Exception("Подмена данных токена", 401);
        }

        View::assign('user', $request->user);

        return $request;
    }


    /**
     * Получение гварда
     * @return GuardInterface
     */
    protected function getGuard(): GuardInterface
    {
        // Гвард по дефолту
        return Auth::guard();
    }

    /**
     * Проверка исключительного пути
     * @param Request $request
     * @return bool
     */
    protected function isOptionalRoute(Request $request): bool
    {
        $path = $request->path();
        if (in_array($path, $this->optionalRoutes())) {
            return true;
        }

        return false;
    }

    /**
     * Исключительные пути
     * @return array
     */
    protected function optionalRoutes(): array
    {
        return config('plugin.framex.auth.auth.exceptions');
    }
}
