<?php
declare(strict_types=1);

namespace App\Support;

/**
 * $v = Validator::make($input, [
 *     'name'  => 'required|max:100',
 *     'email' => 'required|email',
 *     'age'   => 'numeric|min:18',
 * ]);
 * if ($v->fails()) { return $this->json(['errors' => $v->errors()], 422); }
 */
final class Validator
{
    private array $errors = [];

    private function __construct(private readonly array $data, private readonly array $rules)
    {
        $this->run();
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function fails(): bool  { return $this->errors !== []; }
    public function passes(): bool { return $this->errors === []; }
    public function errors(): array { return $this->errors; }

    /** Only the validated fields */
    public function validated(): array
    {
        return Arr::only($this->data, array_keys($this->rules));
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            $isEmpty = $value === null || (is_string($value) && trim($value) === '');

            foreach (explode('|', $ruleString) as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

                if ($name !== 'required' && $isEmpty) {
                    continue; // optional field left blank
                }
                $error = match ($name) {
                    'required' => $isEmpty ? "$field is required." : null,
                    'email'    => !filter_var($value, FILTER_VALIDATE_EMAIL) ? "$field must be a valid email." : null,
                    'numeric'  => !is_numeric($value) ? "$field must be a number." : null,
                    'min'      => $this->min($field, $value, (float) $param),
                    'max'      => $this->max($field, $value, (float) $param),
                    default    => null,
                };
                if ($error) {
                    $this->errors[$field][] = $error;
                    break; // first error per field is enough
                }
            }
        }
    }

    private function min(string $field, mixed $v, float $n): ?string
    {
        return (is_numeric($v) ? (float) $v < $n : mb_strlen((string) $v) < $n) ? "$field must be at least $n." : null;
    }

    private function max(string $field, mixed $v, float $n): ?string
    {
        return (is_numeric($v) ? (float) $v > $n : mb_strlen((string) $v) > $n) ? "$field must not exceed $n." : null;
    }
}
