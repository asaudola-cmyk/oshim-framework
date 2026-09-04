<?php
declare(strict_types=1);

namespace App\Views;

use App\Views\Layout;
use Oshim\Ui\Canvas3D\Canvas3DWidget;
use Oshim\Ui\Canvas3D\Scene;
use Oshim\Ui\Canvas3D\Geometry\BoxGeometry;
use Oshim\Ui\Canvas3D\Material\MeshStandardMaterial;

$scene = new Scene();
$geom = new BoxGeometry(1.5, 1.5, 1.5);
$mat = new MeshStandardMaterial([
    'color' => 0x00f0ff,
    'wireframe' => true
]);
$scene->addMesh($geom, $mat);

$canvas = new Canvas3DWidget($scene);
$canvas->width('100%')
       ->height('400px')
       ->autoRotate(true, 1.5)
       ->camera(0, 0, 5)
       ->controls(true);

$content = <<<HTML
<div class="max-w-6xl mx-auto px-4 py-16 flex flex-col md:flex-row items-center justify-between gap-12">
    <div class="flex-1 space-y-6">
        <h1 class="text-5xl md:text-7xl font-extrabold tracking-tight">
            Build AI Apps <br>
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-cyan-300">At Light Speed</span>
        </h1>
        <p class="text-xl text-cyber-300 max-w-2xl text-gray-400">
            The Oshim AI Workspace is built on the world's fastest pure PHP framework. 
            Zero dependencies, strict types, and absolute power.
        </p>
        <div class="flex gap-4 pt-4">
            <a href="/workspace/register" class="bg-blue-600 hover:bg-blue-500 text-white px-8 py-3 rounded-lg font-semibold transition shadow-[0_0_15px_rgba(59,130,246,0.5)]">
                Start Building Now
            </a>
            <a href="/docs" class="border border-cyber-700 bg-cyber-800/50 hover:bg-cyber-700/50 text-white px-8 py-3 rounded-lg font-semibold transition">
                Read Documentation
            </a>
        </div>
    </div>
    <div class="flex-1 w-full relative">
        <div class="absolute inset-0 bg-blue-500/20 blur-3xl rounded-full"></div>
        <div class="relative rounded-2xl border border-cyber-700 bg-cyber-900/50 overflow-hidden shadow-2xl backdrop-blur-xl">
            {$canvas->render()}
            <div class="absolute bottom-4 left-4 right-4 text-center text-sm text-cyan-400/70">
                Interactive Canvas3D Engine (Pure PHP + WebGL)
            </div>
        </div>
    </div>
</div>
HTML;

echo Layout::render("Landing", $content, false);
