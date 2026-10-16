<?php
declare(strict_types=1);

namespace Core;

final class Router
{
    private array $routes = [];
    /** @var class-string<MiddlewareInterface>[] */
    private array $middleware = [];

    public function __construct(private readonly Container $container) {}

    public function get(string $path, array|callable $handler): void    { $this->add('GET', $path, $handler); }
    public function post(string $path, array|callable $handler): void   { $this->add('POST', $path, $handler); }
    public function put(string $path, array|callable $handler): void    { $this->add('PUT', $path, $handler); }
    public function delete(string $path, array|callable $handler): void { $this->add('DELETE', $path, $handler); }

    public function middleware(string $class): void
    {
        $this->middleware[] = $class;
    }

    private function add(string $method, string $path, array|callable $handler): void
    {
        $path = '/' . trim($path, '/');
        // {id} -> named capture group
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $path) . '$#';
        $this->routes[$method][] = [$regex, $handler];
    }

    public function dispatch(Request $request): Response
    {
        $core = fn (Request $r): Response => $this->resolve($r);

        // Build the onion: first registered middleware runs first.
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn (callable $next, string $class) =>
                fn (Request $r): Response => $this->container->make($class)->handle($r, $next),
            $core
        );
        return $pipeline($request);
    }

    private function resolve(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes[$method] ?? [] as [$regex, $handler]) {
            if (preg_match($regex, $path, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                return $this->call($handler, $request, $params);
            }
        }

        foreach ($this->routes as $m => $list) {
            foreach ($list as [$regex]) {
                if ($m !== $method && preg_match($regex, $path)) {
                    throw new HttpException(405, 'Method not allowed');
                }
            }
        }
        throw new HttpException(404, 'Page not found');
    }

    private function call(array|callable $handler, Request $request, array $params): Response
    {
        if (is_array($handler)) {
            [$class, $action] = $handler;
            $handler = [$this->container->make($class), $action];
        }
        $result = $handler($request, ...array_values($params));

        return match (true) {
            $result instanceof Response => $result,
            is_array($result)           => Response::json($result),
            default                     => Response::html((string) $result),
        };
    }
}
