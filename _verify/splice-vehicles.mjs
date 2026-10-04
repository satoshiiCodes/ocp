// Splices vehicles.php: lines 13-211 (the inline controller: delete, add and
// edit handlers plus the listing query) become the actions + endpoint requires.
import fs from 'node:fs';

const APPLY = process.argv.includes('--apply');
const file = 'E:\\laragon\\www\\OCP\\vehicles.php';
const lines = fs.readFileSync(file, 'utf8').split('\n');

const FROM = 13;
const TO = 211;

const expect = (n, re, what) => {
  if (!re.test(lines[n - 1] ?? '')) {
    console.error(`ABORT: line ${n} is not ${what}: ${JSON.stringify(lines[n - 1])}`);
    process.exit(1);
  }
};
expect(12, /page_data\.php/, 'the page_data require');
expect(FROM, /^\/\/ Process form submission for adding vehicle/, 'the controller comment');
expect(TO, /^\}\s*$/, 'the closing brace of the listing query');
expect(TO + 2, /^\/\/ Get user details/, 'the user-details comment');

const block = [
  '// $show_modal tells the markup which modal to reopen when an action was rejected.',
  '// The actions file sets it; it starts false for a plain page load.',
  '$show_modal = false;',
  '',
  '// All of this page\'s actions live in one file: deleting a vehicle (a GET) and',
  '// adding or editing one (POSTs). The form posts back to this page, so it is',
  '// pulled in before anything is read or rendered.',
  'if (!defined(\'OCP_VEHICLES_ACTIONS_RAN\')) {',
  '    require __DIR__ . \'/actions/vehicles-actions.php\';',
  '}',
  '',
  '// All of this page\'s fetching lives in one file: it returns the variables the',
  '// markup below needs, which are unpacked into this scope.',
  '$ocp_endpoint = require __DIR__ . \'/api/vehicles-endpoint.php\';',
  'foreach ($ocp_endpoint as $ocp_key => $ocp_value) {',
  '    ${$ocp_key} = $ocp_value;',
  '}',
  'unset($ocp_endpoint, $ocp_key, $ocp_value);',
];

const out = [...lines.slice(0, FROM - 1), ...block, ...lines.slice(TO)];
console.log(`vehicles.php: ${lines.length} -> ${out.length} lines`);
if (APPLY) {
  fs.writeFileSync(file, out.join('\n'));
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
