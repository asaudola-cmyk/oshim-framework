<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Materials;

use Oshim\Ui\Canvas3D\Math\Color;

/**
 * Cyberpunk 3D Hologram Shader Material with Fresnel Rim Glow, Scanlines & Glitch fx.
 */
class HologramShaderMaterial extends Material
{
    public Color $rimColor;
    public Color $coreColor;
    public float $scanlineSpeed = 2.5;
    public float $scanlineDensity = 15.0;
    public float $glowIntensity = 1.8;
    public float $fresnelPower = 2.2;
    public float $glitchIntensity = 0.05;
    public string $blending = 'additive';

    public function __construct(
        Color|string $rimColor = '#00f2fe',
        Color|string $coreColor = '#051329',
        float $scanlineSpeed = 2.5,
        float $glowIntensity = 1.8,
        float $fresnelPower = 2.2,
        bool $wireframe = false,
        string $name = 'HologramShaderMaterial'
    ) {
        parent::__construct($name, 'ShaderMaterial');
        $this->rimColor = is_string($rimColor) ? Color::fromHex($rimColor) : $rimColor;
        $this->coreColor = is_string($coreColor) ? Color::fromHex($coreColor) : $coreColor;
        $this->color = $this->rimColor;
        $this->scanlineSpeed = $scanlineSpeed;
        $this->glowIntensity = $glowIntensity;
        $this->fresnelPower = $fresnelPower;
        $this->wireframe = $wireframe;
        $this->transparent = true;
        $this->opacity = 0.85;
    }

    /**
     * GLSL Vertex Shader for sovereign WebGL execution.
     */
    public function getVertexShader(): string
    {
        return <<<'GLSL'
attribute vec3 aPosition;
attribute vec3 aNormal;
attribute vec2 aUv;

uniform mat4 uProjectionMatrix;
uniform mat4 uViewMatrix;
uniform mat4 uModelMatrix;
uniform mat4 uNormalMatrix;
uniform float uTime;
uniform float uGlitch;

varying vec3 vNormal;
varying vec3 vPosition;
varying vec2 vUv;
varying vec3 vViewDir;

void main() {
    vUv = aUv;
    
    // Quantum holographic glitch jitter
    vec3 pos = aPosition;
    if (uGlitch > 0.0) {
        float noise = sin(pos.y * 50.0 + uTime * 20.0) * cos(uTime * 15.0);
        if (noise > 0.85) {
            pos.x += noise * uGlitch * 0.15;
            pos.z += noise * uGlitch * 0.15;
        }
    }

    vec4 worldPos = uModelMatrix * vec4(pos, 1.0);
    vPosition = worldPos.xyz;
    vNormal = normalize((uModelMatrix * vec4(aNormal, 0.0)).xyz);
    vViewDir = normalize(-worldPos.xyz);

    gl_Position = uProjectionMatrix * uViewMatrix * worldPos;
}
GLSL;
    }

    /**
     * GLSL Fragment Shader for sovereign WebGL execution with Fresnel & Scanlines.
     */
    public function getFragmentShader(): string
    {
        return <<<'GLSL'
precision mediump float;

uniform vec4 uRimColor;
uniform vec4 uCoreColor;
uniform float uTime;
uniform float uScanlineSpeed;
uniform float uScanlineDensity;
uniform float uGlowIntensity;
uniform float uFresnelPower;
uniform float uOpacity;

varying vec3 vNormal;
varying vec3 vPosition;
varying vec2 vUv;
varying vec3 vViewDir;

void main() {
    vec3 normal = normalize(vNormal);
    vec3 viewDir = normalize(vViewDir);

    // Fresnel rim calculation
    float NdotV = max(dot(normal, viewDir), 0.0);
    float fresnel = pow(1.0 - NdotV, uFresnelPower) * uGlowIntensity;

    // Moving horizontal scanlines
    float scanline = sin((vPosition.y * uScanlineDensity) - (uTime * uScanlineSpeed)) * 0.5 + 0.5;
    scanline = pow(scanline, 1.5);

    // Grid wireframe shimmer
    float grid = sin(vUv.x * 60.0) * sin(vUv.y * 60.0);
    float shimmer = step(0.92, grid) * 0.35;

    // Composite final color
    vec3 baseColor = mix(uCoreColor.rgb, uRimColor.rgb, fresnel + (scanline * 0.3) + shimmer);
    float alpha = clamp(fresnel * 1.2 + (scanline * 0.4) + 0.25, 0.0, 1.0) * uOpacity;

    gl_FragColor = vec4(baseColor * uRimColor.rgb * 1.3, alpha);
}
GLSL;
    }

    public function toThreeJsData(): array
    {
        $data = parent::toThreeJsData();
        $data['uniforms'] = [
            'uRimColor' => ['value' => $this->rimColor->toArray()],
            'uCoreColor' => ['value' => $this->coreColor->toArray()],
            'uScanlineSpeed' => ['value' => $this->scanlineSpeed],
            'uScanlineDensity' => ['value' => $this->scanlineDensity],
            'uGlowIntensity' => ['value' => $this->glowIntensity],
            'uFresnelPower' => ['value' => $this->fresnelPower],
            'uGlitch' => ['value' => $this->glitchIntensity],
        ];
        $data['vertexShader'] = $this->getVertexShader();
        $data['fragmentShader'] = $this->getFragmentShader();
        return $data;
    }
}
