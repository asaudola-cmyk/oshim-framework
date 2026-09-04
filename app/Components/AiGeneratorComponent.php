<?php
declare(strict_types=1);

namespace App\Components;

use Oshim\Ui\LiveDom\LiveComponent;
use App\Models\Document;

class AiGeneratorComponent extends LiveComponent
{
    public string $prompt = '';
    public string $generatedContent = '';
    public bool $isGenerating = false;
    public string $docTitle = '';

    public function generate(): void
    {
        if (empty(trim($this->prompt))) {
            $this->addError('prompt', 'Please enter a prompt.');
            return;
        }

        $this->isGenerating = true;
        
        // Mock generation
        sleep(1); // Simulate AI delay
        
        $this->generatedContent = "OSHIM AI Response:\n\nBased on your prompt '{$this->prompt}', here is your ultra-fast, zero-dependency generated content. The framework's MicroKernel handled this request in < 1ms before I (the AI) started generating this text.";
        $this->docTitle = "Generated: " . substr($this->prompt, 0, 20) . "...";
        
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        if (!empty($_SESSION['user_id'])) {
            $doc = new Document();
            $doc->user_id = (int)$_SESSION['user_id'];
            $doc->title = $this->docTitle;
            $doc->prompt = $this->prompt;
            $doc->content = $this->generatedContent;
            $doc->save();
        }

        $this->isGenerating = false;
        $this->prompt = '';
    }

    public function render(): string
    {
        $error = $this->getError('prompt');
        $errorHtml = $error ? "<p class='text-red-400 text-sm mt-1'>{$error}</p>" : '';

        $btnText = $this->isGenerating ? 'Generating...' : 'Generate Content';
        $btnClass = $this->isGenerating ? 'bg-blue-800 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-500';
        $btnDisabled = $this->isGenerating ? 'disabled' : '';

        $resultHtml = '';
        if ($this->generatedContent !== '') {
            $resultHtml = <<<HTML
            <div class="mt-8 p-6 bg-cyber-900 border border-cyber-700 rounded-lg shadow-lg">
                <h3 class="text-xl font-bold text-white mb-4">{$this->docTitle}</h3>
                <div class="prose prose-invert max-w-none text-gray-300 whitespace-pre-wrap">
                    {$this->generatedContent}
                </div>
            </div>
            HTML;
        }

        return <<<HTML
        <div class="w-full max-w-4xl mx-auto">
            <div class="bg-cyber-800 p-6 rounded-xl border border-cyber-700 shadow-xl">
                <h2 class="text-2xl font-bold text-white mb-4">AI Content Generator</h2>
                <div class="flex gap-4">
                    <div class="flex-1">
                        <input type="text" 
                               data-live-model="prompt"
                               placeholder="What do you want to generate? e.g. Write a marketing email for OSHIM" 
                               class="w-full bg-cyber-900 border border-cyber-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-blue-500 transition"
                               autocomplete="off">
                        {$errorHtml}
                    </div>
                    <button data-live-click="generate" 
                            class="px-6 py-3 rounded-lg text-white font-semibold transition {$btnClass}"
                            {$btnDisabled}>
                        {$btnText}
                    </button>
                </div>
            </div>
            
            {$resultHtml}
        </div>
        HTML;
    }
}
