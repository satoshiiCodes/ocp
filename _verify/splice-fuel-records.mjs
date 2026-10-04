// Splices fuel_records.php: lines 32-73 (the POST guard and the two read queries)
// become the actions + endpoint requires.
import fs from 'node:fs';

const APPLY = process.argv.includes('--apply');
const file = 'E:\\laragon\\www\\OCP\\fuel_records.php';
const lines = fs.readFileSync(file, 'utf8').split('\n');
const FROM = 32;
const TO = 73;

const expect = (n, re, what) => {
  if (!re.test(lines[n - 1] ?? '')) {
    console.error(`ABORT: line ${n} is not ${what}: ${JSON.stringify(lines[n - 1])}`);
    process.exit(1);
  }
};
expect(31, /^\s*$/, 'a blank line');
expect(FROM, /^\/\/ Check if vehicle data was submitted via POST/, 'the POST comment');
expect(TO, /^\}\s*$/, 'the closing brace of the else branch');
expect(TO + 1, /^\?>/, 'the closing PHP tag');

const block = [
  '// All of this page\'s actions live in one file. This page has no forms of its',
  '// own: it is opened by a POST carrying the vehicle to show, and without one the',
  '// actions file sends the request back to the vehicle list.',
  'if (!defined(\'OCP_FUEL_RECORDS_ACTIONS_RAN\')) {',
  '    require __DIR__ . \'/actions/fuel_records-actions.php\';',
  '}',
  '',
  '// All of this page\'s fetching lives in one file: it returns the variables the',
  '// markup below needs, which are unpacked into this scope.',
  '$ocp_endpoint = require __DIR__ . \'/api/fuel_records-endpoint.php\';',
  'foreach ($ocp_endpoint as $ocp_key => $ocp_value) {',
  '    ${$ocp_key} = $ocp_value;',
  '}',
  'unset($ocp_endpoint, $ocp_key, $ocp_value);',
];

const out = [...lines.slice(0, FROM - 1), ...block, ...lines.slice(TO)];
console.log(`fuel_records.php: ${lines.length} -> ${out.length} lines`);
if (APPLY) {
  fs.writeFileSync(file, out.join('\n'));
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
