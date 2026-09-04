<?php
declare(strict_types=1);

namespace Oshim\Ai\Canvas\Nodes;

use Oshim\Ai\Canvas\AbstractNode;

/**
 * Prompt Node: Dynamic template interpolation and prompt assembly.
 */
class PromptNode extends AbstractNode
{
    protected string $type = 'prompt';
    protected string $title = 'Prompt Template';

    protected function definePorts(): void
    {
        $this->registerInputPort('variables', 'array', 'Key-value map of variables to interpolate', false, []);
        $this->registerInputPort('template', 'string', 'Dynamic template string override', false, null);
        $this->registerInputPort('user_query', 'string', 'Direct user query or input text', false, null);
        $this->registerInputPort('system_prompt', 'string', 'System instructions', false, null);

        $this->registerOutputPort('prompt', 'string', 'Interpolated prompt ready for LLM inference');
        $this->registerOutputPort('system_prompt', 'string', 'System instructions');
        $this->registerOutputPort('variables', 'array', 'Applied variable map');
    }

    protected function validateCustom(): void
    {
        $template = $this->getConfigValue('template', '');
        if (!is_string($template)) {
            $this->addError("Config 'template' must be a string.");
        }
    }

    protected function process(array $inputs): array
    {
        // 1. Determine template
        $template = (string)($inputs['template'] ?? $this->getConfigValue('template', '{{query}}'));
        if (trim($template) === '' && isset($inputs['user_query'])) {
            $template = '{{user_query}}';
        }

        // 2. Gather variable replacements
        $vars = (array)($this->getConfigValue('variables', []));
        if (isset($inputs['variables']) && is_array($inputs['variables'])) {
            $vars = array_merge($vars, $inputs['variables']);
        }

        // Merge direct scalar input keys into variables
        foreach ($inputs as $k => $v) {
            if ($k !== 'variables' && $k !== 'template' && is_scalar($v)) {
                $vars[$k] = (string)$v;
            }
        }

        if (isset($inputs['user_query']) && !isset($vars['query'])) {
            $vars['query'] = (string)$inputs['user_query'];
        }

        // 3. Interpolate {{key}} and {key}
        $interpolated = preg_replace_callback('/\{\{?\s*([a-zA-Z0-9_\-\.]+)\s*\}?\}/', function ($matches) use ($vars) {
            $key = $matches[1];
            if (array_key_exists($key, $vars)) {
                $val = $vars[$key];
                return is_array($val) ? json_encode($val, JSON_UNESCAPED_SLASHES) : (string)$val;
            }
            return '';
        }, $template);

        $systemPrompt = (string)($inputs['system_prompt'] ?? $this->getConfigValue('system_prompt', 'You are a sovereign AI assistant.'));

        return [
            'prompt' => $interpolated,
            'system_prompt' => $systemPrompt,
            'variables' => $vars,
            'raw_template' => $template,
        ];
    }
}
