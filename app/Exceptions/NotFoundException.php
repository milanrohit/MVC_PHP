<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

final class NotFoundException extends AppException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = 'The requested resource was not found.',
        array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 404, $context, $previous);
    }

    public static function forResource(string $resource, int|string $identifier): self
    {
        return new self(
            \sprintf('%s with identifier [%s] was not found.', $resource, (string) $identifier),
            ['resource' => $resource, 'id' => $identifier],
        );
    }
}
