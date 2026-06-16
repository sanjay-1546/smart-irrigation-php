<?php
declare(strict_types=1);

/**
 * Minimal input validator. Sanitizes strings (XSS) and validates required
 * fields / types. Use Validator::make($data, $rules) and check errors().
 */
class Validator
{
    private array $errors = [];

    public static function make(array $data, array $rules): self
    {
        $v = new self();
        foreach ($rules as $field => $ruleString) {
            $rulesArr = explode('|', $ruleString);
            $value = $data[$field] ?? null;

            foreach ($rulesArr as $rule) {
                $param = null;
                if (str_contains($rule, ':')) {
                    [$rule, $param] = explode(':', $rule, 2);
                }

                switch ($rule) {
                    case 'required':
                        if ($value === null || $value === '') {
                            $v->errors[$field][] = "$field is required";
                        }
                        break;
                    case 'email':
                        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $v->errors[$field][] = "$field must be a valid email";
                        }
                        break;
                    case 'numeric':
                        if ($value !== null && $value !== '' && !is_numeric($value)) {
                            $v->errors[$field][] = "$field must be numeric";
                        }
                        break;
                    case 'integer':
                        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                            $v->errors[$field][] = "$field must be an integer";
                        }
                        break;
                    case 'min':
                        if ($value !== null && is_numeric($value) && (float) $value < (float) $param) {
                            $v->errors[$field][] = "$field must be at least $param";
                        }
                        break;
                    case 'max':
                        if ($value !== null && is_numeric($value) && (float) $value > (float) $param) {
                            $v->errors[$field][] = "$field must be at most $param";
                        }
                        break;
                    case 'in':
                        $allowed = explode(',', (string) $param);
                        if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
                            $v->errors[$field][] = "$field must be one of: " . implode(', ', $allowed);
                        }
                        break;
                    case 'string':
                        if ($value !== null && !is_string($value)) {
                            $v->errors[$field][] = "$field must be a string";
                        }
                        break;
                }
            }
        }
        return $v;
    }

    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /** Strip tags / encode special chars to mitigate stored/reflected XSS. */
    public static function sanitizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }

    public static function sanitizeArray(array $data, array $stringFields): array
    {
        foreach ($stringFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = self::sanitizeString($data[$field]);
            }
        }
        return $data;
    }
}
