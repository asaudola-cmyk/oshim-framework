<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D;

use Oshim\Ui\Canvas3D\Core\Scene3D;
use Oshim\Ui\Canvas3D\Serialization\ThreeJsSerializer;
use Oshim\Ui\Dsl\Element;

/**
 * Sovereign WebGL / 3D Canvas Interactive Widget.
 * Renders high-performance 3D Holograms and Three.js scene graphs with zero external dependencies.
 */
class Canvas3DWidget extends Element
{
    private Scene3D $scene;
    private string $width;
    private string $height;
    private bool $showHud = true;
    private bool $orbitControls = true;
    private bool $autoRotate = true;
    private float $autoRotateSpeed = 0.8;
    private string $title = 'Quantum 3D Holographic Canvas';

    public function __construct(
        Scene3D $scene,
        string $width = '100%',
        string $height = '520px',
        string $title = 'Quantum 3D Holographic Canvas'
    ) {
        parent::__construct('div');
        $this->scene = $scene;
        $this->width = $width;
        $this->height = $height;
        $this->title = $title;
        $this->class('oshim-canvas3d-wrapper relative select-none overflow-hidden rounded-2xl border border-cyan-500/20 shadow-[0_0_50px_rgba(0,242,254,0.08)] bg-[#070913]');
    }

    public static function create(
        Scene3D $scene,
        string $width = '100%',
        string $height = '520px',
        string $title = 'Quantum 3D Holographic Canvas'
    ): self {
        return new self($scene, $width, $height, $title);
    }

    public function setDimensions(string $width, string $height): self
    {
        $this->width = $width;
        $this->height = $height;
        return $this;
    }

    public function showHud(bool $show = true): self
    {
        $this->showHud = $show;
        return $this;
    }

    public function enableOrbit(bool $enable = true): self
    {
        $this->orbitControls = $enable;
        return $this;
    }

    public function setAutoRotate(bool $enable, float $speed = 0.8): self
    {
        $this->autoRotate = $enable;
        $this->autoRotateSpeed = $speed;
        return $this;
    }

    public function getScene(): Scene3D
    {
        return $this->scene;
    }

    public function render(): string
    {
        $uid = 'oshim_gl_' . substr(md5(uniqid('3d', true)), 0, 8);
        $sceneJson = ThreeJsSerializer::toJson($this->scene);
        $escapedTitle = htmlspecialchars($this->title, ENT_QUOTES, 'UTF-8');
        $meshCount = count($this->scene->getAllMeshes());
        $vertexCount = 0;
        foreach ($this->scene->getAllGeometries() as $g) {
            $vertexCount += $g->getVertexCount();
        }

        $autoRotateJs = $this->autoRotate ? 'true' : 'false';
        $hudHtml = '';
        if ($this->showHud) {
            $hudHtml = <<<HTML
            <!-- Sovereign 3D Hologram HUD Overlay -->
            <div class="absolute top-4 left-4 z-20 flex items-center gap-3 bg-black/60 backdrop-blur-md px-3.5 py-1.5 rounded-xl border border-cyan-500/30 text-xs text-cyan-300 font-mono shadow-lg">
                <span class="inline-block w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
                <span class="font-semibold text-white tracking-wider">{$escapedTitle}</span>
                <span class="text-cyan-600">|</span>
                <span id="{$uid}_fps">60 FPS</span>
                <span class="text-cyan-600">|</span>
                <span>{$meshCount} Meshes ({$vertexCount} Verts)</span>
            </div>

            <!-- Interactive Viewport Controls Bar -->
            <div class="absolute bottom-4 right-4 z-20 flex items-center gap-2 bg-black/60 backdrop-blur-md p-1.5 rounded-xl border border-white/10 text-xs font-mono">
                <button type="button" id="{$uid}_btn_spin" class="px-2.5 py-1 rounded-lg bg-cyan-950/60 hover:bg-cyan-900/80 text-cyan-300 border border-cyan-500/40 transition-colors">
                    Spin: ON
                </button>
                <button type="button" id="{$uid}_btn_wire" class="px-2.5 py-1 rounded-lg bg-slate-900/80 hover:bg-slate-800 text-slate-300 border border-slate-700 transition-colors">
                    Wireframe
                </button>
                <button type="button" id="{$uid}_btn_reset" class="px-2.5 py-1 rounded-lg bg-slate-900/80 hover:bg-slate-800 text-slate-300 border border-slate-700 transition-colors">
                    Reset Cam
                </button>
            </div>
HTML;
        }

        return <<<HTML
<div id="{$uid}_container" class="relative w-full h-[{$this->height}] bg-gradient-to-b from-[#080b18] to-[#04050a] overflow-hidden rounded-2xl border border-cyan-500/20 shadow-2xl">
    {$hudHtml}
    <!-- Background Cyber Grid -->
    <div class="absolute inset-0 pointer-events-none opacity-20 bg-[radial-gradient(#00f2fe_1px,transparent_1px)] [background-size:24px_24px]"></div>

    <!-- WebGL Rendering Canvas -->
    <canvas id="{$uid}_canvas" class="w-full h-full block cursor-grab active:cursor-grabbing"></canvas>

    <!-- Embedded Three.js JSON Data for Interoperability -->
    <script type="application/json" id="{$uid}_data">
    {$sceneJson}
    </script>

    <!-- Autonomous Sovereign WebGL & Canvas3D Engine Runtime -->
    <script>
    (function() {
        const uid = "{$uid}";
        const canvas = document.getElementById(uid + "_canvas");
        if (!canvas) return;

        const dataScript = document.getElementById(uid + "_data");
        const sceneData = dataScript ? JSON.parse(dataScript.textContent || "{}") : {};

        let gl = canvas.getContext("webgl", { antialias: true, alpha: true }) || 
                 canvas.getContext("experimental-webgl");

        // Viewport & resize handling
        function resize() {
            const rect = canvas.getBoundingClientRect();
            const dpr = window.devicePixelRatio || 1;
            canvas.width = Math.max(300, rect.width * dpr);
            canvas.height = Math.max(200, rect.height * dpr);
            if (gl) gl.viewport(0, 0, canvas.width, canvas.height);
        }
        window.addEventListener("resize", resize);
        resize();

        let isSpinning = {$autoRotateJs};
        let wireframeMode = false;
        let rotX = 0.25;
        let rotY = 0.4;
        let cameraDist = 6.0;
        let isDragging = false;
        let lastMouseX = 0;
        let lastMouseY = 0;

        // Mouse Drag Orbit Controls
        canvas.addEventListener("mousedown", (e) => {
            isDragging = true;
            lastMouseX = e.clientX;
            lastMouseY = e.clientY;
        });
        window.addEventListener("mouseup", () => { isDragging = false; });
        window.addEventListener("mousemove", (e) => {
            if (!isDragging) return;
            const dx = e.clientX - lastMouseX;
            const dy = e.clientY - lastMouseY;
            rotY += dx * 0.01;
            rotX += dy * 0.01;
            rotX = Math.max(-1.5, Math.min(1.5, rotX));
            lastMouseX = e.clientX;
            lastMouseY = e.clientY;
        });
        canvas.addEventListener("wheel", (e) => {
            e.preventDefault();
            cameraDist += e.deltaY * 0.005;
            cameraDist = Math.max(1.5, Math.min(25.0, cameraDist));
        }, { passive: false });

        // HUD Button Binds
        const btnSpin = document.getElementById(uid + "_btn_spin");
        if (btnSpin) {
            btnSpin.addEventListener("click", () => {
                isSpinning = !isSpinning;
                btnSpin.textContent = "Spin: " + (isSpinning ? "ON" : "OFF");
                btnSpin.className = isSpinning 
                    ? "px-2.5 py-1 rounded-lg bg-cyan-950/60 text-cyan-300 border border-cyan-500/40"
                    : "px-2.5 py-1 rounded-lg bg-slate-900/80 text-slate-400 border border-slate-700";
            });
        }
        const btnWire = document.getElementById(uid + "_btn_wire");
        if (btnWire) {
            btnWire.addEventListener("click", () => {
                wireframeMode = !wireframeMode;
                btnWire.className = wireframeMode 
                    ? "px-2.5 py-1 rounded-lg bg-cyan-950/60 text-cyan-300 border border-cyan-500/40"
                    : "px-2.5 py-1 rounded-lg bg-slate-900/80 text-slate-400 border border-slate-700";
            });
        }
        const btnReset = document.getElementById(uid + "_btn_reset");
        if (btnReset) {
            btnReset.addEventListener("click", () => {
                rotX = 0.25; rotY = 0.4; cameraDist = 6.0;
            });
        }

        // WebGL Pipeline Setup
        let prog = null;
        let buffers = [];

        if (gl) {
            const vsSource = `
                attribute vec3 aPos;
                attribute vec3 aNormal;
                uniform mat4 uMatrix;
                uniform mat4 uNormMatrix;
                uniform float uTime;
                varying vec3 vNormal;
                varying vec3 vPos;
                void main() {
                    vNormal = mat3(uNormMatrix) * aNormal;
                    vPos = aPos;
                    gl_Position = uMatrix * vec4(aPos, 1.0);
                }
            `;
            const fsSource = `
                precision mediump float;
                uniform vec3 uColor;
                uniform float uTime;
                uniform float uWire;
                varying vec3 vNormal;
                varying vec3 vPos;
                void main() {
                    vec3 n = normalize(vNormal);
                    vec3 lightDir = normalize(vec3(0.5, 1.0, 0.8));
                    float diff = max(dot(n, lightDir), 0.15);
                    
                    // Hologram scanline effect
                    float scan = sin(vPos.y * 20.0 - uTime * 3.0) * 0.25 + 0.75;
                    // Fresnel edge glow
                    float fresnel = pow(1.0 - abs(dot(n, vec3(0.0, 0.0, 1.0))), 2.0) * 1.5;
                    
                    vec3 finalCol = (uColor * diff * scan) + (vec3(0.0, 0.95, 1.0) * fresnel);
                    gl_FragColor = vec4(finalCol, 0.9);
                }
            `;

            function createShader(type, src) {
                const s = gl.createShader(type);
                gl.shaderSource(s, src);
                gl.compileShader(s);
                return s;
            }
            const vs = createShader(gl.VERTEX_SHADER, vsSource);
            const fs = createShader(gl.FRAGMENT_SHADER, fsSource);
            prog = gl.createProgram();
            gl.attachShader(prog, vs);
            gl.attachShader(prog, fs);
            gl.linkProgram(prog);

            // Buffer geometries from sceneData
            if (sceneData.geometries && sceneData.geometries.length > 0) {
                sceneData.geometries.forEach(geom => {
                    const posAttr = geom.data?.attributes?.position;
                    const normAttr = geom.data?.attributes?.normal;
                    const idxAttr = geom.data?.index;

                    if (posAttr && posAttr.array) {
                        const pBuf = gl.createBuffer();
                        gl.bindBuffer(gl.ARRAY_BUFFER, pBuf);
                        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(posAttr.array), gl.STATIC_DRAW);

                        let nBuf = null;
                        if (normAttr && normAttr.array) {
                            nBuf = gl.createBuffer();
                            gl.bindBuffer(gl.ARRAY_BUFFER, nBuf);
                            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(normAttr.array), gl.STATIC_DRAW);
                        }

                        let iBuf = null;
                        let count = posAttr.array.length / 3;
                        if (idxAttr && idxAttr.array) {
                            iBuf = gl.createBuffer();
                            gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, iBuf);
                            gl.bufferData(gl.ELEMENT_ARRAY_BUFFER, new Uint16Array(idxAttr.array), gl.STATIC_DRAW);
                            count = idxAttr.array.length;
                        }

                        buffers.push({
                            id: geom.uuid,
                            posBuf: pBuf,
                            normBuf: nBuf,
                            idxBuf: iBuf,
                            count: count,
                            hasIndices: !!iBuf
                        });
                    }
                });
            }

            gl.enable(gl.DEPTH_TEST);
            gl.enable(gl.BLEND);
            gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);
        }

        // Matrix Math Helpers
        function mat4Perspective(out, fov, aspect, near, far) {
            const f = 1.0 / Math.tan(fov * Math.PI / 360.0);
            const rangeInv = 1.0 / (near - far);
            out[0] = f / aspect; out[1] = 0; out[2] = 0; out[3] = 0;
            out[4] = 0; out[5] = f; out[6] = 0; out[7] = 0;
            out[8] = 0; out[9] = 0; out[10] = (near + far) * rangeInv; out[11] = -1;
            out[12] = 0; out[13] = 0; out[14] = near * far * rangeInv * 2; out[15] = 0;
        }

        function mat4Multiply(out, a, b) {
            for (let i = 0; i < 4; i++) {
                for (let j = 0; j < 4; j++) {
                    out[i*4 + j] = a[i*4 + 0] * b[0*4 + j] +
                                   a[i*4 + 1] * b[1*4 + j] +
                                   a[i*4 + 2] * b[2*4 + j] +
                                   a[i*4 + 3] * b[3*4 + j];
                }
            }
        }

        // Render Animation Loop
        let lastTime = performance.now();
        let frameCount = 0;
        let fpsTimer = 0;
        const fpsEl = document.getElementById(uid + "_fps");

        function loop(now) {
            const dt = (now - lastTime) / 1000.0;
            lastTime = now;
            frameCount++;
            fpsTimer += dt;
            if (fpsTimer >= 0.5 && fpsEl) {
                const fps = Math.round(frameCount / fpsTimer);
                fpsEl.textContent = fps + " FPS";
                frameCount = 0;
                fpsTimer = 0;
            }

            if (isSpinning) {
                rotY += dt * {$this->autoRotateSpeed};
            }

            if (gl && prog) {
                gl.clearColor(0.04, 0.05, 0.09, 1.0);
                gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);

                gl.useProgram(prog);

                const uMatrix = gl.getUniformLocation(prog, "uMatrix");
                const uNormMatrix = gl.getUniformLocation(prog, "uNormMatrix");
                const uColor = gl.getUniformLocation(prog, "uColor");
                const uTime = gl.getUniformLocation(prog, "uTime");
                const uWire = gl.getUniformLocation(prog, "uWire");

                gl.uniform1f(uTime, now / 1000.0);
                gl.uniform1f(uWire, wireframeMode ? 1.0 : 0.0);
                gl.uniform3f(uColor, 0.0, 0.95, 1.0);

                // Projection
                const proj = new Float32Array(16);
                mat4Perspective(proj, 45, canvas.width / canvas.height, 0.1, 100.0);

                // View matrix
                const view = new Float32Array([
                    Math.cos(rotY), 0, -Math.sin(rotY), 0,
                    Math.sin(rotX)*Math.sin(rotY), Math.cos(rotX), Math.sin(rotX)*Math.cos(rotY), 0,
                    Math.cos(rotX)*Math.sin(rotY), -Math.sin(rotX), Math.cos(rotX)*Math.cos(rotY), 0,
                    0, 0, -cameraDist, 1
                ]);

                const mvp = new Float32Array(16);
                mat4Multiply(mvp, proj, view);
                gl.uniformMatrix4fv(uMatrix, false, mvp);
                gl.uniformMatrix4fv(uNormMatrix, false, view);

                const aPos = gl.getAttribLocation(prog, "aPos");
                const aNormal = gl.getAttribLocation(prog, "aNormal");

                buffers.forEach(b => {
                    gl.bindBuffer(gl.ARRAY_BUFFER, b.posBuf);
                    gl.vertexAttribPointer(aPos, 3, gl.FLOAT, false, 0, 0);
                    gl.enableVertexAttribArray(aPos);

                    if (b.normBuf && aNormal !== -1) {
                        gl.bindBuffer(gl.ARRAY_BUFFER, b.normBuf);
                        gl.vertexAttribPointer(aNormal, 3, gl.FLOAT, false, 0, 0);
                        gl.enableVertexAttribArray(aNormal);
                    }

                    const renderMode = wireframeMode ? gl.LINES : gl.TRIANGLES;
                    if (b.hasIndices) {
                        gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, b.idxBuf);
                        gl.drawElements(renderMode, b.count, gl.UNSIGNED_SHORT, 0);
                    } else {
                        gl.drawArrays(renderMode, 0, b.count);
                    }
                });
            }

            requestAnimationFrame(loop);
        }
        requestAnimationFrame(loop);
    })();
    </script>
</div>
HTML;
    }
}
