<?php

namespace app\app\controller;

use app\model\News;
use app\model\User;
use support\Request;
use support\View;

class Index
{
    public function index(Request $request)
    {
        // return redirect('/auth');
        View::assign('title', config('app.name'));
        View::assign('page', 'home');
        View::assign('first_posts', News::limit(5)->offset(0)->get());
        View::assign('second_posts', News::limit(5)->offset(5)->get());
        return view('app/index', [], '');
        // $email = User::find(1)->email;
        // return response('hello ' . $email);
    }
}
