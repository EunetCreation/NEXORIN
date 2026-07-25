import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const read = (path) => readFileSync(resolve(root, path), 'utf8');
const source = read('resources/js/nexorin-sld-editor.js');
const bundle = read('public/js/nexorin-sld-editor.js');
const view = read('resources/views/slds/editor.blade.php');
const css = read('public/css/nexorin-sld-editor.css');
const pkg = JSON.parse(read('package.json'));

const checks = [
    ['source uses a low-detail drag preview contract', source.includes("version: '67M.1-HF2'") && source.includes('dragPreviewMarkup(entries)')],
    ['pointer events are coalesced with requestAnimationFrame', source.includes('requestAnimationFrame(renderObjectMoveFrame)') && source.includes('requestAnimationFrame(renderPointerFrame)')],
    ['smart routing is deferred until the move is committed', source.includes('scheduleSmartWireSync(completed.cells.map(({ cell }) => cell))') && !source.includes('syncConnectedWires(moveSelectionSession.cells.map(({ cell }) => cell), true)')],
    ['select mode bypasses expensive drafting snap calculations', source.includes("activeSpace !== 'model' || !osnapInteractionActive()") && source.includes('const interactiveDraft = activeSpace === \'model\' && osnapInteractionActive()')],
    ['editor template exposes the dedicated drag preview layer and HF2 cache key', view.includes('data-editor-drag-preview') && /\?v=(?:67m1hf2|67m2|67m3)/.test(view)],
    ['drag preview CSS avoids expensive effects and exposes the moving state', css.includes('APP67M.1-HF2 · low-cost object drag preview') && css.includes('will-change: transform') && css.includes('.is-object-moving')],
    ['production bundle contains the deployed HF2 preview and deferred-commit path', bundle.includes('67M.1-HF2') && bundle.includes('dragPerformanceDiagnostics') && bundle.includes('is-object-moving') && bundle.includes('requestAnimationFrame')],
    ['package script exposes the HF2 validator', pkg.scripts?.['test:sld-app67m1-hf2'] === 'node scripts/test-app67m1-hf2-drag-performance.mjs'],
];

let passed = 0;
for (const [label, ok] of checks) {
    if (!ok) {
        console.error(`[FAIL] ${label}`);
        process.exitCode = 1;
    } else {
        passed += 1;
        console.log(`[PASS] ${label}`);
    }
}

if (passed === checks.length) {
    console.log(`[APP67M.1-HF2] Drag performance contract · ${passed}/${checks.length} passed`);
}
