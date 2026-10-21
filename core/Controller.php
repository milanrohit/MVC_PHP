<?php
declare(strict_types=1);

namespace Core;

abstract class Controller
{
    /** App\Frontend\Controllers\X -> app/Frontend/Views, App\Backend\... -> app/Backend/Views */
    protected function viewRoot(): string
    {
        $area = explode('\\', static::class)[1] ?? '';
        return BASE_PATH . '/app/' . $area . '/Views';
    }

    protected function render(string $view, array $data = [], string $layout = 'layouts/main'): Response
    {
        return Response::html(View::render($view, $data, $layout, $this->viewRoot()));
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
