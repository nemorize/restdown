<?php

namespace App\Core\Http;

use App\Application;
use Slim\App as SlimApp;

class Route
{
    public static function get (string $pattern, $callable): void
    {
        /**
         * @var SlimApp $app Slim application.
         */
        $app = Application::get('slim');
        $app->get($pattern, $callable);
    }

    public static function post (string $pattern, $callable): void
    {
        /**
         * @var SlimApp $app Slim application.
         */
        $app = Application::get('slim');
        $app->post($pattern, $callable);
    }

    public static function put (string $pattern, $callable): void
    {
        /**
         * @var SlimApp $app Slim application.
         */
        $app = Application::get('slim');
        $app->put($pattern, $callable);
    }

    public static function patch (string $pattern, $callable): void
    {
        /**
         * @var SlimApp $app Slim application.
         */
        $app = Application::get('slim');
        $app->patch($pattern, $callable);
    }

    public static function delete (string $pattern, $callable): void
    {
        /**
         * @var SlimApp $app Slim application.
         */
        $app = Application::get('slim');
        $app->delete($pattern, $callable);
    }

    public static function options (string $pattern, $callable): void
    {
        /**
         * @var SlimApp $app Slim application.
         */
        $app = Application::get('slim');
        $app->options($pattern, $callable);
    }

    public static function any (string $pattern, $callable): void
    {
        /**
         * @var SlimApp $app Slim application.
         */
        $app = Application::get('slim');
        $app->any($pattern, $callable);
    }

    public static function map (array $methods, string $pattern, $callable): void
    {
        /**
         * @var SlimApp $app Slim application.
         */
        $app = Application::get('slim');
        $app->map($methods, $pattern, $callable);
    }

    public static function group (string $pattern, $callable): void
    {
        /**
         * @var SlimApp $app Slim application.
         */
        $app = Application::get('slim');
        $app->group($pattern, $callable);
    }
}