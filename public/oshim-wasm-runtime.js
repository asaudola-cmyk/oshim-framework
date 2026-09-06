/**
 * 👑 Sovereign OSHIM WebAssembly Browser Runtime
 * Loads and executes pure PHP-generated .wasm modules at native CPU speed.
 */
class OshimWasm {
    static async load(wasmUrl = '/app.wasm') {
        const response = await fetch(wasmUrl);
        const buffer = await response.arrayBuffer();
        const { instance } = await WebAssembly.instantiate(buffer);
        return instance.exports;
    }
}
if (typeof window !== 'undefined') {
    window.OshimWasm = OshimWasm;
}