<?php

declare(strict_types=1);

namespace App\Helpers;

use InvalidArgumentException;

/**
 * Single Responsibility Principle (SRP) helper for input data validation.
 */
final class ValidatorHelper
{
    /** @var array<string, string> */
    private const array ERROR_TEMPLATES = [
        'required' => 'The :field field is required.',
        'email' => 'The :field must be a valid email address.',
        'min' => 'The :field must be at least :param0.',
        'max' => 'The :field may not be greater than :param0.',
        'confirmed' => 'The :field confirmation does not match.',
        'alpha_numeric' => 'The :field may only contain letters and numbers.',
        'numeric' => 'The :field must be a valid number.',
        'in' => 'The selected :field is invalid.',
        'url' => 'The :field format is invalid.',
        'ip' => 'The :field must be a valid IP address.',
        'regex' => 'The :field format is invalid.',
        'json' => 'The :field must be a valid JSON string.',
        'date' => 'The :field is not a valid date.',
    ];

    /**
     * Instantiation is blocked to enforce an optimized, unified static interface.
     */
    private function __construct()
    {
    }

    /**
     * Validate data against an array of rules.
     *
     * @param array<string, mixed> $data
     * @param array<string, list<string>> $rules
     * @return array<string, list<string>>
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = ArrayHelper::get($data, $field);

            foreach ($fieldRules as $rawRule) {
                [$ruleName, $parameters] = self::parseRule($rawRule);

                if (!self::evaluateRule($ruleName, $value, $parameters, $data)) {
                    $errors[$field][] = self::getErrorMessage($field, $ruleName, $parameters);
                }
            }
        }

        return $errors;
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private static function parseRule(string $rawRule): array
    {
        if (!str_contains($rawRule, ':')) {
            return [$rawRule, []];
        }

        [$ruleName, $paramString] = explode(':', $rawRule, 2);

        return [$ruleName, explode(',', $paramString)];
    }

    /**
     * @param list<string> $parameters
     * @param array<string, mixed> $data
     */
    private static function evaluateRule(string $rule, mixed $value, array $parameters, array $data): bool
    {
        return match ($rule) {
            'required' => self::validateRequired($value),
            'email' => self::validateEmail($value),
            'min' => self::validateMin($value, $parameters),
            'max' => self::validateMax($value, $parameters),
            'confirmed' => self::validateConfirmed($value, $parameters, $data),
            'alpha_numeric' => self::validateAlphaNumeric($value),
            'numeric' => self::validateNumeric($value),
            'in' => self::validateIn($value, $parameters),
            'url' => self::validateUrl($value),
            'ip' => self::validateIp($value),
            'regex' => self::validateRegex($value, $parameters),
            'json' => self::validateJson($value),
            'date' => self::validateDate($value),
            default => throw new InvalidArgumentException("Validation rule '{$rule}' does not exist."),
        };
    }

    private static function validateRequired(mixed $value): bool
    {
        return match (true) {
            $value === null => false,
            is_string($value) && trim($value) === '' => false,
            is_array($value) && count($value) === 0 => false,
            default => true,
        };
    }

    private static function validateEmail(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** @param list<string> $parameters */
    private static function validateMin(mixed $value, array $parameters): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $min = (int) ($parameters[0] ?? 0);

        return match (true) {
            is_numeric($value) => $value >= $min,
            is_string($value) => StringHelper::length($value) >= $min,
            is_array($value) => count($value) >= $min,
            default => false,
        };
    }

    /** @param list<string> $parameters */
    private static function validateMax(mixed $value, array $parameters): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $max = (int) ($parameters[0] ?? 0);

        return match (true) {
            is_numeric($value) => $value <= $max,
            is_string($value) => StringHelper::length($value) <= $max,
            is_array($value) => count($value) <= $max,
            default => false,
        };
    }

    /**
     * @param list<string> $parameters
     * @param array<string, mixed> $data
     */
    private static function validateConfirmed(mixed $value, array $parameters, array $data): bool
    {
        $confirmationField = ($parameters[0] ?? '') !== ''
            ? $parameters[0]
            : 'password_confirmation';

        return isset($data[$confirmationField]) && $value === $data[$confirmationField];
    }

    private static function validateAlphaNumeric(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return is_string($value) && ctype_alnum($value);
    }

    private static function validateNumeric(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return is_numeric($value);
    }

    /** @param list<string> $parameters */
    private static function validateIn(mixed $value, array $parameters): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return in_array((string) $value, $parameters, true);
    }

    private static function validateUrl(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private static function validateIp(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return is_string($value) && filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /** @param list<string> $parameters */
    private static function validateRegex(mixed $value, array $parameters): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $pattern = $parameters[0] ?? '';

        return is_string($value) && preg_match($pattern, $value) === 1;
    }

    private static function validateJson(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return is_string($value) && json_validate($value);
    }

    private static function validateDate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return is_string($value) && strtotime($value) !== false;
    }

    /**
     * Formats a clear, scannable validation failure error message template.
     *
     * @param list<string> $parameters
     */
    private static function getErrorMessage(string $field, string $rule, array $parameters): string
    {
        $cleanField = ucwords(str_replace(['_', '.'], ' ', $field));
        $template = self::ERROR_TEMPLATES[$rule] ?? 'The :field field is invalid.';

        return str_replace(
            [':field', ':param0', ':params'],
            [$cleanField, $parameters ?? '', implode(', ', $parameters)],
            $template,
        );
    }
}
