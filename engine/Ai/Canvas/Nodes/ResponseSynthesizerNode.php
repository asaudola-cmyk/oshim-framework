<?php
declare(strict_types=1);

namespace Oshim\Ai\Canvas\Nodes;

use Oshim\Ai\Canvas\AbstractNode;

/**
 * Response Synthesizer Node: Aggregates and synthesizes multi-source outputs into cohesive markdown/JSON/HTML responses.
 */
class ResponseSynthesizerNode extends AbstractNode
{
    protected string $type = 'response_synthesizer';
    protected string $title = 'Response Synthesizer';

    protected function definePorts(): void
    {
        $this->registerInputPort('sources', 'any', 'Input sources or text blocks to synthesize', false, []);
        $this->registerInputPort('format', 'string', 'Target response format (markdown, json, html, text)', false, 'markdown');
        $this->registerInputPort('template', 'string', 'Optional response structure template', false, null);
        $this->registerInputPort('title', 'string', 'Title header for the response', false, null);

        $this->registerOutputPort('final_response', 'string', 'Cohesive synthesized response output');
        $this->registerOutputPort('summary', 'string', 'Brief executive summary of findings');
        $this->registerOutputPort('format', 'string', 'Output formatting schema applied');
        $this->registerOutputPort('source_count', 'int', 'Total count of input sources merged');
        $this->registerOutputPort('data', 'array', 'Raw structured aggregation map');
    }

    protected function process(array $inputs): array
    {
        $format = strtolower((string)($inputs['format'] ?? $this->getConfigValue('format', 'markdown')));
        $title = (string)($inputs['title'] ?? $this->getConfigValue('title', 'AI Workflow Result'));
        $template = $inputs['template'] ?? $this->getConfigValue('template', null);

        // 1. Gather all source payloads
        $designatedSources = (array)($this->getConfigValue('sources', []));
        $collected = [];

        // Add explicit 'sources' input
        if (isset($inputs['sources'])) {
            if (is_array($inputs['sources'])) {
                $collected = array_merge($collected, $inputs['sources']);
            } else {
                $collected['source_main'] = $inputs['sources'];
            }
        }

        // Check designated source keys in inputs
        if (!empty($designatedSources)) {
            foreach ($designatedSources as $srcKey) {
                if (isset($inputs[$srcKey])) {
                    $collected[$srcKey] = $inputs[$srcKey];
                }
            }
        }

        // Include common AI output keys if present and not already captured
        $commonKeys = ['reply', 'rag_context', 'tool_result', 'prompt', 'result', 'answer', 'text', 'output'];
        foreach ($commonKeys as $ck) {
            if (isset($inputs[$ck]) && !isset($collected[$ck])) {
                $collected[$ck] = $inputs[$ck];
            }
        }

        // If still empty, collect all scalar and array values from inputs
        if (empty($collected)) {
            foreach ($inputs as $k => $v) {
                if (!in_array($k, ['format', 'template', 'title', 'sources'], true)) {
                    $collected[$k] = $v;
                }
            }
        }

        // 2. Synthesize according to format
        $finalResponse = '';
        $summary = '';

        if (!empty($template) && is_string($template)) {
            // Apply template interpolation
            $finalResponse = preg_replace_callback('/\{\{?\s*([a-zA-Z0-9_\-\.]+)\s*\}?\}/', function ($matches) use ($collected) {
                $k = $matches[1];
                if (isset($collected[$k])) {
                    return is_array($collected[$k]) ? json_encode($collected[$k], JSON_UNESCAPED_SLASHES) : (string)$collected[$k];
                }
                return '';
            }, $template);
            $summary = substr(strip_tags($finalResponse), 0, 160) . '...';
        } else {
            switch ($format) {
                case 'json':
                    $finalResponse = json_encode([
                        'title' => $title,
                        'timestamp' => time(),
                        'data' => $collected,
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    $summary = "Structured JSON payload with " . count($collected) . " data keys.";
                    break;

                case 'html':
                    $html = "<div class=\"oshim-synthesized-response\">\n";
                    if (!empty($title)) {
                        $html .= "  <h2 class=\"text-xl font-bold text-cyan-400 mb-4\">" . htmlspecialchars($title) . "</h2>\n";
                    }
                    foreach ($collected as $key => $val) {
                        $label = ucwords(str_replace('_', ' ', (string)$key));
                        $html .= "  <div class=\"source-block mb-3\">\n";
                        $html .= "    <h4 class=\"text-sm font-semibold text-slate-300\">" . htmlspecialchars($label) . "</h4>\n";
                        if (is_array($val)) {
                            $html .= "    <pre class=\"bg-slate-900 p-3 rounded text-xs text-slate-200\">" . htmlspecialchars(json_encode($val, JSON_PRETTY_PRINT)) . "</pre>\n";
                        } else {
                            $html .= "    <p class=\"text-slate-100\">" . nl2br(htmlspecialchars((string)$val)) . "</p>\n";
                        }
                        $html .= "  </div>\n";
                    }
                    $html .= "</div>";
                    $finalResponse = $html;
                    $summary = "HTML document containing " . count($collected) . " sections.";
                    break;

                case 'text':
                    $lines = [];
                    if (!empty($title)) {
                        $lines[] = strtoupper($title);
                        $lines[] = str_repeat('=', strlen($title));
                    }
                    foreach ($collected as $key => $val) {
                        $label = ucwords(str_replace('_', ' ', (string)$key));
                        $lines[] = "\n[{$label}]";
                        $lines[] = is_array($val) ? json_encode($val, JSON_PRETTY_PRINT) : (string)$val;
                    }
                    $finalResponse = implode("\n", $lines);
                    $summary = substr($finalResponse, 0, 160) . '...';
                    break;

                case 'markdown':
                default:
                    $md = [];
                    if (!empty($title)) {
                        $md[] = "# {$title}\n";
                    }

                    // If primary reply exists, give it prominence
                    if (isset($collected['reply'])) {
                        $md[] = "## Response\n" . (string)$collected['reply'] . "\n";
                        unset($collected['reply']);
                    }

                    foreach ($collected as $key => $val) {
                        $label = ucwords(str_replace('_', ' ', (string)$key));
                        $md[] = "### {$label}";
                        if (is_array($val)) {
                            $md[] = "```json\n" . json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n```\n";
                        } else {
                            $md[] = (string)$val . "\n";
                        }
                    }
                    $finalResponse = trim(implode("\n", $md));
                    $summary = substr(strip_tags($finalResponse), 0, 160) . '...';
                    break;
            }
        }

        return [
            'final_response' => $finalResponse,
            'summary' => $summary,
            'format' => $format,
            'source_count' => count($collected),
            'data' => $collected,
        ];
    }
}
