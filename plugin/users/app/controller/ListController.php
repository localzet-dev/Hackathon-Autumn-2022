<?php

/**
 * @package     FrameX (FX) OAuth Plugin
 * @link        https://localzet.gitbook.io
 * 
 * @author      localzet <creator@localzet.ru>
 * 
 * @copyright   Copyright (c) 2018-2020 Zorin Projects 
 * @copyright   Copyright (c) 2020-2022 NONA Team
 * 
 * @license     https://www.localzet.ru/license GNU GPLv3 License
 */

namespace plugin\users\app\controller;

use app\model\User;
use support\Request;
use support\View;

class ListController
{
    function index(Request $request)
    {
        View::assign('users', User::all());
        View::assign('page', 'users-list');
        return view("list");
    }
}
