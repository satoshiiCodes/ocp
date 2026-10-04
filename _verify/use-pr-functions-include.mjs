// Replaces the two helper definitions in actions/purchase_request-actions.php with a
// require of the shared include.
//
// The page calls generatePRNumber() while rendering its form, and the handlers call
// both helpers, so neither entry point can own them. They now live in
// includes/purchase_request-functions.php, which both files require.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const file = `${ROOT}\\actions\\purchase_request-actions.php`;
const raw = fs.readFileSync(file, 'utf8');
const eol = raw.includes('\r\n') ? '\r\n' : '\n';
const lines = raw.replace(/\r\n/g, '\n').split('\n');

// lines 38-111 are "// Generate PR number" through the second helper's closing brace
const FROM = 38;
const TO = 105;
const at = (n) => lines[n - 1] ?? '';
if (!/^\/\/ Generate PR number$/.test(at(FROM))) {
  console.error(`ABORT: line ${FROM} is not the PR-number comment: ${JSON.stringify(at(FROM))}`);
  process.exit(1);
}
if (at(TO).trim() !== '}') {
  console.error(`ABORT: line ${TO} is not a closing brace: ${JSON.stringify(at(TO))}`);
  process.exit(1);
}
if (!/^function checkStockAvailability/.test(at(52))) {
  console.error(`ABORT: line 52 is not checkStockAvailability: ${JSON.stringify(at(52))}`);
  process.exit(1);
}

const block = [
  '// The two helpers the handlers call. They are shared with the page, which calls',
  '// generatePRNumber() while rendering its form, so they live in their own include',
  '// that both files require.',
  "require_once __DIR__ . '/../includes/purchase_request-functions.php';",
];

const out = [...lines.slice(0, FROM - 1), ...block, ...lines.slice(TO)];
console.log(`actions file: ${lines.length} -> ${out.length} lines`);
if (APPLY) {
  fs.writeFileSync(file, eol === '\r\n' ? out.join('\r\n') : out.join('\n'));
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
