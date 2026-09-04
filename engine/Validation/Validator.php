<?php
declare(strict_types=1);

namespace Oshim\Validation;

use Oshim\Database\ConnectionManager;
use InvalidArgumentException;

/**
 * 👑 Sovereign Validation Engine
 * 
 * WHY: Validates untrusted user input elegantly. 
 * Supports pipelined string rules (e.g., 'required|email|min:8|unique:users,email').
 */
class Validator
{
    protected array $errors = [];
    protected array $data = [];
    protected array $rules = [];

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

        foreach ($this->rules as $field => $fieldRules) {
            $rulesArray = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $value = $this->data[$field] ?? null;

            foreach ($rulesArray as $ruleStr) {
                // Parse rule and parameters (e.g. min:8)
                $params = [];
                if (str_contains($ruleStr, ':')) {
                    [$ruleName, $paramStr] = explode(':', $ruleStr, 2);
                    $params = explode(',', $paramStr);
                } else {
                    $ruleName = $ruleStr;
                }

                // If field is not required and is empty, skip other rules
                if ($ruleName !== 'required' && empty($value) && $value !== '0' && $value !== 0) {
                    continue;
                }

                $this->applyRule($field, $value, $ruleName, $params);
            }
        }

        return empty($this->errors);
    }

    public function passes(): bool
    {
        return $this->validate();
    }

    public function fails(): bool
    {
        return !$this->validate();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    protected function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    protected function applyRule(string $field, mixed $value, string $rule, array $params): void
    {
        switch ($rule) {
            case 'required':
                if ($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && empty($value))) {
                    $this->addError($field, "The {$field} field is required.");
                }
                break;

            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "The {$field} must be a valid email address.");
                }
                break;

            case 'min':
                $min = (float)($params[0] ?? 0);
                if (is_numeric($value) && $value < $min) {
                    $this->addError($field, "The {$field} must be at least {$min}.");
                } elseif (is_string($value) && mb_strlen($value) < $min) {
                    $this->addError($field, "The {$field} must be at least {$min} characters.");
                }
                break;

            case 'max':
                $max = (float)($params[0] ?? 0);
                if (is_numeric($value) && $value > $max) {
                    $this->addError($field, "The {$field} must not be greater than {$max}.");
                } elseif (is_string($value) && mb_strlen($value) > $max) {
                    $this->addError($field, "The {$field} must not be greater than {$max} characters.");
                }
                break;

            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, "The {$field} must be a number.");
                }
                break;

            case 'string':
                if (!is_string($value)) {
                    $this->addError($field, "The {$field} must be a string.");
                }
                break;

            case 'unique':
                // Format: unique:table,column,ignore_id
                $table = $params[0] ?? null;
                $column = $params[1] ?? $field;
                $ignoreId = $params[2] ?? null;

                if (!$table) {
                    throw new InvalidArgumentException("Unique rule requires a table name.");
                }

                // Edge Case: Lazy load DB connection only when rule is triggered
                $db = ConnectionManager::getInstance()->connection();
                
                $query = "SELECT count(*) as count FROM {$table} WHERE {$column} = ?";
                $bindings = [$value];
                
                if ($ignoreId) {
                    $query .= " AND id != ?";
                    $bindings[] = $ignoreId;
                }
                
                $result = $db->selectOne($query, $bindings);
                if (($result['count'] ?? 0) > 0) {
                    $this->addError($field, "The {$field} has already been taken.");
                }
                break;
                
            default:
                throw new InvalidArgumentException("Unsupported validation rule: {$rule}");
        }
    }
}
