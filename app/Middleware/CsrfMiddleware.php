<?php
declare(strict_types=1);

namespace App\Middleware;

use Core\Csrf;
use Core\HttpException;
use Core\MiddlewareInterface;
use Core\Request;
use Core\Response;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = $request->header('X-CSRF-TOKEN') ?? (string) $request->input('_token', '');
            if (!Csrf::verify($token)) {
                throw new HttpException(419, 'CSRF token mismatch');
            }
        }
        return $next($request);
    }
}
