<?php

use App\Application;
use App\Controllers\PostController;
use App\Controllers\WebhookController;
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
 * Post routes.
 */
Route::group('/posts', function (RouteCollectorProxy $group) {
    $group->get('', [ PostController::class, 'getPosts' ]);
    $group->get('/{slug}', [ PostController::class, 'getPost' ]);
});

/**
 * Webhook route.
 */
Route::get('/webhook', [ WebhookController::class, 'webhook' ]);