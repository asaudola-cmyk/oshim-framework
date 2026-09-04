<?php
declare(strict_types=1);

namespace Oshim\Ui\Runtime;

/**
 * Sovereign Client Runtime Generator (< 4KB Zero-Dependency JS Engine).
 * Provides Soft SPA Navigation, Server Actions, Islands Hydration, and Signal Bindings.
 */
class OshimClientRuntime
{
    private static ?string $cachedScript = null;

    /**
     * Get the raw minified client runtime JavaScript (< 3KB).
     */
    public static function getScript(): string
    {
        if (self::$cachedScript !== null) {
            return self::$cachedScript;
        }

        return self::$cachedScript = <<<'JS'
(function(){'use strict';window.Oshim={cache:new Map(),signals:new Map(),init:function(){this.initSoftNav();this.initIslands();this.initActions();this.initSignals();},initSoftNav:function(){document.addEventListener('click',function(e){var a=e.target.closest('a[href]');if(!a||a.target||a.hasAttribute('download')||a.origin!==location.origin)return;var href=a.getAttribute('href');if(href.startsWith('#')||href.startsWith('javascript:'))return;e.preventDefault();Oshim.navigate(href);});document.addEventListener('mousedown',function(e){var a=e.target.closest('a[href]');if(!a||a.origin!==location.origin)return;var href=a.getAttribute('href');if(!Oshim.cache.has(href)&&!href.startsWith('#')){fetch(href,{headers:{'X-Oshim-Prefetch':'1'}}).then(function(r){return r.text()}).then(function(html){Oshim.cache.set(href,html)}).catch(function(){});}});window.addEventListener('popstate',function(){Oshim.navigate(location.href,false);});},navigate:function(url,push){if(push===undefined)push=true;var cached=this.cache.get(url);var p=cached?Promise.resolve(cached):fetch(url,{headers:{'X-Oshim-Soft-Nav':'1'}}).then(function(r){return r.text()});p.then(function(html){Oshim.morphDocument(html);if(push)history.pushState(null,'',url);window.scrollTo(0,0);Oshim.initIslands();Oshim.initActions();}).catch(function(){location.href=url;});},morphDocument:function(newHtml){var parser=new DOMParser();var doc=parser.parseFromString(newHtml,'text/html');document.title=doc.title;document.body.innerHTML=doc.body.innerHTML;},initIslands:function(){var islands=document.querySelectorAll('oshim-island[data-strategy]');islands.forEach(function(el){var strategy=el.getAttribute('data-strategy');var hydrate=function(){el.removeAttribute('data-strategy');el.classList.add('oshim-hydrated');};if(strategy==='load'){hydrate();}else if(strategy==='idle'){if('requestIdleCallback' in window)requestIdleCallback(hydrate);else setTimeout(hydrate,150);}else if(strategy==='visible'){if('IntersectionObserver' in window){var obs=new IntersectionObserver(function(entries){if(entries[0].isIntersecting){obs.disconnect();hydrate();}});obs.observe(el);}else{hydrate();}}});},initActions:function(){document.querySelectorAll('[wire\\:click]').forEach(function(el){if(el._oshimBound)return;el._oshimBound=true;el.addEventListener('click',function(e){var action=el.getAttribute('wire:click');Oshim.dispatchAction(el,action);});});},dispatchAction:function(el,action,params){var comp=el.closest('[id^="oshim-comp-"]');var payload=comp?comp.getAttribute('data-payload'):null;fetch('/_oshim/action',{method:'POST',headers:{'Content-Type':'application/json','X-Oshim-Action':'1'},body:JSON.stringify({action:action,params:params||[],payload:payload})}).then(function(r){return r.json()}).then(function(data){if(data&&data.html&&comp){comp.outerHTML=data.html;Oshim.initActions();}}).catch(function(err){console.error(err);});},initSignals:function(){window.addEventListener('oshim:signal-patch',function(e){var d=e.detail;if(d&&d.signal_id){document.querySelectorAll('[data-oshim-sig="'+d.signal_id+'"]').forEach(function(n){n.textContent=d.value;});}});},setSignal:function(sigId,val){this.signals.set(sigId,val);window.dispatchEvent(new CustomEvent('oshim:signal-patch',{detail:{signal_id:sigId,value:val}}));}};document.addEventListener('DOMContentLoaded',function(){Oshim.init();});})();
JS;
    }

    /**
     * Get HTML script tag embedding the runtime.
     */
    public static function renderTag(): string
    {
        $script = self::getScript();
        return "<script>\n{$script}\n</script>";
    }
}
