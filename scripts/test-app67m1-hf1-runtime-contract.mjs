import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../resources/js/nexorin-sld-editor.js', import.meta.url), 'utf8');
const bundle = readFileSync(new URL('../public/js/nexorin-sld-editor.js', import.meta.url), 'utf8');
const editorView = readFileSync(new URL('../resources/views/slds/editor.blade.php', import.meta.url), 'utf8');
const showView = readFileSync(new URL('../resources/views/slds/show.blade.php', import.meta.url), 'utf8');

const checks = [
    ['source declares transformLabel', /function\s+transformLabel\s*\(/.test(source)],
    ['production bundle declares transformLabel', /function\s+transformLabel\s*\(/.test(bundle)],
    ['production bundle still renders node transform metadata', /data-transform=/.test(bundle)],
    ['editor cache key remains at HF1 or later', /nexorin-sld-editor\.js'\)\s*}}\?v=(?:67m1hf(?:1|2)|67m2|67m3)/.test(editorView)],
    ['controlled export gate includes engineering-controller blocking', /\$hasBlockingEngineeringErrors/.test(showView) && /\$controlledExportBlocked/.test(showView)],
    ['redirected SLD validation errors are visible', /\$errors->has\('sld'\)/.test(showView) && /\$errors->first\('sld'\)/.test(showView)],
];

const failures = checks.filter(([, passed]) => !passed);
for (const [label, passed] of checks) {
    console.log(`${passed ? 'PASS' : 'FAIL'} · ${label}`);
}
if (failures.length > 0) process.exit(1);
console.log(`[APP67M.1-HF1] Runtime and export contract · ${checks.length}/${checks.length} passed`);
