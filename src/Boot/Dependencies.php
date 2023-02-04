<?php

use App\Middlewares\Core\TrailingSlashesMiddleware;

return [
    /**
     * Application middlewares.
     * Middlewares should implement Psr\Http\Server\MiddlewareInterface.
     */
    'app:middlewares' => [
        TrailingSlashesMiddleware::class,
    ],
];