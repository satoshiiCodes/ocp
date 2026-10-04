// Splices projects.php in ONE ordered pass, so the region indices never shift.
//
//   region A  13-49    table creation           -> actions file
//   region A2 50-132   the add-project handler  -> actions file
//   region B  134-138  session success message  -> unchanged, keeps living in the page
//   region C  140-170  the two read queries     -> endpoint file
//   region D  192-203  helper functions         -> stay in the page
import fs from 'node:fs';

const APPLY = process.argv.includes('--apply');
const file = 'E:\\laragon\\www\\OCP\\projects.php';
const lines = fs.readFileSync(file, 'utf8').split('\n');

const check = (n, re, what) => {
  const line = lines[n - 1] ?? '';
  if (!re.test(line)) {
    console.error(`ABORT: line ${n} is not ${what}: ${JSON.stringify(line)}`);
    process.exit(1);
  }
};

check(13, /^\/\/ Create projects table/, 'the projects DDL comment');
check(50, /^\/\/ Process form submission/, 'the POST comment');
check(132, /^\}\s*$/, 'the closing brace of the POST guard');
check(134, /^\/\/ Check for success message in session/, 'the session-message comment');
check(140, /^\/\/ Get all projects from the database/, 'the project listing comment');
check(170, /^\}\s*$/, 'the closing brace of the engineers query');
check(172, /^\/\/ Get user details/, 'the user-details comment');
check(192, /^\/\/ Helper function to safely get POST values/, 'the helper comment');

// Region 13-132 -> the actions require
const actionsBlock = [
  '// All of this page\'s actions live in one file: it makes sure the tables exist',
  '// and adds a project together with its engineers. The form posts back to this page,',
  '// so it is pulled in before anything is read or rendered. On a validation error it',
  '// leaves $error_message and $_POST set, and the markup below repopulates the form.',
  'if (!defined(\'OCP_PROJECTS_ACTIONS_RAN\')) {',
  '    require __DIR__ . \'/actions/projects-actions.php\';',
  '}',
];

// Region 140-170 -> the endpoint require
const endpointBlock = [
  '// All of this page\'s fetching lives in one file: it returns the variables the',
  '// markup below needs, which are unpacked into this scope. A fetch failure comes',
  '// back as error_message, so it is not swallowed.',
  '$ocp_endpoint = require __DIR__ . \'/api/projects-endpoint.php\';',
  'foreach ($ocp_endpoint as $ocp_key => $ocp_value) {',
  '    if ($ocp_key === \'error_message\' && $ocp_value === \'\') {',
  '        continue;',
  '    }',
  '    ${$ocp_key} = $ocp_value;',
  '}',
  'unset($ocp_endpoint, $ocp_key, $ocp_value);',
];

// Build the output by walking the original once.
const out = [];
const take = (from, to) => { for (let i = from; i <= to; i++) out.push(lines[i - 1]); };

take(1, 12);                       // up to and including the page_data require
out.push(...actionsBlock);         // A + A2 replaced
take(133, 139);                    // blank line, session message block, blank line
out.push(...endpointBlock);        // C replaced
take(171, 191);                    // user details + display name + blank line
take(192, 203);                    // the two helper functions stay put
take(204, lines.length);           // the closing tag and all markup

console.log(`project.php: ${lines.length} -> ${out.length} lines`);
if (APPLY) {
  fs.writeFileSync(file, out.join('\n'));
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
