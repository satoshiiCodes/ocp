// Builds employee_registration's two files.
//
//   node build-er.mjs [--apply]
//
// The page splits as:
//   13-26    the message state and the form-repopulation variables
//   27-1375  the POST dispatcher: an 11-branch chain
//   1377-1418 the reads
// The dispatcher becomes the actions file; the reads become the endpoint. The init
// block stays in the page, because thirteen of those variables are read by the
// template - the handlers assign to them and the endpoint runs after both, so keeping
// them in the page's scope is what lets a rejected form come back filled in.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');

const lines = fs.readFileSync(`${ROOT}\\employee_registration.php`, 'utf8').replace(/\r\n/g, '\n').split('\n');
const at = (n) => lines[n - 1] ?? '';
const check = (n, re, what) => {
  if (!re.test(at(n))) {
    console.error(`ABORT: line ${n} is not ${what}: ${JSON.stringify(at(n))}`);
    process.exit(1);
  }
};

check(13, /^\/\/ Process form submission$/, 'the message-state comment');
check(18, /^\/\/ Initialize variables to avoid undefined errors$/, 'the init comment');
check(27, /^if \(\$_SERVER\['REQUEST_METHOD'\] === 'POST'\) \{$/, 'the dispatcher');
check(1375, /^\}\s*$/, 'the closing brace of the dispatcher');
check(1377, /^\/\/ Get user details$/, 'the user-details comment');
check(1419, /^\?>$/, 'the closing PHP tag');

// --- the actions -------------------------------------------------------------
const dispatcher = lines.slice(26, 1375).join('\n');
const branches = [...dispatcher.matchAll(/^(?:if|\} elseif|else) \(?/gm)].length;

const actionsOut = `<?php
/**
 * actions/employee_registration-actions.php
 *
 * Every action for employee_registration.php lives in this one file: updating an
 * employee's details, their wage, their deductions, deleting one, and the view and
 * edit requests the page's buttons make.
 *
 * The page pulls this file in after its own initialisation, so it runs in the page's
 * scope. That matters for two reasons: the form-repopulation variables the page
 * initialises are assigned here and read by the template below, and a rejected form
 * therefore comes back filled in; and $message, $message_type and $swal_data are set
 * here and read by the markup.
 *
 * The block below is lifted verbatim from employee_registration.php: the queries, the
 * wage and deduction arithmetic, and the messages are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_EMPLOYEE_REGISTRATION_ACTIONS_RAN')) {
    return;
}
define('OCP_EMPLOYEE_REGISTRATION_ACTIONS_RAN', true);

${dispatcher}
`;

// --- the endpoint ------------------------------------------------------------
const names = [];
{
  const body = lines.slice(1376, 1418).join('\n');
  for (const m of body.matchAll(/^\s*\$([a-z_][a-z0-9_]*)\s*(?:\[[^\]]*\])?\s*=[^=]/gm)) {
    const n = m[1];
    if (['pdo', 'stmt', 'i', 'key', 'value', 'row', 'item', 'e', 'user', 'user_id', 'display_name'].includes(n)) continue;
    if (/_stmt$/.test(n)) continue;
    if (!names.includes(n)) names.push(n);
  }
}
console.log(`fetch variables: ${names.join(', ')}`);

let fetch = lines.slice(1376, 1418).join('\n');
for (const n of names) {
  const esc = n.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  fetch = fetch.replace(new RegExp(`^(\\s*)\\$${esc} = (\\[\\]|0|array\\(\\)|null);`, 'gm'), (m, pad, val) => `${pad}$ocp_endpoint['${n}'] = ${val};`);
  fetch = fetch.replace(new RegExp(`^(\\s*)\\$${esc} = (\\$[\\w$]+(?:->fetch(?:All)?\\([^;]*?\\)));$`, 'gm'), (m, pad, rhs) => `${pad}$ocp_endpoint['${n}'] = ${rhs};`);
}
// the display name is computed, not fetched
fetch = fetch.replace(/^(\s*)\$display_name = \$user\['firstname'\];$/m, "$1$ocp_endpoint['display_name'] = $user['firstname'];");
fetch = fetch.replace(/^(\s*)\$display_name \.= /gm, "$1$ocp_endpoint['display_name'] .= ");

const left = [...fetch.matchAll(/^\s*\$(employees|employee_error|display_name) = /gm)];
console.log(`unconverted assignments in the fetch: ${left.length}`);
for (const m of left) console.log(`  ${m[0].trim()}`);

const endpointOut = `<?php
/**
 * api/employee_registration-endpoint.php
 *
 * Every read for employee_registration.php lives in this one file: the signed-in
 * user's display name for the side menu, and the employee table with each one's
 * current deductions.
 *
 * The page pulls this in instead of querying the database itself, so all of the page's
 * fetching is in one place. It runs in the page's scope and returns an array of the
 * variables the markup needs; the page unpacks that array. Nothing is printed here, so
 * this file cannot disturb the page's output.
 *
 * Note that the handler's own values reach the template through the page's scope, not
 * through this array: this runs after the actions file and only fills what the markup
 * reads for display.
 *
 * Returns
${names.map(n => ` *   ${n}`).join('\n')}
 *   display_name
 */

$ocp_endpoint = [
${names.map(n => `    '${n}' => [],`).join('\n')}
    'display_name' => '',
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

${fetch}

return $ocp_endpoint;
`;

console.log(`\nactions file:  ${actionsOut.length} bytes (${dispatcher.split('\n').length} lines, ${branches} branches)`);
console.log(`endpoint file: ${endpointOut.length} bytes`);

if (APPLY) {
  fs.writeFileSync(`${ROOT}\\actions\\employee_registration-actions.php`, actionsOut);
  fs.writeFileSync(`${ROOT}\\api\\employee_registration-endpoint.php`, endpointOut);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
