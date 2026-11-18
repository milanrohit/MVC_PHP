<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

final class SqlHelperConnectionException extends AppException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = 'SqlHelper connection has not been initialized. Call setConnection() first.',
        int $status = 500,
        array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $context, $previous);
    }
}
