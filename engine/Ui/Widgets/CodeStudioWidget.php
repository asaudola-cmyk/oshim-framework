<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * CodeStudioWidget: Futuristic Dark Glassmorphic Code IDE & Sandbox.
 * Syntax highlighting, line numbers, file tabs, copy action, and inline run simulation.
 */
class CodeStudioWidget extends Element
{
    private string $filename;
    private string $language;
    private string $code;
    private ?string $outputLog;

    public function __construct(string $filename, string $code, string $language = 'php', ?string $outputLog = null)
    {
        parent::__construct('div');
        $this->filename = $filename;
        $this->code = trim($code);
        $this->language = strtolower($language);
        $this->outputLog = $outputLog;
        $this->class('oshim-code-studio-widget');
    }

    public static function create(string $filename, string $code, string $language = 'php', ?string $outputLog = null): self
    {
        return new self($filename, $code, $language, $outputLog);
    }

    public function render(): string
    {
        $id = 'code_' . substr(md5($this->filename . $this->code), 0, 8);
        $lines = explode("\n", $this->code);
        $highlightedLines = [];

        foreach ($lines as $i => $line) {
            $lineNum = $i + 1;
            $highlighted = $this->highlightSyntax(htmlspecialchars($line, ENT_QUOTES, 'UTF-8'));
            $highlightedLines[] = <<<HTML
            <tr class="hover:bg-white/[0.03] transition-colors leading-6">
                <td class="text-right pr-4 pl-3 select-none text-slate-600 font-mono text-xs w-10">{$lineNum}</td>
                <td class="font-mono text-xs text-slate-200 whitespace-pre pr-4">{$highlighted}</td>
            </tr>
HTML;
        }

        $codeRows = implode('', $highlightedLines);

        $terminalDrawer = '';
        if ($this->outputLog !== null) {
            $terminalDrawer = <<<HTML
            <div class="border-t border-slate-800 bg-slate-950 p-3 font-mono text-xs text-emerald-400 flex items-start space-x-2">
                <span class="text-cyan-400 font-bold">$</span>
                <div class="flex-1 whitespace-pre-wrap">{$this->outputLog}</div>
                <span class="text-[10px] text-slate-500 uppercase font-bold px-2 py-0.5 rounded bg-slate-900">EXEC 0.42ms</span>
            </div>
HTML;
        }

        return <<<HTML
<div id="{$id}" class="oshim-code-studio rounded-2xl overflow-hidden border border-slate-800 bg-[#090d16] shadow-2xl backdrop-blur-2xl">
    <!-- Window Header -->
    <div class="flex items-center justify-between px-4 py-2.5 bg-slate-900/80 border-b border-slate-800">
        <div class="flex items-center space-x-3">
            <div class="flex space-x-1.5">
                <span class="w-3 h-3 rounded-full bg-red-500/80"></span>
                <span class="w-3 h-3 rounded-full bg-amber-500/80"></span>
                <span class="w-3 h-3 rounded-full bg-emerald-500/80"></span>
            </div>
            <div class="flex items-center space-x-2 px-3 py-1 rounded-lg bg-slate-800/60 border border-slate-700/50">
                <span class="text-xs">📄</span>
                <span class="text-xs font-mono font-medium text-slate-200">{$this->filename}</span>
                <span class="text-[10px] font-mono uppercase px-1.5 py-0.2 rounded bg-cyan-500/20 text-cyan-300 font-bold">{$this->language}</span>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <button onclick="navigator.clipboard.writeText(decodeURIComponent('{$this->getEncodedCode()}')); this.innerText='✔ Copied!';" class="px-2.5 py-1 text-xs font-mono font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors border border-slate-700">
                Copy
            </button>
            <button class="px-3 py-1 text-xs font-mono font-bold rounded-lg bg-cyan-500 hover:bg-cyan-400 text-slate-950 transition-all shadow-[0_0_10px_rgba(6,182,212,0.4)] flex items-center space-x-1">
                <span>▶</span>
                <span>Run</span>
            </button>
        </div>
    </div>

    <!-- Code Editor Body -->
    <div class="overflow-x-auto py-3 max-h-96">
        <table class="w-full border-collapse">
            <tbody>
                {$codeRows}
            </tbody>
        </table>
    </div>

    <!-- Terminal Output -->
    {$terminalDrawer}
</div>
HTML;
    }

    private function getEncodedCode(): string
    {
        return rawurlencode($this->code);
    }

    private function highlightSyntax(string $line): string
    {
        // Simple fast regex syntax token highlighting
        $keywords = ['class', 'function', 'public', 'private', 'protected', 'return', 'new', 'if', 'else', 'foreach', 'as', 'use', 'namespace', 'declare', 'static'];
        $pattern = '/\b(' . implode('|', $keywords) . ')\b/';
        $line = preg_replace($pattern, '<span style="color: #c084fc; font-weight: 600;">$1</span>', $line);

        // Variables
        $line = preg_replace('/(\$[a-zA-Z0-9_]+)/', '<span style="color: #38bdf8;">$1</span>', $line);

        // Strings
        $line = preg_replace('/(&quot;.*?&quot;|&#039;.*?&#039;)/', '<span style="color: #34d399;">$1</span>', $line);

        // Numbers
        $line = preg_replace('/\b(\d+)\b/', '<span style="color: #fbbf24;">$1</span>', $line);

        // Comments
        $line = preg_replace('/(\/\/.*$)/', '<span style="color: #64748b; font-style: italic;">$1</span>', $line);

        return $line ?? '';
    }
}
