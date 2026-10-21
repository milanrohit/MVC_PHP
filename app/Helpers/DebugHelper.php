<?php

declare(strict_types=1);

namespace App\Helpers;

use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

/**
 * SOLID Design: Single Responsibility Principle (SRP) for granular runtime diagnostics and profiling.
 * Optimization: Memory-safe backtracing, static internal timers, and output buffer management.
 */
final class DebugHelper
{
    private const MEMORY_FORMAT = '%.2f MB';

    private static array $timers = [];

    /**
     * Instantiation blocked to enforce functional utility architecture.
     */
    private function __construct() {}

    /**
     * 1. Dump variables beautifully with clean typography and continue execution.
     */
    public static function dump(mixed ...$vars): void
    {
        $isCli = PHP_SAPI === 'cli';
        $location = self::getCallerLocation(1);

        if (!$isCli) {
            echo '<pre style="background-color: #111217; color: #a9b2c3; padding: 16px; border-radius: 6px; font-family: Fira Code, Consolas, monospace; font-size: 13px; line-height: 1.6; overflow: auto; border-left: 4px solid #4fc1ff; margin: 12px 0; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">';
            echo '<strong style="color: #5c6370;">[Debug] ' . htmlspecialchars($location, ENT_QUOTES, 'UTF-8') . '</strong><br><br>';
        } else {
            echo "\n\033[1;36m[Debug] {$location}\033[0m\n";
        }

        foreach ($vars as $var) {
            if ($isCli) {
                var_dump($var);
            } else {
                echo htmlspecialchars(print_r($var, true), ENT_QUOTES, 'UTF-8') . "\n";
            }
        }

        if (!$isCli) {
            echo '</pre>';
        }
    }

    /**
     * 2. Dump and Die: Inspect parameters and immediately terminate execution safely.
     */
    public static function dd(mixed ...$vars): void
    {
        self::dump(...$vars);
        exit(1);
    }

    /**
     * 3. Start a high-precision performance execution micro-timer.
     */
    public static function startTimer(string $name): void
    {
        self::$timers[$name] = [
            'time' => microtime(true),
            'memory' => memory_get_usage()
        ];
    }

    /**
     * 4. Stop a micro-timer and return explicit execution metrics.
     */
    public static function stopTimer(string $name): array
    {
        if (!isset(self::$timers[$name])) {
            return ['error' => "Timer '{$name}' was never initialized."];
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        $startData = self::$timers[$name];

        unset(self::$timers[$name]);

        return [
            'timer_name'     => $name,
            'duration_ms'    => sprintf('%.2f ms', ($endTime - $startData['time']) * 1000),
            'memory_used_kb' => sprintf('%.2f KB', ($endMemory - $startData['memory']) / 1024),
        ];
    }

    /**
     * 5. Capture a lightweight backtrace avoiding reference cycles.
     */
    public static function backtrace(int $limit = 5): array
    {
        // PHP 8.4 safe bitmask ensures memory footprint remains tiny by ignoring object arguments
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit + 1);
        array_shift($trace); // Remove DebugHelper trace framework frame

        return array_map(fn($frame) => [
            'file'     => $frame['file'] ?? 'Closure/CoreEngine',
            'line'     => $frame['line'] ?? 0,
            'call'     => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? 'unknown')
        ], $trace);
    }

    /**
     * 6. Return a comprehensive breakdown of memory allocation thresholds.
     */
    public static function getMemoryProfile(): array
    {
        return [
            'current_allocated' => sprintf(self::MEMORY_FORMAT, memory_get_usage(false) / 1024 / 1024),
            'current_real_os'   => sprintf(self::MEMORY_FORMAT, memory_get_usage(true) / 1024 / 1024),
            'peak_allocated'    => sprintf(self::MEMORY_FORMAT, memory_get_peak_usage(false) / 1024 / 1024),
            'peak_real_os'      => sprintf(self::MEMORY_FORMAT, memory_get_peak_usage(true) / 1024 / 1024),
        ];
    }

    /**
     * 7. Inspect all public methods and properties available on an object instance via Reflection.
     */
    public static function inspectObject(object $object): array
    {
        $reflector = new ReflectionClass($object);
        
        return [
            'class_name' => $reflector->getName(),
            'properties' => array_map(fn($prop) => $prop->getName(), $reflector->getProperties()),
            'methods'    => array_map(fn($method) => $method->getName(), $reflector->getMethods(ReflectionMethod::IS_PUBLIC)),
        ];
    }

    /**
     * 8. Write a high-performance raw payload execution block entry into the framework logs.
     */
    public static function log(mixed $message, string $level = 'DEBUG'): void
    {
        $logFile = dirname(__DIR__, 2) . '/storage/logs/debug.log';
        
        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }

        $entry = sprintf(
            "[%s] [%s] %s (Location: %s)\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            is_scalar($message) ? (string)$message : json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            self::getCallerLocation(0)
        );

        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * 9. Benchmark the speed performance of a specific callback function.
     */
    public static function benchmark(callable $callback, int $iterations = 1000): string
    {
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $callback();
        }
        $end = microtime(true);

        return sprintf('Executed %d times in %.4f seconds (Avg: %.6fs per run)', $iterations, $end - $start, ($end - $start) / $iterations);
    }

    /**
     * 10. Print out all defined headers associated with the current HTTP incoming context request payload.
     */
    public static function getRequestHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

    /**
     * 11. Clear all ongoing output buffer content up to this statement execution.
     */
    public static function clearBuffers(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    /**
     * 12. Evaluate if the framework script execution context is executing natively inside CLI.
     */
    public static function isCli(): bool
    {
        return PHP_SAPI === 'cli';
    }

    /**
     * 13. Returns an array list of all files loaded or required into execution engine memory up to this frame.
     */
    public static function getIncludedFiles(): array
    {
        return array_map('basename', get_included_files());
    }

    /**
     * 14. Check if a specific PHP extension is loaded and active.
     */
    public static function checkExtension(string $extensionName): bool
    {
        return extension_loaded($extensionName);
    }

    /**
     * 15. Helper trace mapping function matching context origins dynamically.
     */
    private static function getCallerLocation(int $depth = 0): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth + 3);
        $frame = $trace[$depth + 1] ?? ($trace[$depth] ?? null);

        if ($frame && isset($frame['file'])) {
            return basename($frame['file']) . ':' . $frame['line'];
        }

        return 'System Core Context';
    }
}
