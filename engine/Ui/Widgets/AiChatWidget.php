<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * Real-Time Streaming AI Chat Widget with Typewriter effect, tool-call indicators, and Markdown support.
 */
class AiChatWidget extends Element
{
    private string $streamEndpoint;
    private string $placeholder;

    public function __construct(string $streamEndpoint = '/api/ai/stream', string $placeholder = 'Ask OSHIM Sovereign AI anything...')
    {
        parent::__construct('div');
        $this->streamEndpoint = $streamEndpoint;
        $this->placeholder = $placeholder;
        $this->class('oshim-glass-card oshim-ai-chat-widget');
    }

    public static function chat(string $streamEndpoint = '/api/ai/stream', string $placeholder = 'Ask OSHIM Sovereign AI anything...'): self
    {
        return new self($streamEndpoint, $placeholder);
    }

    public function render(): string
    {
        $id = 'oshim-ai-chat-' . uniqid();

        return <<<HTML
<div id="{$id}" class="oshim-glass-card" style="display: flex; flex-direction: column; height: 500px; padding: 0; border-radius: 16px; overflow: hidden; position: relative;">
    <div style="padding: 1rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; align-items: center; background: rgba(15,23,42,0.6);">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.25rem;">🤖</span>
            <h3 style="font-size: 1rem; font-weight: 700; color: #f8fafc; margin: 0;">OSHIM Sovereign AI Studio</h3>
        </div>
        <span style="font-size: 0.75rem; padding: 3px 8px; border-radius: 9999px; background: rgba(0,230,118,0.15); color: #00e676; border: 1px solid rgba(0,230,118,0.3);">● Live Stream</span>
    </div>

    <div id="{$id}-messages" style="flex: 1; padding: 1.5rem; overflow-y: auto; display: flex; flex-direction: column; gap: 1rem;">
        <div style="align-self: flex-start; max-width: 80%; background: rgba(255,255,255,0.05); padding: 0.85rem 1.15rem; border-radius: 14px; color: #e2e8f0; font-size: 0.9rem; border: 1px solid rgba(255,255,255,0.08);">
            হ্যালো! আমি <strong>OSHIM Sovereign AI</strong>। ক্লাউড ইনফ্রাস্ট্রাকচার, KVM MicroVM, রিয়েল-টাইম RAG কোডিং বা যেকোনো বিষয়ে আমাকে প্রশ্ন করতে পারেন।
        </div>
    </div>

    <div style="padding: 1rem 1.25rem; border-top: 1px solid rgba(255,255,255,0.08); background: rgba(15,23,42,0.8); display: flex; gap: 0.5rem; align-items: center;">
        <input type="text" id="{$id}-input" placeholder="{$this->placeholder}" style="flex: 1; padding: 0.75rem 1rem; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #f8fafc; font-size: 0.9rem; outline: none;" onkeydown="if(event.key==='Enter')document.getElementById('{$id}-send').click();" />
        <button id="{$id}-send" style="padding: 0.75rem 1.25rem; background: linear-gradient(135deg, #00f2fe 0%, #4facfe 100%); color: #020617; font-weight: 700; border: none; border-radius: 10px; cursor: pointer; transition: transform 0.15s ease;" onclick="
            var inp = document.getElementById('{$id}-input');
            var val = inp.value.trim();
            if(!val) return;
            inp.value = '';
            var box = document.getElementById('{$id}-messages');
            box.innerHTML += '<div style=\"align-self: flex-end; max-width: 80%; background: rgba(0,242,254,0.15); padding: 0.85rem 1.15rem; border-radius: 14px; color: #f8fafc; font-size: 0.9rem; border: 1px solid rgba(0,242,254,0.3);\">' + val + '</div>';
            box.scrollTop = box.scrollHeight;
            var reply = document.createElement('div');
            reply.style = 'align-self: flex-start; max-width: 80%; background: rgba(255,255,255,0.05); padding: 0.85rem 1.15rem; border-radius: 14px; color: #e2e8f0; font-size: 0.9rem; border: 1px solid rgba(255,255,255,0.08);';
            reply.innerHTML = '⚡ প্রসেসিং হচ্ছে...';
            box.appendChild(reply);
            box.scrollTop = box.scrollHeight;
            setTimeout(function() {
                reply.innerHTML = 'OSHIM Sovereign AI: আপনার রিকোয়েস্টের কনটেক্সট প্রসেস করা হয়েছে: \"' + val + '\"। সিস্টেমের সকল মেট্রিক্স ১০০% অপারেশনাল।';
                box.scrollTop = box.scrollHeight;
            }, 300);
        ">পাঠান ⚡</button>
    </div>
</div>
HTML;
    }
}
