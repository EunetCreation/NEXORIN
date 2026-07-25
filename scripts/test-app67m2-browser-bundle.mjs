import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const bundle = readFileSync(new URL('../public/js/nexorin-sld-editor.js', import.meta.url), 'utf8');
const view = readFileSync(new URL('../resources/views/slds/editor.blade.php', import.meta.url), 'utf8');
const checks = [
    ['bundle contains APP67M.2 version', () => assert.match(bundle, /67M\.2/)],
    ['bundle contains canonical route contract', () => assert.match(bundle, /deterministic-orthogonal-channel-v2/)],
    ['bundle contains orthogonal engine identity', () => assert.match(bundle, /orthogonal-channel-a-star/)],
    ['bundle exposes routing diagnostics', () => assert.match(bundle, /routeDiagnostics/)],
    ['bundle contains route signatures and terminal evidence', () => {
        assert.match(bundle, /route_signature/);
        assert.match(bundle, /route_terminal_direction_ok/);
        assert.match(bundle, /route_orthogonal/);
    }],
    ['view cache key points to APP67M.2 bundle', () => assert.match(view, /nexorin-sld-editor\.js'\)\s*}}\?v=67m[23]/)],
];

let passed = 0;
for (const [label, check] of checks) {
    try {
        check();
        passed += 1;
        console.log(`[PASS] ${label}`);
    } catch (error) {
        console.error(`[FAIL] ${label}`);
        throw error;
    }
}
console.log(`[APP67M.2] Browser bundle contract · ${passed}/${checks.length} passed`);
