<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

final class AuthorizationException extends AppException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = 'You do not have permission to perform this action.',
        array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 403, $context, $previous);
    }
}
