<?php

declare(strict_types=1);

use App\Http\Controllers\OAuth\HandleGoogleCalendarBootstrapCallbackController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');
Route::get('/oauth/google/bootstrap/callback', HandleGoogleCalendarBootstrapCallbackController::class)
    ->name('oauth.google.bootstrap.callback');
