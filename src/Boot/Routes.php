<?php

use App\Application;
use App\Controllers\Auth\AuthController;
use App\Core\Http\Route;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Routing\RouteCollectorProxy;

/**
 * Welcome route.
 */
Route::any('/', function (ServerRequest $request, Response $response): Response {
    return $response->withJson([
        'success' => true,
        'url' => Application::get('env:APP_URL'),
        'name' => Application::get('env:APP_NAME'),
        'watch' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'
    ]);
});

/**
 * Authentication routes.
 */
Route::group('/auth', function (RouteCollectorProxy $group) {
    $group->any('/authenticate', [ AuthController::class, 'getAuthenticate' ]);
});