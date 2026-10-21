<?php
declare(strict_types=1);

namespace Core;

use RuntimeException;

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = null, ?string $root = null): string
    {
        $content = self::capture($view, $data, $root);
        return $layout ? self::capture($layout, $data + ['content' => $content], $root) : $content;
    }

    private static function capture(string $view, array $data, ?string $root): string
    {
        // Area folder first (Frontend/Backend), then the old shared app/Views as fallback
        foreach (array_filter([$root, BASE_PATH . '/app/Views']) as $dir) {
            $file = $dir . '/' . $view . '.php';
            if (is_file($file)) {
                extract($data, EXTR_SKIP);
                ob_start();
                require_once $file;
                return (string) ob_get_clean();
            }
        }
        throw new RuntimeException("View [$view] not found in [" . ($root ?? 'app/Views') . "].");
    }
}
