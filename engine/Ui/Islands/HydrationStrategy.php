<?php
declare(strict_types=1);

namespace Oshim\Ui\Islands;

/**
 * Hydration Strategy enumeration for Islands Architecture (Astro style).
 */
enum HydrationStrategy: string
{
    case LOAD    = 'load';    // Hydrate immediately on page load
    case IDLE    = 'idle';    // Hydrate when browser main thread is idle (requestIdleCallback)
    case VISIBLE = 'visible'; // Hydrate when scrolled into viewport (IntersectionObserver)
    case MEDIA   = 'media';   // Hydrate when CSS media query matches
    case NEVER   = 'never';   // Pure zero-JS static HTML
}
