<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

/* [SERVER USE] */
Route::get('/artisan/{cmd}/{key}', function ($cmd, $key) {
    $secret = 'zikri123';

    if ($key !== $secret) {
        abort(403, 'Unauthorized');
    }

    $allowed = [
        'migrate' => 'migrate',
        'migratefresh' => 'migrate:fresh',
        'key'     => 'key:generate',
        'cache'   => 'config:cache',
        'route'   => 'route:cache',
        'view'    => 'view:cache',
        'storage' => 'storage:link',
        'optimize'   => 'optimize:clear',
        'composer' => 'composer dump-autoload',
    ];

    if (!array_key_exists($cmd, $allowed)) {
        return "Command not allowed.";
    }

    Artisan::call($allowed[$cmd]);
    return nl2br(Artisan::output());
});

Route::get('/', function () {
    return view('welcome');
});
