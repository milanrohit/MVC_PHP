<?php
declare(strict_types=1);

namespace Core;

final class Request
{
    private ?array $json = null;

    public function __construct(
        private readonly array $server,
        private readonly array $query,
        private readonly array $post,
        private readonly string $rawBody = ''
    ) {}

    public static function capture(): self
    {
        return new self($_SERVER, $_GET, $_POST, (string) file_get_contents('php://input'));
    }

    public function method(): string
    {
        $m = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        if ($m === 'POST' && isset($this->post['_method'])) {
            $override = strtoupper((string) $this->post['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }
        return $m;
    }

    /**
     * Base path as seen by the browser, e.g. "/my-mvc-project/public",
     * "/my-mvc-project" (XAMPP root .htaccess) or "" (Docker / virtual host).
     */
    public function basePath(): string
    {
        $configured = (string) Config::get('app.base_path', '');
        if ($configured !== '') {
            return '/' . trim($configured, '/');
        }

        $uriPath = parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($this->server['SCRIPT_NAME'] ?? '')), '/');

        $candidates = [$scriptDir];
        if (str_ends_with($scriptDir, '/public')) {
            $candidates[] = substr($scriptDir, 0, -7); // parent folder
        }
        foreach ($candidates as $c) {
            if ($c !== '' && ($uriPath === $c || str_starts_with($uriPath, $c . '/'))) {
                return $c;
            }
        }
        return '';
    }

    /** Request path with base path removed: always starts with "/". */
    public function path(): string
    {
        $uriPath = parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = $this->basePath();
        if ($base !== '' && str_starts_with($uriPath, $base)) {
            $uriPath = substr($uriPath, strlen($base));
        }
        $uriPath = '/' . trim($uriPath, '/');
        return $uriPath === '' ? '/' : $uriPath;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json());
    }

    public function json(): array
    {
        if ($this->json === null) {
            $decoded = [];
            if (str_contains($this->header('Content-Type') ?? '', 'application/json') && $this->rawBody !== '') {
                $decoded = json_decode($this->rawBody, true);
            }
            $this->json = is_array($decoded) ? $decoded : [];
        }
        return $this->json;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if ($name === 'Content-Type') {
            $key = 'CONTENT_TYPE';
        }
        return $this->server[$key] ?? null;
    }

    public function wantsJson(): bool
    {
        return str_contains($this->header('Accept') ?? '', 'application/json')
            || str_starts_with($this->path(), '/api/');
    }
}
