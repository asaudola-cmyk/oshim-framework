<?php
declare(strict_types=1);

namespace Oshim\Ui\Components;

use Oshim\Ui\Component;
use Oshim\Security\Csrf;

class Form extends Component
{
    protected string $action = '#';
    protected string $method = 'POST';
    protected array $fields = []; // field_name => ['type' => 'text|select|toggle|checkbox|textarea|range|file', 'label' => '', 'placeholder' => '', 'options' => [], 'help' => '', 'rules' => '']
    protected string $submitLabel = 'Submit';
    protected string $submitVariant = 'primary';
    protected ?string $csrfToken = null;
    protected string $class = '';

    public function mount(array $props): void
    {
        $this->action = (string)($props['action'] ?? '#');
        $this->method = strtoupper($props['method'] ?? 'POST') === 'GET' ? 'GET' : 'POST';
        $this->fields = (array)($props['fields'] ?? []);
        $this->submitLabel = (string)($props['submitLabel'] ?? 'Submit');
        $this->submitVariant = (string)($props['submitVariant'] ?? 'primary');
        $this->csrfToken = $props['csrfToken'] ?? null;
        $this->class = (string)($props['class'] ?? '');

        if (!isset($this->state['values'])) {
            $this->state['values'] = (array)($props['values'] ?? []);
        }
        if (!isset($this->state['errors'])) {
            $this->state['errors'] = (array)($props['errors'] ?? []);
        }
        if (!isset($this->state['submitting'])) {
            $this->state['submitting'] = (bool)($props['submitting'] ?? false);
        }
    }

    public function setFieldValue(array $payload): void
    {
        $field = (string)($payload['field'] ?? ($payload['name'] ?? ''));
        if ($field !== '') {
            $this->state['values'][$field] = $payload['value'] ?? null;
            unset($this->state['errors'][$field]);
        }
    }

    public function handleFieldInput(array $payload): void
    {
        $this->setFieldValue($payload);
    }

    public function clearErrors(array $payload = []): void
    {
        $this->state['errors'] = [];
    }

    public function setErrors(array $payload): void
    {
        $this->state['errors'] = (array)($payload['errors'] ?? $payload);
    }

    public function render(): string
    {
        $html = '<form action="' . $this->escape($this->action) . '" method="' . $this->method . '" class="oshim-form oshim-glass ' . $this->escape($this->class) . '" data-oshim-id="' . $this->escape($this->id) . '" oshim:submit="' . $this->escape($this->action) . '" data-oshim-submit="' . $this->escape($this->action) . '">';

        // CSRF Protection Hidden Input
        $token = $this->csrfToken ?? (class_exists(Csrf::class) ? Csrf::token() : 'oshim_csrf');
        $html .= '<input type="hidden" name="_csrf" value="' . $this->escape($token) . '">';

        // Render Fields
        foreach ($this->fields as $name => $field) {
            $html .= $this->renderField((string)$name, (array)$field);
        }

        // Custom default slot or Submit Button
        if ($this->hasSlot('default')) {
            $html .= $this->slot('default');
        } else {
            $isSubmitting = !empty($this->state['submitting']);
            $btnProps = [
                'label'    => $this->submitLabel,
                'variant'  => $this->submitVariant,
                'type'     => 'submit',
                'loading'  => $isSubmitting,
                'disabled' => $isSubmitting,
            ];
            $button = new Button($btnProps);
            $html .= '<div class="oshim-form__actions">' . $button->render() . '</div>';
        }

        $html .= '</form>';
        return $html;
    }

    private function renderField(string $name, array $field): string
    {
        $type = $field['type'] ?? 'text';
        $label = $field['label'] ?? ucfirst($name);
        $val = $this->state['values'][$name] ?? ($field['default'] ?? ($field['value'] ?? ''));
        $error = $this->state['errors'][$name] ?? null;
        $help = $field['help'] ?? null;
        $hasError = !empty($error);

        $html = '<div class="oshim-form-group' . ($hasError ? ' oshim-form-group--error' : '') . '">';
        $html .= '<label class="oshim-label" for="field_' . $this->escape($name) . '">' . $this->escape($label) . '</label>';

        $controlHtml = match ($type) {
            'textarea' => sprintf(
                '<textarea id="field_%s" name="%s" class="oshim-textarea oshim-input" rows="%d" placeholder="%s" oshim:input="handleFieldInput" data-oshim-bind="%s" data-field="%s">%s</textarea>',
                $this->escape($name),
                $this->escape($name),
                (int)($field['rows'] ?? 3),
                $this->escape($field['placeholder'] ?? ''),
                $this->escape($name),
                $this->escape($name),
                $this->escape((string)$val)
            ),
            'select' => $this->renderSelect($name, $field, $val),
            'toggle', 'switch' => sprintf(
                '<label class="oshim-toggle"><input type="checkbox" id="field_%s" name="%s" value="1" %s oshim:change="handleFieldInput" data-oshim-bind="%s" data-field="%s"><span class="oshim-toggle__slider"></span></label>',
                $this->escape($name),
                $this->escape($name),
                !empty($val) ? 'checked="checked"' : '',
                $this->escape($name),
                $this->escape($name)
            ),
            'checkbox' => sprintf(
                '<label class="oshim-checkbox"><input type="checkbox" id="field_%s" name="%s" value="1" %s oshim:change="handleFieldInput" data-oshim-bind="%s" data-field="%s"><span class="oshim-checkbox__mark"></span><span class="oshim-checkbox__text">%s</span></label>',
                $this->escape($name),
                $this->escape($name),
                !empty($val) ? 'checked="checked"' : '',
                $this->escape($name),
                $this->escape($name),
                $this->escape($field['checkboxLabel'] ?? $label)
            ),
            'range' => sprintf(
                '<div class="oshim-range-wrapper"><input type="range" id="field_%s" name="%s" min="%s" max="%s" step="%s" value="%s" class="oshim-range" oshim:input="handleFieldInput" data-oshim-bind="%s" data-field="%s"><span class="oshim-range__value">%s</span></div>',
                $this->escape($name),
                $this->escape($name),
                $this->escape($field['min'] ?? '0'),
                $this->escape($field['max'] ?? '100'),
                $this->escape($field['step'] ?? '1'),
                $this->escape((string)$val),
                $this->escape($name),
                $this->escape($name),
                $this->escape((string)$val)
            ),
            default => sprintf(
                '<input type="%s" id="field_%s" name="%s" value="%s" placeholder="%s" class="oshim-input" oshim:input="handleFieldInput" data-oshim-bind="%s" data-field="%s"%s />',
                $this->escape($type),
                $this->escape($name),
                $this->escape($name),
                $this->escape((string)$val),
                $this->escape($field['placeholder'] ?? ''),
                $this->escape($name),
                $this->escape($name),
                !empty($field['required']) ? ' required' : ''
            ),
        };

        $html .= $controlHtml;

        if ($hasError) {
            $errorMsg = is_array($error) ? implode(', ', $error) : $error;
            $html .= '<span class="oshim-field-error">' . $this->escape((string)$errorMsg) . '</span>';
        } elseif ($help !== null) {
            $html .= '<span class="oshim-form-help">' . $this->escape((string)$help) . '</span>';
        }

        $html .= '</div>';
        return $html;
    }

    private function renderSelect(string $name, array $field, mixed $selectedVal): string
    {
        $html = '<select id="field_' . $this->escape($name) . '" name="' . $this->escape($name) . '" class="oshim-select oshim-input" oshim:change="handleFieldInput" data-oshim-bind="' . $this->escape($name) . '" data-field="' . $this->escape($name) . '">';
        foreach ($field['options'] ?? [] as $k => $v) {
            $isSelected = (string)$k === (string)$selectedVal ? ' selected' : '';
            $html .= '<option value="' . $this->escape((string)$k) . '"' . $isSelected . '>' . $this->escape((string)$v) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }
}
