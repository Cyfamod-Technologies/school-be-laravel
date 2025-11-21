<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    return redirect('/api/docs');
});

Route::get('/reset-password', function (Request $request) {
    $token = (string) $request->query('token', '');
    $email = (string) $request->query('email', '');

    if ($token === '' || $email === '') {
        abort(404);
    }

    $frontendBase = env('FRONTEND_URL', rtrim(config('app.url'), '/'));
    $redirectUrl = rtrim($frontendBase, '/') . '/login';

    return view('auth.reset-password', [
        'token' => $token,
        'email' => $email,
        'redirectUrl' => $redirectUrl,
    ]);
});
