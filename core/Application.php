<?php

declare(strict_types=1);

namespace Core;

use App\Middleware\CsrfMiddleware;
use App\Middleware\SecurityHeaders;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\UserRepository;
use PDO;
use Throwable;

final class Application
{
    private readonly Container $container;
    private static ?Request $request = null;

    public function __construct(private readonly string $basePath)
    {
        Env::load($basePath . '/.env');
        Config::load($basePath . '/config');
        $this->container = new Container();
        $this->registerBindings();
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function run(): void
    {
        $debug = (bool) Config::get('app.debug', false);
        ini_set('display_errors', '0');
        error_reporting(E_ALL);

        $handler = new ExceptionHandler($debug, $this->basePath . '/storage/logs');
        $handler->register();

        $request = Request::capture();
        self::$request = $request;

        try {
            Session::start();

            $router = new Router($this->container);
            $router->middleware(SecurityHeaders::class);
            $router->middleware(CsrfMiddleware::class);
            require_once $this->basePath . '/routes/web.php';

            $router->dispatch($request)->send();
        } catch (Throwable $e) {
            $handler->handle($e, $request)->send();
        }
    }

    public static function getRequest(): ?Request
    {
        return self::$request;
    }

    private function registerBindings(): void
    {
        $container = $this->container;
        $container->singleton(PDO::class, static fn (): PDO => Database::connection());
        $container->bind(UserRepositoryInterface::class, UserRepository::class);
    }
}
