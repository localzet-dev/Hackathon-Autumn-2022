<?php

namespace app\controller;

use app\model\User;
use support\Request;
use support\View;

class Index
{
    public function index(Request $request)
    {
        return redirect('/auth');
        // View::assign('title', config('app.name'));
        // return view('custom/landing');
        // $email = User::find(1)->email;
        // return response('hello ' . $email);
    }
}
