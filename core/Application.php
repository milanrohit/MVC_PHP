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
    public static ?Request $request = null;
    public readonly Container $container;

    public function __construct(private readonly string $basePath)
    {
        Env::load($basePath . '/.env');
        Config::load($basePath . '/config');
        $this->container = new Container();
        $this->registerBindings();
    }

    private function registerBindings(): void
    {
        $c = $this->container;
        $c->singleton(PDO::class, fn () => Database::connection());
        $c->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    public function run(): void
    {
        $debug = (bool) Config::get('app.debug', false);
        ini_set('display_errors', '0');
        error_reporting(E_ALL);

        $handler = new ExceptionHandler($debug, $this->basePath . '/storage/logs');
        $handler->register();

        $request = self::$request = Request::capture();

        try {
            Session::start();

            $router = new Router($this->container);
            $router->middleware(SecurityHeaders::class);
            $router->middleware(CsrfMiddleware::class);
            require $this->basePath . '/routes/web.php';

            $router->dispatch($request)->send();
        } catch (Throwable $e) {
            $handler->handle($e, $request)->send();
        }
    }
}
