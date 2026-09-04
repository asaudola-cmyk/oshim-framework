<?php
declare(strict_types=1);

namespace Oshim\Ui\Animation;

/**
 * Zero-Dependency Client-Side Motion Micro-Runtime Generator.
 *
 * Generates an ultra-lightweight (< 1.5 KB) client script for progressive
 * enhancement of server-driven animations: IntersectionObserver scroll triggers,
 * scroll scrubbing, parallax tracking, and interactive hover/tap state physics.
 */
class MotionRuntime
{
    /**
     * Generate HTML <script> tag containing the client motion micro-runtime.
     */
    public static function script(bool $minified = true): string
    {
        $code = self::code($minified);
        return "<script id=\"oshim-motion-runtime\">\n{$code}\n</script>";
    }

    /**
     * Generate pure JavaScript string.
     */
    public static function code(bool $minified = true): string
    {
        $js = <<<'JAVASCRIPT'
(function() {
  'use strict';
  if (window.__OSHIM_MOTION_INITIALIZED__) return;
  window.__OSHIM_MOTION_INITIALIZED__ = true;

  function initMotion() {
    var motionElements = document.querySelectorAll('[data-motion="true"]');
    var scrollElements = [];

    motionElements.forEach(function(el) {
      // 1. Interactive Hover States
      if (el.dataset.whileHover) {
        try {
          var hoverProps = JSON.parse(el.dataset.whileHover);
          var origTransform = el.style.transform || '';
          el.addEventListener('mouseenter', function() {
            var transforms = [];
            if (hoverProps.scale !== undefined) transforms.push('scale(' + hoverProps.scale + ')');
            if (hoverProps.y !== undefined) transforms.push('translateY(' + hoverProps.y + (typeof hoverProps.y === 'number' ? 'px' : '') + ')');
            if (hoverProps.x !== undefined) transforms.push('translateX(' + hoverProps.x + (typeof hoverProps.x === 'number' ? 'px' : '') + ')');
            if (hoverProps.rotate !== undefined) transforms.push('rotate(' + hoverProps.rotate + (typeof hoverProps.rotate === 'number' ? 'deg' : '') + ')');
            el.style.transform = transforms.length ? transforms.join(' ') : (hoverProps.transform || '');
            el.style.transition = 'transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
          });
          el.addEventListener('mouseleave', function() {
            el.style.transform = origTransform;
          });
        } catch(e) {}
      }

      // 2. Interactive Tap States
      if (el.dataset.whileTap) {
        try {
          var tapProps = JSON.parse(el.dataset.whileTap);
          el.addEventListener('mousedown', function() {
            if (tapProps.scale !== undefined) el.style.transform = 'scale(' + tapProps.scale + ')';
          });
          el.addEventListener('mouseup', function() {
            el.style.transform = '';
          });
        } catch(e) {}
      }

      // 3. Scroll Triggers & Scrubbing
      if (el.dataset.scroll === 'true') {
        scrollElements.push(el);
      }
    });

    // 4. Viewport Intersection Observer
    if ('IntersectionObserver' in window && scrollElements.length > 0) {
      var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
          var target = entry.target;
          var isOnce = target.dataset.scrollOnce === 'true';
          if (entry.isIntersecting) {
            target.classList.add('oshim-in-view');
            target.style.visibility = 'visible';
            target.style.animationPlayState = 'running';
            if (isOnce) observer.unobserve(target);
          } else if (!isOnce) {
            target.classList.remove('oshim-in-view');
          }
        });
      }, { threshold: [0, 0.1, 0.2, 0.5, 1.0] });

      scrollElements.forEach(function(el) {
        if (!el.dataset.scrollScrub) {
          el.style.animationPlayState = 'paused';
          observer.observe(el);
        }
      });
    }

    // 5. Parallax & Scrub RAF Loop
    var scrubElements = Array.prototype.filter.call(scrollElements, function(el) {
      return el.dataset.scrollScrub || el.dataset.scrollParallax;
    });

    if (scrubElements.length > 0) {
      var ticking = false;
      function onScroll() {
        if (!ticking) {
          window.requestAnimationFrame(function() {
            var vh = window.innerHeight || document.documentElement.clientHeight;
            scrubElements.forEach(function(el) {
              var rect = el.getBoundingClientRect();
              var progress = Math.max(0, Math.min(1, (vh - rect.top) / (vh + rect.height)));
              if (el.dataset.scrollParallax) {
                var factor = parseFloat(el.dataset.scrollParallax) || 0.2;
                var offset = (rect.top - (vh / 2)) * factor;
                el.style.transform = 'translateY(' + offset.toFixed(2) + 'px)';
              }
            });
            ticking = false;
          });
          ticking = true;
        }
      }
      window.addEventListener('scroll', onScroll, { passive: true });
      onScroll();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMotion);
  } else {
    initMotion();
  }
})();
JAVASCRIPT;

        if ($minified) {
            // Remove comments and multi-line whitespace
            $js = preg_replace('!/\*.*?\*/!s', '', $js) ?? $js;
            $js = preg_replace('!\/\/.*$!m', '', $js) ?? $js;
            $js = preg_replace('/\s+/', ' ', $js) ?? $js;
            $js = trim($js);
        }

        return $js;
    }
}
