<?php

declare(strict_types=1);

namespace Core;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class View
{
    private const string VALID_VAR_PATTERN = '/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/';

    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], ?string $layout = null, ?string $root = null): string
    {
        $content = self::capture($view, $data, $root);

        if ($layout !== null && $layout !== '') {
            return self::capture($layout, ['content' => $content] + $data, $root);
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function capture(string $view, array $data, ?string $root): string
    {
        if (str_contains($view, '..')) {
            throw new InvalidArgumentException(\sprintf('Invalid view path [%s].', $view));
        }

        $directories = array_filter(
            [$root, BASE_PATH . '/app/Views'],
            static fn (?string $dir): bool => $dir !== null && $dir !== '',
        );

        foreach ($directories as $dir) {
            $file = rtrim($dir, '/\\') . '/' . ltrim($view, '/\\') . '.php';
            if (is_file($file)) {
                return self::evaluateTemplate($file, $data);
            }
        }

        throw new RuntimeException(\sprintf('View [%s] not found in [%s].', $view, $root ?? 'app/Views'));
    }

    /**
     * @param array<string, mixed> $__data
     */
    private static function evaluateTemplate(string $__file, array $__data): string
    {
        $renderer = static function (string $__templatePath, array $__variables): void {
            foreach ($__variables as $__key => $__value) {
                if (
                    !\is_string($__key)
                    || $__key === '__templatePath'
                    || $__key === '__variables'
                    || preg_match(self::VALID_VAR_PATTERN, $__key) !== 1
                ) {
                    continue;
                }

                ${$__key} = $__value;
            }

            require $__templatePath;
        };

        ob_start();

        try {
            $renderer($__file, $__data);

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            throw $e;
        }
    }
}
