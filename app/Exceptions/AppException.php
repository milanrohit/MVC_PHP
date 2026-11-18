<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class AppException extends RuntimeException
{
    private readonly int $status;

    /** @var array<string, mixed> */
    private readonly array $context;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = 'Application error',
        int $status = 400,
        array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->status = $status;
        $this->context = $context;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getStatusCode(): int
    {
        return $this->getStatus();
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
