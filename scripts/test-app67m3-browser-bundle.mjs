import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const bundle = readFileSync(new URL('../public/js/nexorin-sld-editor.js', import.meta.url), 'utf8');
const view = readFileSync(new URL('../resources/views/slds/editor.blade.php', import.meta.url), 'utf8');
const checks = [
    ['bundle contains APP67M.3 version', () => assert.match(bundle, /67M\.3/)],
    ['bundle contains label placement contract', () => assert.match(bundle, /deterministic-clearance-label-v1/)],
    ['bundle contains candidate scoring engine', () => assert.match(bundle, /candidate-score-label-placer/)],
    ['bundle exposes label diagnostics', () => assert.match(bundle, /labelPlacementContract/)],
    ['bundle persists signatures and conflict evidence', () => {
        assert.match(bundle, /label_placement/);
        assert.match(bundle, /equipment_conflicts/);
        assert.match(bundle, /wire_conflicts/);
    }],
    ['view cache key points to APP67M.3 bundle', () => assert.match(view, /nexorin-sld-editor\.js'\)\s*}}\?v=67m3(?:-[a-z0-9]+)*/)],
];

let passed = 0;
for (const [label, check] of checks) {
    check();
    passed += 1;
    console.log(`[PASS] ${label}`);
}
console.log(`[APP67M.3] Browser bundle contract · ${passed}/${checks.length} passed`);
