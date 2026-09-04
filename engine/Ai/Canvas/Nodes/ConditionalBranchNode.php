<?php
declare(strict_types=1);

namespace Oshim\Ai\Canvas\Nodes;

use Oshim\Ai\Canvas\AbstractNode;

/**
 * Conditional Branch Node: Predicate evaluation and dynamic edge routing.
 */
class ConditionalBranchNode extends AbstractNode
{
    protected string $type = 'conditional_branch';
    protected string $title = 'Conditional Branch';

    protected function definePorts(): void
    {
        $this->registerInputPort('value', 'any', 'Value or payload object to evaluate', false, null);
        $this->registerInputPort('condition_key', 'string', 'Key to extract from value/context', false, null);

        $this->registerOutputPort('selected_branch', 'string', 'Target branch or node identifier resolved');
        $this->registerOutputPort('matched_rule', 'array', 'Rule definition that satisfied condition');
        $this->registerOutputPort('result', 'bool', 'Boolean outcome of condition');
        $this->registerOutputPort('true_branch', 'any', 'Payload passed if condition holds true');
        $this->registerOutputPort('false_branch', 'any', 'Payload passed if condition holds false');
        $this->registerOutputPort('payload', 'any', 'Passthrough of original input data');
    }

    protected function process(array $inputs): array
    {
        $rules = (array)($this->getConfigValue('rules', []));
        $defaultTarget = (string)($this->getConfigValue('default_target', 'default'));

        $conditionKey = (string)($inputs['condition_key'] ?? $this->getConfigValue('condition_key', ''));
        $targetValue = $this->getConfigValue('value', null);
        $operator = (string)($this->getConfigValue('operator', '=='));

        // If specific multi-rule list is provided in config
        if (!empty($rules)) {
            foreach ($rules as $rule) {
                if (!is_array($rule)) {
                    continue;
                }
                $key = (string)($rule['key'] ?? '');
                $op = (string)($rule['op'] ?? '==');
                $expected = $rule['value'] ?? true;
                $target = (string)($rule['target'] ?? 'true');

                $actual = $this->extractValue($inputs, $key);

                if ($this->evaluateCondition($actual, $op, $expected)) {
                    return [
                        'selected_branch' => $target,
                        'matched_rule' => $rule,
                        'result' => true,
                        'true_branch' => $inputs,
                        'false_branch' => null,
                        'payload' => $inputs,
                    ];
                }
            }

            return [
                'selected_branch' => $defaultTarget,
                'matched_rule' => null,
                'result' => false,
                'true_branch' => null,
                'false_branch' => $inputs,
                'payload' => $inputs,
            ];
        }

        // Single condition evaluation
        $actual = !empty($conditionKey) ? $this->extractValue($inputs, $conditionKey) : ($inputs['value'] ?? null);
        $matched = $this->evaluateCondition($actual, $operator, $targetValue);
        $selectedBranch = $matched ? (string)$this->getConfigValue('true_target', 'true') : (string)$this->getConfigValue('false_target', $defaultTarget);

        return [
            'selected_branch' => $selectedBranch,
            'matched_rule' => ['op' => $operator, 'expected' => $targetValue, 'actual' => $actual],
            'result' => $matched,
            'true_branch' => $matched ? $inputs : null,
            'false_branch' => !$matched ? $inputs : null,
            'payload' => $inputs,
        ];
    }

    private function extractValue(array $context, string $key): mixed
    {
        if (empty($key)) {
            return $context['value'] ?? $context;
        }

        if (array_key_exists($key, $context)) {
            return $context[$key];
        }

        // Check if value is nested array
        if (isset($context['value']) && is_array($context['value']) && array_key_exists($key, $context['value'])) {
            return $context['value'][$key];
        }

        // Dot notation support
        $parts = explode('.', $key);
        $current = $context;
        foreach ($parts as $part) {
            if (is_array($current) && array_key_exists($part, $current)) {
                $current = $current[$part];
            } else {
                return null;
            }
        }

        return $current;
    }

    private function evaluateCondition(mixed $actual, string $op, mixed $expected): bool
    {
        return match (strtolower($op)) {
            '==', 'equals', 'eq' => $actual == $expected,
            '===', 'identical' => $actual === $expected,
            '!=', '<>', 'neq' => $actual != $expected,
            '!==', 'not_identical' => $actual !== $expected,
            '>', 'gt' => is_numeric($actual) && is_numeric($expected) && (float)$actual > (float)$expected,
            '>=', 'gte' => is_numeric($actual) && is_numeric($expected) && (float)$actual >= (float)$expected,
            '<', 'lt' => is_numeric($actual) && is_numeric($expected) && (float)$actual < (float)$expected,
            '<=', 'lte' => is_numeric($actual) && is_numeric($expected) && (float)$actual <= (float)$expected,
            'contains' => is_string($actual) && is_string($expected) && str_contains(strtolower($actual), strtolower($expected)),
            'not_contains' => is_string($actual) && is_string($expected) && !str_contains(strtolower($actual), strtolower($expected)),
            'regex', 'match' => is_string($actual) && is_string($expected) && (bool)@preg_match($expected, $actual),
            'in' => is_array($expected) && in_array($actual, $expected, false),
            'not_in' => is_array($expected) && !in_array($actual, $expected, false),
            'truthy' => !empty($actual),
            'falsy', 'empty' => empty($actual),
            'not_empty' => !empty($actual),
            default => $actual == $expected,
        };
    }
}
