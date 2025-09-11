<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/dbseed', function (Request $request) {
    return \Illuminate\Support\Facades\Artisan::call("db:seed");
});

Route::post('/hook/cashonrails', [App\Http\Controllers\CashonrailsWebhook::class, 'index']);

require __DIR__ . '/cash_on.php';
require __DIR__ . '/admin.php';
