<?php
declare(strict_types=1);

namespace App\Views;

use App\Views\Layout;
use App\Components\AiGeneratorComponent;

$component = new AiGeneratorComponent();
$componentHtml = $component->renderWithLiveDom();

$docsHtml = '';
foreach ($documents as $doc) {
    $docsHtml .= <<<HTML
    <div class="bg-cyber-800 p-4 rounded-lg border border-cyber-700 mb-4 hover:border-blue-500 transition">
        <h4 class="font-bold text-blue-400">{$doc->title}</h4>
        <p class="text-sm text-gray-500 mt-1">Prompt: {$doc->prompt}</p>
        <p class="text-sm text-gray-400 mt-2 truncate">{$doc->content}</p>
        <div class="text-xs text-gray-600 mt-2">{$doc->created_at}</div>
    </div>
    HTML;
}

if (empty($documents)) {
    $docsHtml = '<p class="text-gray-500 italic">No documents generated yet.</p>';
}

$content = <<<HTML
<div class="max-w-6xl mx-auto py-12 px-4 flex gap-8 flex-col md:flex-row">
    <!-- Sidebar -->
    <div class="w-full md:w-1/3 space-y-6">
        <div class="bg-cyber-900/50 p-6 rounded-xl border border-cyber-700 backdrop-blur-md">
            <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002 2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                Your Documents
            </h3>
            <div class="space-y-2 h-[500px] overflow-y-auto pr-2 custom-scrollbar">
                {$docsHtml}
            </div>
        </div>
    </div>

    <!-- Main Workspace -->
    <div class="w-full md:w-2/3">
        {$componentHtml}
    </div>
</div>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #475569; }
</style>

<script>
    // Configure LiveDOM endpoint
    document.addEventListener("DOMContentLoaded", function() {
        if (window.LiveDom) {
            window.LiveDom.endpoint = "/workspace/livedom/update";
        }
    });
</script>
HTML;

echo Layout::render("Dashboard", $content, true);
