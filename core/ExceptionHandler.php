<?php
declare(strict_types=1);

namespace Core;

use Throwable;

final class ExceptionHandler
{
    public function __construct(private readonly bool $debug, private readonly string $logDir) {}

    public function register(): void
    {
        set_exception_handler(fn (Throwable $e) => $this->handle($e, null)->send());
        set_error_handler(function (int $no, string $str, string $file, int $line) {
            if (!(error_reporting() & $no)) {
                return false;
            }
            throw new \ErrorException($str, 0, $no, $file, $line);
        });
    }

    public function handle(Throwable $e, ?Request $request): Response
    {
        $status = $e instanceof HttpException ? $e->status : 500;

        if ($status >= 500) {
            $this->log($e);
        }

        $message = ($this->debug || $status < 500) ? $e->getMessage() : 'Server error';
        $json = $request?->wantsJson() ?? false;

        if ($json) {
            $payload = ['error' => $message];
            if ($this->debug && $status >= 500) {
                $payload['trace'] = explode("\n", $e->getTraceAsString());
            }
            return Response::json($payload, $status);
        }

        $body = '<!doctype html><meta charset="utf-8"><title>' . $status . '</title>'
            . '<body style="font-family:system-ui;padding:3rem"><h1>' . $status . '</h1><p>' . e($message) . '</p>';
        if ($this->debug && $status >= 500) {
            $body .= '<pre style="background:#f4f4f5;padding:1rem;overflow:auto">' . e($e->getTraceAsString()) . '</pre>';
        }
        return Response::html($body, $status);
    }

    private function log(Throwable $e): void
    {
        if (!is_dir($this->logDir)) {
            return;
        }
        $line = sprintf("[%s] %s: %s in %s:%d\n", date('c'), $e::class, $e->getMessage(), $e->getFile(), $e->getLine());
        @file_put_contents($this->logDir . '/app.log', $line, FILE_APPEND | LOCK_EX);
    }
}
