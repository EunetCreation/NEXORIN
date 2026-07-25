import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const bundle = readFileSync(new URL('../public/js/nexorin-sld-editor.js', import.meta.url), 'utf8');
const view = readFileSync(new URL('../resources/views/slds/editor.blade.php', import.meta.url), 'utf8');
const checks = [
    ['bundle contains APP67M.4 version', () => assert.match(bundle, /67M\.4/)],
    ['bundle contains visual-regression contract', () => assert.match(bundle, /deterministic-scene-fingerprint-v1/)],
    ['bundle contains canonical scene auditor', () => assert.match(bundle, /canonical-scene-auditor/)],
    ['bundle exposes visual regression diagnostics', () => assert.match(bundle, /visualRegressionContract/)],
    ['bundle persists fingerprint and baseline evidence', () => {
        assert.match(bundle, /visual_regression/);
        assert.match(bundle, /baseline_fingerprint/);
        assert.match(bundle, /coordinate_tolerance/);
    }],
    ['view cache key points to APP67M.4 bundle', () => assert.match(view, /nexorin-sld-editor\.js'\)\s*}}\?v=67m3-67m4/)],
];

let passed = 0;
for (const [label, check] of checks) {
    check();
    passed += 1;
    console.log(`[PASS] ${label}`);
}
console.log(`[APP67M.4] Browser bundle contract · ${passed}/${checks.length} passed`);
