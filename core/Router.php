<?php

declare(strict_types=1);

namespace Core;

final class Router
{
    /** @var array<string, list<array{0: string, 1: array<mixed>|callable}>> */
    private array $routes = [];

    /** @var list<class-string<MiddlewareInterface>> */
    private array $middleware = [];

    public function __construct(private readonly Container $container)
    {
    }

    public function get(string $path, array|callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array|callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, array|callable $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, array|callable $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    /**
     * @param class-string<MiddlewareInterface> $class
     */
    public function middleware(string $class): void
    {
        $this->middleware[] = $class;
    }

    public function dispatch(Request $request): Response
    {
        $core = fn (Request $r): Response => $this->resolve($r);

        // Build the onion: first registered middleware runs first.
        $pipeline = \array_reduce(
            \array_reverse($this->middleware),
            fn (callable $next, string $class): \Closure => fn (Request $r): Response => $this->container
                ->make($class)
                ->handle($r, $next),
            $core,
        );

        return $pipeline($request);
    }

    private function add(string $method, string $path, array|callable $handler): void
    {
        $this->routes[$method][] = [$this->compile('/' . \trim($path, '/')), $handler];
    }

    /**
     * Turns "/users/{id}" into an anchored regex. Static parts are escaped,
     * so "/file.php" cannot match "/filexphp"; "{id}" becomes a named capture group.
     */
    private function compile(string $path): string
    {
        $parts = \preg_split('#(\{[A-Za-z_]\w*\})#', $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY)
            ?: [$path];

        $regex = '';
        foreach ($parts as $part) {
            $regex .= \preg_match('#^\{([A-Za-z_]\w*)\}$#', $part, $name) === 1
                ? '(?P<' . $name[1] . '>[^/\r\n]+)'
                : \preg_quote($part, '#');
        }

        // "D" stops "$" from also matching before a trailing newline;
        // parameters exclude CR/LF so they cannot smuggle line breaks into logs or headers
        return '#^' . $regex . '$#D';
    }

    private function resolve(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        $match = $this->match($method, $path);
        if ($match !== null) {
            return $this->call($match[0], $request, $match[1]);
        }

        if ($this->existsForOtherMethod($method, $path)) {
            throw new HttpException(405, 'Method not allowed');
        }

        throw new HttpException(404, 'Page not found');
    }

    /**
     * @return array{0: array<mixed>|callable, 1: array<string, string>}|null handler and route parameters
     */
    private function match(string $method, string $path): ?array
    {
        foreach ($this->routes[$method] ?? [] as [$regex, $handler]) {
            if (\preg_match($regex, $path, $matches) === 1) {
                return [$handler, \array_filter($matches, '\is_string', ARRAY_FILTER_USE_KEY)];
            }
        }

        return null;
    }

    private function existsForOtherMethod(string $method, string $path): bool
    {
        foreach ($this->routes as $routeMethod => $routes) {
            if ($routeMethod === $method) {
                continue;
            }

            foreach ($routes as [$regex]) {
                if (\preg_match($regex, $path) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<mixed>|callable $handler
     * @param array<string, string> $params
     */
    private function call(array|callable $handler, Request $request, array $params): Response
    {
        $result = $this->toCallable($handler)($request, ...\array_values($params));

        return match (true) {
            $result instanceof Response => $result,
            \is_array($result) => Response::json($result),
            default => Response::html((string) $result),
        };
    }

    /**
     * ["Controller::class", "action"] is built through the container;
     * [$object, "action"], closures and function names are used as given.
     *
     * @param array<mixed>|callable $handler
     */
    private function toCallable(array|callable $handler): callable
    {
        if (\is_array($handler) && \is_string($handler[0] ?? null)) {
            [$class, $action] = $handler;

            return [$this->container->make($class), $action];
        }

        return $handler;
    }
}
