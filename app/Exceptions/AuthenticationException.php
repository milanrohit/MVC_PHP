<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

final class AuthenticationException extends AppException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = 'Unauthenticated. Please log in to continue.',
        array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 401, $context, $previous);
    }
}
