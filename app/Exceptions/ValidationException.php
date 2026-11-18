<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

final class ValidationException extends AppException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'The given data was invalid.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 422, ['errors' => $errors], $previous);
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
