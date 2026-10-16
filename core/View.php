<?php
declare(strict_types=1);

namespace Core;

use RuntimeException;

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = null): string
    {
        $content = self::capture($view, $data);
        return $layout ? self::capture($layout, $data + ['content' => $content]) : $content;
    }

    private static function capture(string $view, array $data): string
    {
        $file = BASE_PATH . '/app/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("View [$view] not found.");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
