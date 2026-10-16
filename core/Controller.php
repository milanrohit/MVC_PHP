<?php
declare(strict_types=1);

namespace Core;

/** Base controller: rendering + JSON helpers. CSRF is enforced by CsrfMiddleware. */
abstract class Controller
{
    protected function render(string $view, array $data = [], string $layout = 'layouts/main'): Response
    {
        return Response::html(View::render($view, $data, $layout));
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect(url($path));
    }

    protected function csrfToken(): string
    {
        return Csrf::token();
    }
}
