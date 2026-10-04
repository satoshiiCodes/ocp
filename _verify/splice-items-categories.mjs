// Splices items_categories.php so its inline controller is replaced by the
// actions + endpoint files. Lines are 1-based and inclusive.
import fs from 'node:fs';

const APPLY = process.argv.includes('--apply');
const file = 'E:\\laragon\\www\\OCP\\items_categories.php';
const lines = fs.readFileSync(file, 'utf8').split('\n');

const FROM = 13;   // "// Create items_categories table if it doesn't exist"
const TO = 213;    // the closing brace of the POST block
const first = lines[FROM - 1];
const last = lines[TO - 1];

if (!/^\/\/ Create items_categories table if it doesn't exist/.test(first)) {
  console.error(`ABORT: line ${FROM} is not the expected start: ${JSON.stringify(first)}`);
  process.exit(1);
}
if (last.trim() !== '}') {
  console.error(`ABORT: line ${TO} is not a closing brace: ${JSON.stringify(last)}`);
  process.exit(1);
}

const replacement = [
  '// All of this page\'s actions live in one file. The page\'s forms post back here,',
  '// so it is pulled in before anything is read or rendered.',
  'if (!defined(\'OCP_ITEMS_CATEGORIES_ACTIONS_RAN\')) {',
  '    require __DIR__ . \'/actions/items_categories-actions.php\';',
  '}',
  '',
  '// All of this page\'s fetching lives in one file: it returns the variables the',
  '// markup below needs, which are unpacked into this scope.',
  '$ocp_endpoint = require __DIR__ . \'/api/items_categories-endpoint.php\';',
  'foreach ($ocp_endpoint as $ocp_key => $ocp_value) {',
  '    ${$ocp_key} = $ocp_value;',
  '}',
  'unset($ocp_endpoint, $ocp_key, $ocp_value);',
];

const out = [...lines.slice(0, FROM - 1), ...replacement, ...lines.slice(TO)];
console.log(`replacing lines ${FROM}-${TO} (${TO - FROM + 1} lines) with ${replacement.length} lines`);
console.log(`total: ${lines.length} -> ${out.length} lines`);

if (APPLY) {
  fs.writeFileSync(file, out.join('\n'));
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
