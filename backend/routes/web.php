<?php 


use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::middleware('auth')->get(
    '/realtime-test',
    function () {
        return view('realtime-test');
    }
);

Route::get('/realtime-test-login', function () {

    $seller = \App\Models\User::findOrFail(2);

    abort_unless($seller->isSeller(), 403);

    Auth::login($seller);

    return redirect('/realtime-test');
});