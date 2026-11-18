<?php

declare(strict_types=1);

namespace App\Helpers;

use ReflectionClass;
use ReflectionMethod;

/**
 * Single Responsibility Principle (SRP) helper for runtime diagnostics and profiling.
 */
final class DebugHelper
{
    private const string MEMORY_FORMAT = '%.2f MB';

    private const string PRE_STYLE = 'background-color:#111217;color:#a9b2c3;padding:16px;'
        . 'border-radius:6px;font-family:Fira Code,Consolas,monospace;font-size:13px;'
        . 'line-height:1.6;overflow:auto;border-left:4px solid #4fc1ff;margin:12px 0;'
        . 'box-shadow:0 4px 6px rgba(0,0,0,0.1);';

    /** @var array<string, array{time: float, memory: int}> */
    private static array $timers = [];

    /**
     * Instantiation is blocked to enforce functional utility architecture.
     */
    private function __construct()
    {
    }

    /**
     * Dump variables with clean typography and continue execution.
     */
    public static function dump(mixed ...$vars): void
    {
        $isCli = PHP_SAPI === 'cli';
        $location = self::getCallerLocation(1);

        if ($isCli) {
            echo "\n\033[1;36m[Debug] {$location}\033[0m\n";
        } else {
            $escapedLocation = htmlspecialchars($location, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo '<pre style="' . self::PRE_STYLE . '">';
            echo '<strong style="color:#5c6370;">[Debug] ' . $escapedLocation . '</strong><br><br>';
        }

        foreach ($vars as $var) {
            $formatted = self::formatVariable($var);
            echo $isCli
                ? $formatted . "\n"
                : htmlspecialchars($formatted, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
        }

        if (!$isCli) {
            echo '</pre>';
        }
    }

    /**
     * Dump and Die: Inspect parameters and immediately terminate execution safely.
     */
    public static function dd(mixed ...$vars): never
    {
        self::dump(...$vars);
        exit(1);
    }

    /**
     * Start a high-precision performance execution micro-timer.
     */
    public static function startTimer(string $name): void
    {
        self::$timers[$name] = [
            'time' => microtime(true),
            'memory' => memory_get_usage(),
        ];
    }

    /**
     * Stop a micro-timer and return explicit execution metrics.
     *
     * @return array<string, string>
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
            'timer_name' => $name,
            'duration_ms' => sprintf('%.2f ms', ($endTime - $startData['time']) * 1000),
            'memory_used_kb' => sprintf('%.2f KB', ($endMemory - $startData['memory']) / 1024),
        ];
    }

    /**
     * Capture a lightweight backtrace avoiding reference cycles.
     *
     * @return list<array{file: string, line: int, call: string}>
     */
    public static function backtrace(int $limit = 5): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit + 1);
        array_shift($trace);

        return array_map(
            static fn (array $frame): array => [
                'file' => $frame['file'] ?? 'Closure/CoreEngine',
                'line' => $frame['line'] ?? 0,
                'call' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? 'unknown'),
            ],
            $trace,
        );
    }

    /**
     * Return a comprehensive breakdown of memory allocation thresholds.
     *
     * @return array<string, string>
     */
    public static function getMemoryProfile(): array
    {
        return [
            'current_allocated' => sprintf(self::MEMORY_FORMAT, memory_get_usage(false) / 1024 / 1024),
            'current_real_os' => sprintf(self::MEMORY_FORMAT, memory_get_usage(true) / 1024 / 1024),
            'peak_allocated' => sprintf(self::MEMORY_FORMAT, memory_get_peak_usage(false) / 1024 / 1024),
            'peak_real_os' => sprintf(self::MEMORY_FORMAT, memory_get_peak_usage(true) / 1024 / 1024),
        ];
    }

    /**
     * Inspect all public methods and properties available on an object instance via Reflection.
     *
     * @return array{class_name: class-string, properties: list<string>, methods: list<string>}
     */
    public static function inspectObject(object $object): array
    {
        $reflector = new ReflectionClass($object);
        $publicMethods = $reflector->getMethods(ReflectionMethod::IS_PUBLIC);

        return [
            'class_name' => $reflector->getName(),
            'properties' => array_map(static fn ($prop): string => $prop->getName(), $reflector->getProperties()),
            'methods' => array_map(static fn ($method): string => $method->getName(), $publicMethods),
        ];
    }

    /**
     * Write a structured debug log entry into the framework logs.
     */
    public static function log(mixed $message, string $level = 'DEBUG'): void
    {
        $logFile = dirname(__DIR__, 2) . '/storage/logs/debug.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        if (is_scalar($message)) {
            $formattedMessage = (string) $message;
        } else {
            $formattedMessage = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                ?: '[unserializable]';
        }

        $entry = sprintf(
            "[%s] [%s] %s (Location: %s)\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $formattedMessage,
            self::getCallerLocation(0),
        );

        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Benchmark the speed performance of a specific callback function.
     */
    public static function benchmark(callable $callback, int $iterations = 1000): string
    {
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $callback();
        }
        $end = microtime(true);
        $elapsed = $end - $start;

        return sprintf(
            'Executed %d times in %.4f seconds (Avg: %.6fs per run)',
            $iterations,
            $elapsed,
            $elapsed / max(1, $iterations),
        );
    }

    /**
     * Return all defined headers associated with the current HTTP incoming request.
     *
     * @return array<string, string>
     */
    public static function getRequestHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $serverVars = filter_input_array(INPUT_SERVER, FILTER_UNSAFE_RAW);
        if (!is_array($serverVars)) {
            return [];
        }

        $headers = [];
        foreach ($serverVars as $name => $value) {
            if (!is_string($name) || !is_string($value) || !str_starts_with($name, 'HTTP_')) {
                continue;
            }

            $headerKey = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$headerKey] = $value;
        }

        return $headers;
    }

    /**
     * Clear all ongoing output buffer content up to this statement execution.
     */
    public static function clearBuffers(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    private static function formatVariable(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded !== false ? $encoded : var_export($value, true);
    }

    private static function getCallerLocation(int $offset): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $offset + 2);
        $frame = $trace[$offset + 1] ?? [];
        $file = $frame['file'] ?? 'unknown';
        $line = $frame['line'] ?? 0;

        return "{$file}:{$line}";
    }
}
