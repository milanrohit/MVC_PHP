<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

final class DomainException extends AppException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = 'A business rule violation occurred.',
        int $status = 400,
        array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $context, $previous);
    }
}
