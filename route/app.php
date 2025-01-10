<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group('', function () {
    Route::get('', 'index/index');
    Route::any('/login', 'index/login');
    Route::any('/register', 'index/register');
    Route::any('/detail/:id', 'index/detail');
    Route::any('/temporary', 'index/temporary');
    Route::any('/forgotpassword', 'index/forgotpassword');
});

Route::group('dowload', function () {
    Route::any('/:imageSrc', 'download/index');
});

// http://dev.aicovers.com/aicover/aicover
Route::group('aicover', function () {
    Route::get('/aicover', 'Aicover/aicover');
});
