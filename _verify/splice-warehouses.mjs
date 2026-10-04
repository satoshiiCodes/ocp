// Splices warehouses.php in ONE ordered pass:
//   lines 13-177   the three POST handlers      -> actions file
//   lines 179-192  the warehouse listing query  -> endpoint file
//   lines 193-211  the user details + display name -> endpoint file
//   lines 213-223  the add form's repopulation values -> stay in the page
import fs from 'node:fs';

const APPLY = process.argv.includes('--apply');
const file = 'E:\\laragon\\www\\OCP\\warehouses.php';
const lines = fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n').split('\n');

const at = (n) => lines[n - 1] ?? '';
const check = (n, re, what) => {
  if (!re.test(at(n))) {
    console.error(`ABORT: line ${n} is not ${what}: ${JSON.stringify(at(n))}`);
    process.exit(1);
  }
};

check(12, /page_data\.php/, 'the page_data require');
check(13, /^\/\/ Process form submission/, 'the form-submission comment');
check(17, /^\s*$/, 'a blank line');
check(177, /^\}\s*$/, 'the closing brace of the add handler');
check(179, /^\/\/ Get all warehouses/, 'the listing comment');
check(192, /^\s*$/, 'a blank line');
check(193, /^\/\/ Get user details/, 'the user-details comment');
check(211, /^\}\s*$/, 'the closing brace of the display-name block');
check(212, /^\s*$/, 'a blank line');
check(213, /^\/\/ Store form values for repopulation/, 'the repopulation comment');
check(223, /^\}\s*$/, 'the closing brace of the repopulation block');
check(224, /^\?>/, 'the closing PHP tag');

const out = [];
const take = (from, to) => { for (let i = from; i <= to; i++) out.push(lines[i - 1]); };

take(1, 12);
out.push(
  '// $swal_data carries the message the actions file wants shown. It is created here,',
  '// before the actions run, so the markup below always finds it defined.',
  '$swal_data = [];',
  '',
  '// All of this page\'s actions live in one file: deleting, updating and adding a',
  '// warehouse. The forms post back to this page, so it is pulled in before anything',
  '// is read or rendered. On a rejected submission it leaves $swal_data set and the',
  '// markup below reopens the right form.',
  'if (!defined(\'OCP_WAREHOUSES_ACTIONS_RAN\')) {',
  '    require __DIR__ . \'/actions/warehouses-actions.php\';',
  '}',
  '',
  '// All of this page\'s fetching lives in one file: it returns the variables the',
  '// markup below needs, which are unpacked into this scope.',
  '$ocp_endpoint = require __DIR__ . \'/api/warehouses-endpoint.php\';',
  'foreach ($ocp_endpoint as $ocp_key => $ocp_value) {',
  '    ${$ocp_key} = $ocp_value;',
  '}',
  'unset($ocp_endpoint, $ocp_key, $ocp_value);',
  '',
);
take(212, 223);   // blank line + the repopulation block
take(224, lines.length);

console.log(`warehouses.php: ${lines.length} -> ${out.length} lines`);
if (APPLY) {
  fs.writeFileSync(file, out.join('\n'));
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
