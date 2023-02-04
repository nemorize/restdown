<?php

namespace App;

use App\Core\Exceptable;
use DI\Container;
use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Sentry\ClientBuilder as SentryClientBuilder;
use Sentry\State\Hub as SentryHub;
use Slim\App as SlimApp;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;
use Throwable;

class Application
{
    /**
     * @var self $instance Application instance.
     */
    private static self $instance;

    /**
     * @var SlimApp $app Slim application.
     */
    private SlimApp $app;

    /**
     * @var Container $container Dependency container.
     */
    private Container $container;

    /**
     * @var ?SentryHub $sentryHub Sentry hub.
     */
    private ?SentryHub $sentryHub;

    /**
     * Constructor.
     *
     * @throws Exception
     */
    public function __construct ()
    {
        self::$instance = $this;
        $this->container = $this->createDependencyContainer(__DIR__ . '/Boot/Dependencies.php');
        $this->set('app', $this);
        $this->set(Application::class, $this);

        $this->registerEnvironments();
        $this->container->set('app:env', $_ENV);
        foreach ($_ENV as $key => $value) {
            if (!$this->has('env:' . $key)) {
                $this->set('env:' . $key, $value);
            }
        }

        $this->app = $this->createSlimApplication($this->container);
        $this->set('slim', $this->app);
        $this->set(SlimApp::class, $this->app);

        $this->sentryHub = $this->createSentryHub();
        $this->addErrorMiddleware($this->app);
    }

    /**
     * Register environment variables from dotenv.
     *
     * @return void
     */
    private function registerEnvironments (): void
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->safeLoad();
    }

    /**
     * Create dependency container.
     *
     * @param ?string $configPath
     * @return Container
     * @throws Exception
     */
    private function createDependencyContainer (?string $configPath): Container
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(true);
        if ($configPath !== null) {
            $builder->addDefinitions($configPath);
        }

        return $builder->build();
    }

    /**
     * Create Slim application.
     *
     * @param Container $container
     * @return SlimApp
     */
    private function createSlimApplication (Container $container): SlimApp
    {
        AppFactory::setContainer($container);
        $app = AppFactory::create();

        $middlewares = $this->get('app:middlewares') ?? [];
        foreach ($middlewares as $middleware) {
            $middleware = $this->get($middleware);
            $app->add($middleware);
        }

        return $app;
    }

    /**
     * Create Sentry hub.
     *
     * @return ?SentryHub
     */
    private function createSentryHub (): ?SentryHub
    {
        $sentryDsn = $_ENV['SENTRY_DSN'] ?? null;
        if ($sentryDsn === null) {
            return null;
        }

        $sentryHub = new SentryHub();
        $sentryHub->bindClient(SentryClientBuilder::create([ 'dsn' => $sentryDsn ])->getClient());
        return $sentryHub;
    }

    /**
     * Add error middleware to slim application.
     *
     * @param SlimApp $app
     * @return void
     */
    private function addErrorMiddleware (SlimApp $app): void
    {
        $errorMiddleware = $app->addErrorMiddleware(($_ENV['APP_DEBUG'] ?? null) === 'true', true, true);
        $errorMiddleware->setDefaultErrorHandler(function (mixed ...$args) {
            return Application::$instance->handleThrowable($args[1], $args[2], $args[3], $args[5] ?? null);
        });
    }

    /**
     * Error handler.
     *
     * @param Throwable $throwable
     * @param bool $displayErrorDetails
     * @param bool $logErrors
     * @param LoggerInterface|null $logger
     * @return ResponseInterface
     */
    private function handleThrowable (Throwable $throwable, bool $displayErrorDetails, bool $logErrors, ?LoggerInterface $logger = null): ResponseInterface
    {
        $code = $throwable->getCode();
        if ($code < 400 || $code >= 600) {
            $code = 500;
        }

        $response = (new Response())->withStatus($code)->withHeader('Content-Type', 'application/json');
        if ($throwable instanceof Exceptable) {
            $payload = [
                'success' => false,
                'message' => $throwable->getMessage(),
                ...$throwable->getPayload()
            ];

            $response->getBody()->write(json_encode($payload));
            return $response;
        }

        if ($logErrors) {
            $this->sentryHub?->captureException($throwable);
            $logger?->error($throwable, [ 'exception' => $throwable ]);
        }

        $payload = [ 'success' => false ];
        if ($displayErrorDetails) {
            $payload['message'] = $throwable->getMessage();
            $payload['file'] = [
                'path' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ];
            $payload['trace'] = $throwable->getTrace();
        }

        $response->getBody()->write(json_encode($payload));
        return $response;
    }

    /**
     * Run application.
     *
     * @return never
     */
    public function run (): never
    {
        $this->registerRoutes();
        $this->app->run();
        exit();
    }

    /**
     * Register routes.
     *
     * @return void
     */
    private function registerRoutes (): void
    {
        require_once __DIR__ . '/Boot/Routes.php';
    }

    /**
     * Get dependency from container.
     *
     * @param string $key
     * @return mixed
     */
    public static function get (string $key): mixed
    {
        try {
            return self::$instance->container->get($key);
        }
        catch (Throwable) {
            return null;
        }
    }

    /**
     * Set dependency to container.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function set (string $key, mixed $value): void
    {
        self::$instance->container->set($key, $value);
    }

    /**
     * Check if dependency exists in container.
     *
     * @param string $key
     * @return bool
     */
    public static function has (string $key): bool
    {
        return self::$instance->container->has($key);
    }
}