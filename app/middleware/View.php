<?php

/**
 * @author    localzet<creator@localzet.ru>
 * @copyright localzet<creator@localzet.ru>
 * @link      https://www.localzet.ru/
 * @license   https://www.localzet.ru/license GNU GPLv3 License
 */

namespace app\middleware;

use FrameX\HttpClient\Curl;
use localzet\FrameX\MiddlewareInterface;
use localzet\FrameX\Http\Response;
use localzet\FrameX\Http\Request;
use support\View as SupportView;

/**
 * Class StaticFile
 * @package app\middleware
 */
class View implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        SupportView::assign(config());
        SupportView::assign('session', session());
        

        if (!isset($request->data['loaded']) || empty($request->data['loaded'])) {
            SupportView::assign('title', 'localzet');
            SupportView::assign('description', 'localzet');
            SupportView::assign('keywords', 'localzet, Иван Зорин, Zorin Projects, NONA');
            SupportView::assign('viewport', 'width=device-width, initial-scale=1');
            SupportView::assign('canonical', $request->url());
            SupportView::assign('og', [
                // 'title' => 'localzet', // См. title
                // 'title' => 'og:description', // См. og:description
                'type' => 'website', // article, book, profile, website
                'image' => 'localzet',
                // 'url' => $request->url(), // См. canonical
            ]);
            // echo 'loaded';
            $request->data = ['loaded' => true];
        }

        /** @var Response $response */
        $response = $next($request);

        // $response->withHeader('RootX', 'rootx.ru');
        // $response->withHeader('RX-API', 'api.localzet.ru');
        $http = new Curl();
        $_matrix = $http->request("https://{$request->host(true)}/.well-known/matrix");

        if ($_matrix && is_string($_matrix)) {
            $_matrix = @json_decode($_matrix, true) ?? [];
        }
        if (is_array($_matrix)) {
            if (!empty($_matrix['m.homeserver'])) {
                $response->withHeader('RX-Chat', $_matrix['m.homeserver']['base_url']);
            }
            if (!empty($_matrix['m.identity_server'])) {
                $response->withHeader('RX-Identity', $_matrix['m.identity_server']['base_url']);
            }
        }

        return $response;
    }
}
