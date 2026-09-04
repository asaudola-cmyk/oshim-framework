<?php
declare(strict_types=1);

namespace Oshim\GraphQL;

use Closure;
use RuntimeException;

class GraphQLServer
{
    private array $typeDefs = [];
    private array $resolvers = [
        'Query' => [],
        'Mutation' => [],
    ];

    public function type(string $name, array $fields): self
    {
        $this->typeDefs[$name] = $fields;
        return $this;
    }

    public function query(string $name, callable $resolver, string $returnType = 'String'): self
    {
        $this->resolvers['Query'][$name] = [
            'resolver' => $resolver(...),
            'type' => $returnType,
        ];
        return $this;
    }

    public function mutation(string $name, callable $resolver, string $returnType = 'String'): self
    {
        $this->resolvers['Mutation'][$name] = [
            'resolver' => $resolver(...),
            'type' => $returnType,
        ];
        return $this;
    }

    public function execute(string $queryString, array $variables = [], mixed $context = null): array
    {
        $parsed = $this->parseQuery($queryString);
        $opType = $parsed['operation']; // 'query' or 'mutation'
        $rootType = ucfirst($opType);

        $data = [];
        $errors = [];

        foreach ($parsed['fields'] as $field) {
            $fieldName = $field['name'];
            $args = array_merge($field['args'], $variables);

            if (!isset($this->resolvers[$rootType][$fieldName])) {
                $errors[] = ['message' => "Field '{$fieldName}' not defined on type '{$rootType}'"];
                continue;
            }

            try {
                $resolver = $this->resolvers[$rootType][$fieldName]['resolver'];
                $result = $resolver($args, $context);
                
                // If selecting subfields
                if (!empty($field['selection']) && is_array($result)) {
                    $projected = [];
                    foreach ($field['selection'] as $subField) {
                        $projected[$subField] = $result[$subField] ?? null;
                    }
                    $data[$fieldName] = $projected;
                } else {
                    $data[$fieldName] = $result;
                }
            } catch (\Throwable $e) {
                $errors[] = ['message' => $e->getMessage()];
            }
        }

        $res = ['data' => $data];
        if (!empty($errors)) {
            $res['errors'] = $errors;
        }

        return $res;
    }

    private function parseQuery(string $query): array
    {
        $trimmed = trim($query);
        $op = 'query';

        if (str_starts_with($trimmed, 'mutation')) {
            $op = 'mutation';
            $trimmed = substr($trimmed, 8);
        } elseif (str_starts_with($trimmed, 'query')) {
            $op = 'query';
            $trimmed = substr($trimmed, 5);
        }

        // Strip outer braces
        $trimmed = trim($trimmed);
        if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
            $trimmed = substr($trimmed, 1, -1);
        }

        $fields = [];
        // Regex match field definitions with args and sub-selections
        if (preg_match_all('/([a-zA-Z_]\w*)(?:\(([^)]*)\))?(?:\s*\{([^}]*)\})?/', $trimmed, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $fieldName = trim($match[1]);
                if (empty($fieldName)) continue;

                $args = [];
                if (!empty($match[2])) {
                    $argParts = explode(',', $match[2]);
                    foreach ($argParts as $part) {
                        $pair = explode(':', $part, 2);
                        if (count($pair) === 2) {
                            $k = trim($pair[0]);
                            $v = trim(trim($pair[1]), '"\'');
                            $args[$k] = $v;
                        }
                    }
                }

                $selection = [];
                if (!empty($match[3])) {
                    $selection = array_values(array_filter(array_map('trim', preg_split('/\s+|,/', $match[3]))));
                }

                $fields[] = [
                    'name' => $fieldName,
                    'args' => $args,
                    'selection' => $selection,
                ];
            }
        }

        return [
            'operation' => $op,
            'fields' => $fields,
        ];
    }
}
