<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

final class ConflictException extends AppException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = 'The request could not be completed due to a resource conflict.',
        array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 409, $context, $previous);
    }
}
