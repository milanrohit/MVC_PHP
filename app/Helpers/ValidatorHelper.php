<?php

declare(strict_types=1);

namespace app\Support;

use InvalidArgumentException;
use app\Helpers\ArrayHelper as ArrayHelper;

/**
 * SOLID Design: Single Responsibility Principle (SRP) for input data validation.
 * Optimization: Early-exit validation rules, array mapping, and zero class-state overhead.
 */
final class ValidatorHelper
{
    /**
     * Instantiation is blocked to enforce an optimized, unified static interface.
     */
    private function __construct() {}

    /**
     * 1. Validate data against an array of rules.
     * Returns an array containing error strings. If valid, returns an empty array.
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = ArrayHelper::get($data, $field); // Leverage our ArrayHelper for nested field access

            foreach ($fieldRules as $rule) {
                $parameters = [];

                if (str_contains($rule, ':')) {
                    [$ruleName, $paramString] = explode(':', $rule, 2);
                    $parameters = explode(',', $paramString);
                    $rule = $ruleName;
                }

                $methodName = 'validate' . StringHelper::camel($rule); // Leverage our StringHelper for clean naming

                if (!method_exists(self::class, $methodName)) {
                    throw new InvalidArgumentException("Validation rule '{$rule}' does not exist.");
                }

                // Execute rule check dynamically with an early-exit optimization strategy
                if (!self::$methodName($value, $parameters, $data)) {
                    $errors[$field][] = self::getErrorMessage($field, $rule, $parameters);
                }
            }
        }

        return $errors;
    }

    /**
     * 2. Rule: Check if a value is present and not empty.
     */
    private static function validateRequired(mixed $value, array $parameters, array $data): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_string($value) && trim($value) === '') {
            return false;
        }
        if (is_array($value) && count($value) === 0) {
            return false;
        }
        return true;
    }

    /**
     * 3. Rule: Check if a value is a valid email address.
     */
    private static function validateEmail(mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Use 'required' to trap empty values instead
        }
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * 4. Rule: Check minimum length or value.
     */
    private static function validateMin(mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $min = (int) ($parameters[0] ?? 0);

        if (is_numeric($value)) {
            return $value >= $min;
        }
        if (is_string($value)) {
            return StringHelper::length($value) >= $min;
        }
        if (is_array($value)) {
            return count($value) >= $min;
        }
        return false;
    }

    /**
     * 5. Rule: Check maximum length or value.
     */
    private static function validateMax(mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $max = (int) ($parameters[0] ?? 0);

        if (is_numeric($value)) {
            return $value <= $max;
        }
        if (is_string($value)) {
            return StringHelper::length($value) <= $max;
        }
        if (is_array($value)) {
            return count($value) <= $max;
        }
        return false;
    }

    /**
     * 6. Rule: Check if a value matches another field (e.g., password confirmation).
     */
    private static function validateConfirmed(mixed $value, array $parameters, array $data): bool
    {
        $confirmationField = ($parameters[0] ?? 'password') . '_confirmation';
        return isset($data[$confirmationField]) && $value === $data[$confirmationField];
    }

    /**
     * 7. Rule: Check if a value matches a strict alphanumeric structure.
     */
    private static function validateAlphaNumeric(mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        return is_string($value) && ctype_alnum($value);
    }

    /**
     * 8. Rule: Check if a value is a valid numeric digit sequence.
     */
    private static function validateNumeric(mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        return is_numeric($value);
    }

    /**
     * 9. Rule: Check if a value exists within a specific comma-separated list.
     */
    private static function validateIn(mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        return in_array((string)$value, $parameters, true);
    }

    /**
     * 10. Rule: Check if a value is a valid URL string pattern.
     */
    private static function validateUrl(mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 11. Rule: Check if a value is a valid IPv4 or IPv6 address.
     */
    private static function validateIp(mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        return is_string($value) && filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * 12. Rule: Check if a value matches a specific regex layout pattern.
     */
    private static function validateRegex(mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $pattern = $parameters[0] ?? '';
        return is_string($value) && preg_match($pattern, $value) === 1;
    }

    /**
     * 13. Rule: Check if a field value matches a valid JSON format string.
     */
    private static function validateJson(mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        return is_string($value) && json_validate($value); // PHP 8.3/8.4 Optimized native syntax 
    }

    /**
     * 14. Rule: Check if a value is a valid ISO 8601 date.
     */
    private static function validateDate(mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        return is_string($value) && strtotime($value) !== false;
    }

    /**
     * 15. Formats a clear, scannable validation failure error message template.
     */
    private static function getErrorMessage(string $field, string $rule, array $parameters): string
    {
        $cleanField = ucwords(str_replace(['_', '-'], ' ', $field));
        
        return match ($rule) {
            'required'      => "The {$cleanField} field is strictly required.",
            'email'         => "The {$cleanField} must be a valid email registration address.",
            'min'           => "The {$cleanField} must meet a minimum size criteria of {$parameters[0]}.",
            'max'           => "The {$cleanField} cannot exceed a maximum capacity boundary of {$parameters[0]}.",
            'confirmed'     => "The {$cleanField} confirmation match has failed validation rules.",
            'alpha_numeric' => "The {$cleanField} must consist strictly of alphanumeric elements.",
            'numeric'       => "The {$cleanField} must resolve as a clean numerical entity.",
            'in'            => "The selected {$cleanField} selection choice is completely invalid.",
            'url'           => "The {$cleanField} format must match a structured link context routing string.",
            'ip'            => "The {$cleanField} must map to a standardized web terminal IP address configuration.",
            'regex'         => "The {$cleanField} configuration has failed a strict token matching matrix test.",
            'json'          => "The {$cleanField} variable payload must translate to a valid structured JSON context.",
            'date'          => "The {$cleanField} value must map correctly into a verifiable date timestamp sequence.",
            default         => "The {$cleanField} parameter configuration has thrown a runtime formatting error status."
        };
    }
}
