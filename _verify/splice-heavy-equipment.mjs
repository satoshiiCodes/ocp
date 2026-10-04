// Splices heavy_equipment.php: lines 13-247 (the three POST handlers plus the two
// read queries) become the actions + endpoint requires.
import fs from 'node:fs';

const APPLY = process.argv.includes('--apply');
const file = 'E:\\laragon\\www\\OCP\\heavy_equipment.php';
const lines = fs.readFileSync(file, 'utf8').split('\n');

const FROM = 13;
const TO = 247;

const expect = (n, re, what) => {
  if (!re.test(lines[n - 1] ?? '')) {
    console.error(`ABORT: line ${n} is not ${what}: ${JSON.stringify(lines[n - 1])}`);
    process.exit(1);
  }
};
expect(12, /page_data\.php/, 'the page_data require');
expect(FROM, /^\/\/ Process form submissions/, 'the handler comment');
expect(TO, /^\}\s*$/, 'the closing brace of the edit-modal lookup');
expect(TO + 2, /^\/\/ Get user details/, 'the user-details comment');

const block = [
  '// All of this page\'s actions live in one file: deleting, editing and adding a',
  '// piece of equipment. The forms post back to this page, so it is pulled in before',
  '// anything is read or rendered.',
  'if (!defined(\'OCP_HEAVY_EQUIPMENT_ACTIONS_RAN\')) {',
  '    require __DIR__ . \'/actions/heavy_equipment-actions.php\';',
  '}',
  '',
  '// All of this page\'s fetching lives in one file: it returns the variables the',
  '// markup below needs, which are unpacked into this scope. show_edit_modal comes',
  '// back set when an edit was asked for, so the modal reopens filled in.',
  '$ocp_endpoint = require __DIR__ . \'/api/heavy_equipment-endpoint.php\';',
  'foreach ($ocp_endpoint as $ocp_key => $ocp_value) {',
  '    ${$ocp_key} = $ocp_value;',
  '}',
  'unset($ocp_endpoint, $ocp_key, $ocp_value);',
];

const out = [...lines.slice(0, FROM - 1), ...block, ...lines.slice(TO)];
console.log(`heavy_equipment.php: ${lines.length} -> ${out.length} lines`);
if (APPLY) {
  fs.writeFileSync(file, out.join('\n'));
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
