// Splices suppliers.php: keeps the initial state variables, then replaces the
// inline POST handler and the listing query with the actions + endpoint files.
import fs from 'node:fs';

const APPLY = process.argv.includes('--apply');
const file = 'E:\\laragon\\www\\OCP\\suppliers.php';
const lines = fs.readFileSync(file, 'utf8').split('\n');

// lines 23-232: "if ($_SERVER['REQUEST_METHOD'] === 'POST') {" ... listing catch
const FROM = 23;
const TO = 232;
const first = lines[FROM - 1];
const last = lines[TO - 1];

if (!/^\s*if \(\$_SERVER\['REQUEST_METHOD'\] === 'POST'\) \{/.test(first)) {
  console.error(`ABORT: line ${FROM} is not the POST guard: ${JSON.stringify(first)}`);
  process.exit(1);
}
if (last.trim() !== '}') {
  console.error(`ABORT: line ${TO} is not a closing brace: ${JSON.stringify(last)}`);
  process.exit(1);
}

const replacement = [
  '// All of this page\'s actions live in one file. The page\'s forms post back here,',
  '// so it is pulled in before anything is read or rendered. On a validation error',
  '// it leaves $swal_data and the form values set, and the markup below reopens the',
  '// right modal with them.',
  'if (!defined(\'OCP_SUPPLIERS_ACTIONS_RAN\')) {',
  '    require __DIR__ . \'/actions/suppliers-actions.php\';',
  '}',
  '',
  '// All of this page\'s fetching lives in one file: it returns the variables the',
  '// markup below needs, which are unpacked into this scope.',
  '$ocp_endpoint = require __DIR__ . \'/api/suppliers-endpoint.php\';',
  'foreach ($ocp_endpoint as $ocp_key => $ocp_value) {',
  '    ${$ocp_key} = $ocp_value;',
  '}',
  'unset($ocp_endpoint, $ocp_key, $ocp_value);',
];

const out = [...lines.slice(0, FROM - 1), ...replacement, ...lines.slice(TO)];
console.log(`replacing lines ${FROM}-${TO} (${TO - FROM + 1}) with ${replacement.length}`);
console.log(`total: ${lines.length} -> ${out.length}`);

if (APPLY) {
  fs.writeFileSync(file, out.join('\n'));
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
