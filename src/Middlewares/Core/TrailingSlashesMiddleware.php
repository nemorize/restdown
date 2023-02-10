<?php

namespace App\Middlewares\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class TrailingSlashesMiddleware implements MiddlewareInterface
{
    /**
     * Constructor.
     */
    public function __construct (
        private readonly bool $use = false
    ) {}

    /**
     * Process middleware.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $path = $this->normalize($uri->getPath());

        if ($uri->getPath() !== $path) {
            return (new Response())
                ->withHeader('Location', (string) $uri->withPath($path))
                ->withStatus(301);
        }

        return $handler->handle($request);
    }

    /**
     * Normalize the trailing slashes.
     *
     * @param string $path
     * @return string
     */
    private function normalize (string $path): string
    {
        if ($path === '') {
            return '/';
        }

        if (strlen($path) > 1) {
            if ($this->use) {
                if (!str_ends_with($path, '/') && !pathinfo($path, PATHINFO_EXTENSION)) {
                    return $path . '/';
                }
            }
            else {
                return rtrim($path, '/');
            }
        }

        return $path;
    }
}