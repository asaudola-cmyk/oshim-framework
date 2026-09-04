<?php
declare(strict_types=1);

namespace Oshim\Ui\Form;

/**
 * Declarative Pure PHP Form & Data Validator.
 */
class FormValidator
{
    private array $data;
    private array $rules;
    private array $errors = [];
    private array $validated = [];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function validate(): bool
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($this->rules as $field => $ruleSet) {
            $fieldRules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value = $this->data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$ruleName, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                } else {
                    $ruleName = $rule;
                }

                $this->applyRule($field, $value, $ruleName, $params);
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }

        return empty($this->errors);
    }

    private function applyRule(string $field, mixed $value, string $rule, array $params): void
    {
        switch ($rule) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                    $this->addError($field, "The {$field} field is required.");
                }
                break;

            case 'email':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "The {$field} must be a valid email address.");
                }
                break;

            case 'min':
                $min = (int)($params[0] ?? 0);
                if (is_string($value) && mb_strlen($value) < $min) {
                    $this->addError($field, "The {$field} must be at least {$min} characters.");
                } elseif (is_numeric($value) && $value < $min) {
                    $this->addError($field, "The {$field} must be at least {$min}.");
                } elseif (is_array($value) && count($value) < $min) {
                    $this->addError($field, "The {$field} must have at least {$min} items.");
                }
                break;

            case 'max':
                $max = (int)($params[0] ?? 0);
                if (is_string($value) && mb_strlen($value) > $max) {
                    $this->addError($field, "The {$field} may not be greater than {$max} characters.");
                } elseif (is_numeric($value) && $value > $max) {
                    $this->addError($field, "The {$field} may not be greater than {$max}.");
                } elseif (is_array($value) && count($value) > $max) {
                    $this->addError($field, "The {$field} may not have more than {$max} items.");
                }
                break;

            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    $this->addError($field, "The {$field} must be a number.");
                }
                break;

            case 'in':
                if ($value !== null && $value !== '' && !in_array((string)$value, $params, true)) {
                    $this->addError($field, "The selected {$field} is invalid.");
                }
                break;

            case 'same':
                $otherField = $params[0] ?? '';
                $otherValue = $this->data[$otherField] ?? null;
                if ($value !== $otherValue) {
                    $this->addError($field, "The {$field} and {$otherField} must match.");
                }
                break;

            case 'url':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, "The {$field} must be a valid URL.");
                }
                break;
        }
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function passes(): bool
    {
        return $this->validate();
    }

    public function fails(): bool
    {
        return !$this->validate();
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getValidated(): array
    {
        return $this->validated;
    }
}
