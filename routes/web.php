<?php

declare(strict_types=1);

use App\Http\Controllers\OAuth\HandleGoogleBootstrapCallbackController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');
Route::get('/oauth/google/bootstrap/callback', HandleGoogleBootstrapCallbackController::class)
    ->name('oauth.google.bootstrap.callback');
