<?php

/**
 * @package     FrameX (FX) Engine
 * @link        https://localzet.gitbook.io
 * 
 * @author      localzet <creator@localzet.ru>
 * 
 * @copyright   Copyright (c) 2018-2020 Zorin Projects 
 * @copyright   Copyright (c) 2020-2022 NONA Team
 * 
 * @license     https://www.localzet.ru/license GNU GPLv3 License
 */

namespace app\exception;

use app\model\Token;
use Exception;
use FrameX\Auth\Guard\Guard;
use FrameX\JWT\Exception\JwtTokenExpiredException;
use localzet\FrameX\Exception\ExceptionHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use localzet\FrameX\Http\Request;
use localzet\FrameX\Http\Response;

/**
 * Class Handler
 */
class Handler implements ExceptionHandlerInterface
{
    /**
     * @var LoggerInterface
     */
    protected $_logger = null;

    /**
     * @var bool
     */
    protected $_debug = false;

    /**
     * @var array
     */
    public $dontReport = [];

    /**
     * ExceptionHandler constructor.
     * @param $logger
     * @param $debug
     */
    public function __construct($logger, $debug)
    {
        $this->_logger = $logger;
        $this->_debug = $debug;
    }

    /**
     * @param Throwable $exception
     * @return void
     */
    public function report(Throwable $exception)
    {
        if ($this->shouldntReport($exception)) {
            return;
        }

        $logs = '';
        if ($request = \request()) {
            $logs = $request->getRealIp() . ' ' . $request->method() . ' ' . \trim($request->fullUrl(), '/');
        }
        $this->_logger->error($logs . PHP_EOL . $exception);
    }

    /**
     * @param Request $request
     * @param Throwable $exception
     * @return Response
     */
    public function render(Request $request, Throwable $exception): Response
    {
        $status = $exception->getCode();
        if ($request->expectsJson()) {
            $json = [
                'debug' => $this->_debug,
                'status' => $status ? $status : 500,
                'error' => $exception->getMessage()
            ];
            $this->_debug && $json['traces'] = (string)$exception;
            return responseJson($json);

            // return new Response(
            //     200,
            //     ['Content-Type' => 'application/json'],
            //     \json_encode($json, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            // );
        }
        $error = $this->_debug ? \nl2br((string)$exception) : $exception->getMessage();

        if ($exception instanceof JwtTokenExpiredException) {
            if (session('access_token', false) && $tokens = Token::where('access_token', '=', session('access_token'))->first()) {

                $tokens = JWT()->refresh($tokens->refresh_token);
                $data = JWT()->getData(token: $tokens['access_token'], type: 1);

                $request->session()->set(Guard::SESSION_AUTH_ID, $data['user_id']);
                $request->session()->set('access_token', $tokens['access_token']);
                $request->session()->save();

                return redirect('/app');
            } else {
                return redirect('/auth/logout');
            }
        }

        return view('response/error', ['data' => $error], '');
        // return response((string)$exception, 500);
    }

    /**
     * @param Throwable $e
     * @return bool
     */
    protected function shouldntReport(Throwable $e)
    {
        foreach ($this->dontReport as $type) {
            if ($e instanceof $type) {
                return true;
            }
        }
        return false;
    }
}
