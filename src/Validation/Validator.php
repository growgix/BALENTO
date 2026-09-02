<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * Reusable strict Input Validator.
 */
final class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    public function required(string $field, ?string $message = null): self
    {
        $val = $this->data[$field] ?? null;
        if ($val === null || (is_string($val) && trim($val) === '') || (is_array($val) && empty($val))) {
            $this->addError($field, $message ?? "The {$field} field is required.");
        }
        return $this;
    }

    public function pincode(string $field, ?string $message = null): self
    {
        $val = (string) ($this->data[$field] ?? '');
        if (!preg_match('/^[1-9][0-9]{5}$/', trim($val))) {
            $this->addError($field, $message ?? "Please provide a valid 6-digit Indian PIN code.");
        }
        return $this;
    }

    public function email(string $field, ?string $message = null): self
    {
        $val = (string) ($this->data[$field] ?? '');
        if (!filter_var(trim($val), FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, $message ?? "Please provide a valid email address.");
        }
        return $this;
    }

    public function phone(string $field, ?string $message = null): self
    {
        $val = preg_replace('/[^0-9]/', '', (string) ($this->data[$field] ?? ''));
        // Indian phone: 10 digits or 12 digits with 91 prefix
        if (strlen($val) === 12 && str_starts_with($val, '91')) {
            $val = substr($val, 2);
        }
        if (!preg_match('/^[6-9][0-9]{9}$/', $val)) {
            $this->addError($field, $message ?? "Please provide a valid 10-digit Indian mobile number.");
        }
        return $this;
    }

    public function minLength(string $field, int $min, ?string $message = null): self
    {
        $val = (string) ($this->data[$field] ?? '');
        if (mb_strlen(trim($val)) < $min) {
            $this->addError($field, $message ?? "The {$field} must be at least {$min} characters.");
        }
        return $this;
    }

    public function maxLength(string $field, int $max, ?string $message = null): self
    {
        $val = (string) ($this->data[$field] ?? '');
        if (mb_strlen(trim($val)) > $max) {
            $this->addError($field, $message ?? "The {$field} may not exceed {$max} characters.");
        }
        return $this;
    }

    public function numeric(string $field, ?string $message = null): self
    {
        $val = $this->data[$field] ?? null;
        if ($val !== null && !is_numeric($val)) {
            $this->addError($field, $message ?? "The {$field} must be a valid number.");
        }
        return $this;
    }

    public function inArray(string $field, array $allowed, ?string $message = null): self
    {
        $val = $this->data[$field] ?? null;
        if ($val !== null && !in_array($val, $allowed, true)) {
            $this->addError($field, $message ?? "The selected {$field} is invalid. Allowed: " . implode(', ', $allowed));
        }
        return $this;
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }
}
