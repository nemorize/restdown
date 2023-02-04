<?php

namespace App\Middlewares\Auth;

use App\Application;
use App\Models\Auth\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key as JWTKey;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class AuthMiddleware implements MiddlewareInterface
{
    /**
     * @var User|null $user Current user.
     */
    private static ?User $user = null;

    /**
     * Process middleware.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authorization = $request->getHeaderLine('Authorization');
        if (empty($authorization) || !str_starts_with($authorization, 'Bearer ')) {
            return $handler->handle($request);
        }

        $token = substr($authorization, 7);
        try {
            $userId = JWT::decode($token, new JWTKey(Application::get('env:SECRET_KEY'), 'HS256'));
            self::$user = new User();
            self::$user->id = $userId;
        }
        catch (Throwable) {}
        return $handler->handle($request);
    }

    /**
     * Get current user.
     *
     * @return ?User
     */
    public static function getCurrentUser (): ?User
    {
        self::$user?->refresh();
        return self::$user;
    }
}