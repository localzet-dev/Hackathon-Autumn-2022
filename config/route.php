<?php
/**
 * @author    localzet<creator@localzet.ru>
 * @copyright localzet<creator@localzet.ru>
 * @link      https://www.localzet.ru/
 * @license   https://www.localzet.ru/license GNU GPLv3 License
 */

use localzet\FrameX\Route;

Route::any('/test', function ($request) {
    throw new Exception("Тест))");
});

Route::fallback(function () {
    return response('Упс! Мы ничего не нашли', 500);
});