<?php

declare(strict_types=1);

use App\Http\Controllers\Mcp\ShowOAuthAuthorizationServerMetadataController;
use App\Http\Controllers\Mcp\ShowOAuthProtectedResourceMetadataController;
use App\Http\Middleware\AuthenticateGoogleMcpClient;
use App\Mcp\Servers\GoogleCalendarServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::get('/.well-known/oauth-protected-resource/{path?}', ShowOAuthProtectedResourceMetadataController::class)
    ->where('path', '.*')
    ->name('mcp.oauth.protected-resource');

Route::get('/.well-known/oauth-authorization-server/{path?}', ShowOAuthAuthorizationServerMetadataController::class)
    ->where('path', '.*')
    ->name('mcp.oauth.authorization-server');

Mcp::web('/mcp', GoogleCalendarServer::class)
    ->middleware(AuthenticateGoogleMcpClient::class);
