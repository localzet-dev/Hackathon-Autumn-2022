<?php

namespace app\controller;

use app\model\User;
use Exception;
use FrameX\Auth\Guard\Guard;
use FrameX\JWT\JWT;
use support\Request;
use support\View;

class Auth
{
    function returnError($error)
    {
        // request()->session()->set('auth_error', $error);
        // request()->session()->save();
        // return redirect("/auth");
        return response($error, 400);
    }

    function index(Request $request)
    {
        if (strtolower($request->method()) != 'post') {
            if (session('access_token', false)) {
                return redirect('/app');
            }
            // return response(print_r(config('app'), true));
            if (session('auth_error', false)) {
                View::assign('auth_error', session('auth_error'));
                session()->delete('auth_error');
            }

            View::assign('title', 'Авторизация');
            View::assign('page', 'sign-in');
            return view('app/auth/sign-in');
        } else {
            $input = $request->post();

            if (empty($input['email']) || empty($input['password'])) {
                return $this->returnError('Некорректный E-mail или пароль');
            }

            /** @var User $user */
            $user = User::firstWhere('email', '=', $input['email']);

            if (empty($user)) {
                return $this->returnError('Пользователь не найден');
            }

            if ($user->diffPassword($input['password'])) {
                $tokens = $user->generateToken();

                // $request->session()->set(Guard::SESSION_AUTH_ID, $user->id);
                // $request->session()->set('access_token', $tokens->access_token);
                // $request->session()->save();

                // return redirect('/app');
                // return responseJson([
                //     'status' => 200,
                //     'redirect' => $request->host(true) . '/app'
                // ]);
                return response(['access_token' => $tokens->access_token, 'user' => $user->id]);
            }

            return response("Пароль неверный", 401);
        }
    }

    function token(Request $request)
    {
        if ($request->get('user', false) && $request->get('access_token', false)) {
            $request->session()->set(Guard::SESSION_AUTH_ID, $request->get('user'));
            $request->session()->set('access_token', $request->get('access_token'));
            $request->session()->save();
            // return response(print_r(session(), true));
            return redirect('/app');
        }
    }

    function logout(Request $request)
    {
        $request->session()->flush();
        $request->session()->save();
        return redirect('/auth');
    }

    function forgot(Request $request)
    {
        if (session('access_token', false)) {
            return redirect('/app');
        }
        if (session('auth_error', false)) {
            View::assign('auth_error', session('auth_error'));
            session()->delete('auth_error');
        }
        View::assign('title', 'Сброс пароля');
        View::assign('page', 'sign-in');
        return view('app/auth/forgot');
    }
    function create(Request $request)
    {
        if (session('access_token', false)) {
            return redirect('/app');
        }
        if (session('auth_error', false)) {
            View::assign('auth_error', session('auth_error'));
            session()->delete('auth_error');
        }
        View::assign('title', 'Регистрация');
        View::assign('page', 'sign-in');
        return view('app/auth/create');
    }
}
