<?php
declare(strict_types=1);

use Core\Csrf;

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('base_path_url')) {
    function base_path_url(): string
    {
        return Core\Application::$request?->basePath() ?? '';
    }
}

if (!function_exists('url')) {
    function url(string $path = '/'): string
    {
        return base_path_url() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /** Files under public/ (e.g. build/js/app.js) */
    function asset(string $path): string
    {
        return url($path);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
    }
}
