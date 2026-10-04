// Builds gasoline_purchase_order's three files.
//
//   node build-gpo.mjs [--apply]
//
// The page splits as:
//   13-41    the signed-in user and the four role flags derived from it
//   42-75    getDriverOperatorName()
//   76-79    the message state
//   80-1533  the PO dispatcher
//   1534-1566 the edit-load and session-message blocks
//   1567-1673 the read block
//   1675-1720 generatePONumber()
// The two helpers go into a shared include (generatePONumber() is called by the
// template, getDriverOperatorName() only by the handlers), the dispatcher and the two
// blocks into the actions file, and the read block into the endpoint.
// api/get_gasoline_po_details.php is folded into the endpoint, which the page's
// JavaScript posts to for one PO.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');

const lines = fs.readFileSync(`${ROOT}\\gasoline_purchase_order.php`, 'utf8').replace(/\r\n/g, '\n').split('\n');
const at = (n) => lines[n - 1] ?? '';
const check = (n, re, what) => {
  if (!re.test(at(n))) {
    console.error(`ABORT: line ${n} is not ${what}: ${JSON.stringify(at(n))}`);
    process.exit(1);
  }
};

check(42, /^\/\/ Helper function to get driver\/operator display name/, 'the driver helper comment');
check(76, /^\/\/ Process actions$/, 'the actions comment');
check(80, /^if \(\$_SERVER\['REQUEST_METHOD'\] === 'POST'\) \{$/, 'the dispatcher');
check(1532, /^\}\s*$/, 'the closing brace of the dispatcher');
check(1534, /^\/\/ Check for GET request to load PO for editing$/, 'the edit-load comment');
check(1559, /^\}\s*$/, 'the closing brace of the edit-load block');
check(1561, /^\/\/ Check for session swal data \(from redirect\)$/, 'the session comment');
check(1567, /^\/\/ Fetch data for dropdowns and tables$/, 'the fetch comment');
check(1568, /^try \{$/, 'the fetch try');
check(1673, /^\}\s*$/, 'the closing brace of the fetch');

// generatePONumber() runs from 1675 to its closing brace
let genEnd = 0;
for (let i = 1674; i < lines.length; i++) {
  if (/^function generatePONumber\(\) \{$/.test(lines[i])) {
    let depth = 1;
    for (let j = i + 1; j < lines.length; j++) {
      depth += (lines[j].match(/\{/g) || []).length - (lines[j].match(/\}/g) || []).length;
      if (depth === 0) { genEnd = j + 1; break; }
    }
    break;
  }
}
if (!genEnd) { console.error('ABORT: could not find the end of generatePONumber()'); process.exit(1); }
check(1675, /^\/\/ Generate next PO number in format/, 'the PO-number comment');
check(1676, /^function generatePONumber\(\) \{$/, 'generatePONumber');
console.log(`generatePONumber(): lines 1675-${genEnd}`);

// --- 1. the shared helpers ---------------------------------------------------
const driverHelper = lines.slice(41, 75).join('\n');
const poNumber = lines.slice(1674, genEnd).join('\n');
const helperNames = [...(driverHelper + '\n' + poNumber).matchAll(/^function (\w+)\(/gm)].map(m => m[1]);
console.log(`helpers: ${helperNames.join(', ')}`);

const helperOut = `<?php
/**
 * includes/gasoline_purchase_order-functions.php
 *
 * The two helpers gasoline_purchase_order.php and its actions file share:
 * getDriverOperatorName(), which renders a line's driver or operator, and
 * generatePONumber(), which builds the next PO number with a duplicate check.
 *
 * generatePONumber() is called by the page's template while rendering the form, and
 * the handlers call both, so neither entry point can own them. They must be loaded
 * before whichever runs first, which is why
 * actions/gasoline_purchase_order-actions.php and
 * api/gasoline_purchase_order-endpoint.php both require this file.
 *
 * The bodies below are lifted verbatim from gasoline_purchase_order.php.
 */

if (defined('OCP_GASOLINE_PURCHASE_ORDER_FUNCTIONS_LOADED')) {
    return;
}
define('OCP_GASOLINE_PURCHASE_ORDER_FUNCTIONS_LOADED', true);

${driverHelper}

${poNumber}
`;

// --- 2. the actions ----------------------------------------------------------
const dispatcher = lines.slice(79, 1532).join('\n');
const editLoad = lines.slice(1533, 1559).join('\n');
const sessionBlock = lines.slice(1560, 1566).join('\n');

const actionsOut = `<?php
/**
 * actions/gasoline_purchase_order-actions.php
 *
 * Every action for gasoline_purchase_order.php lives in this one file: creating a PO,
 * updating one, approving, completing, cancelling, deleting, updating an invoice, and
 * loading a PO into the form for editing.
 *
 * The page pulls this file in at the top, so it runs in the page's scope - which also
 * gives it the four role flags the handlers gate on ($can_approve_po, $can_delete_po,
 * $can_complete_po, $can_update_invoice), computed by the page from the signed-in user.
 *
 * The helpers it calls live in includes/gasoline_purchase_order-functions.php,
 * required here so they are defined whichever entry point runs first.
 *
 * The blocks below are lifted verbatim from gasoline_purchase_order.php: the queries,
 * the role checks and the messages are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' && !isset($_GET['edit_po'])) {
    return;
}
if (defined('OCP_GASOLINE_PURCHASE_ORDER_ACTIONS_RAN')) {
    return;
}
define('OCP_GASOLINE_PURCHASE_ORDER_ACTIONS_RAN', true);

require_once __DIR__ . '/../includes/gasoline_purchase_order-functions.php';

// The message state the markup reads, filled in below.
$swal_data = array();
$edit_po_data = null;

${dispatcher}

${editLoad}

${sessionBlock}
`;

// --- 3. the endpoint ---------------------------------------------------------
const names = [];
{
  const body = lines.slice(1566, 1673).join('\n');
  for (const m of body.matchAll(/^\s*\$([a-z_][a-z0-9_]*)\s*(?:\[[^\]]*\])?\s*=[^=]/gm)) {
    const n = m[1];
    if (['pdo', 'stmt', 'i', 'key', 'value', 'row', 'item', 'e'].includes(n)) continue;
    if (/_stmt$/.test(n)) continue;
    if (!names.includes(n)) names.push(n);
  }
}
console.log(`fetch variables: ${names.join(', ')}`);

let fetch = lines.slice(1566, 1673).join('\n');
for (const n of names) {
  const esc = n.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  fetch = fetch.replace(new RegExp(`^(\\s*)\\$${esc} = (\\[\\]|0|array\\(\\)|null);`, 'gm'), (m, pad, val) => `${pad}$ocp_endpoint['${n}'] = ${val};`);
  fetch = fetch.replace(new RegExp(`^(\\s*)\\$${esc} = (\\$[\\w$]+(?:->fetch(?:All|Column)?\\([^;]*?\\)|->fetchAll\\([^;]*?\\)|\\[[^\\]]*\\]));$`, 'gm'), (m, pad, rhs) => `${pad}$ocp_endpoint['${n}'] = ${rhs};`);
  fetch = fetch.replace(new RegExp(`^(\\s*)\\$${esc} \\+= (.*);$`, 'gm'), (m, pad, rhs) => `${pad}$ocp_endpoint['${n}'] += ${rhs};`);
  fetch = fetch.replace(new RegExp(`foreach \\(\\$${esc} as`, 'g'), `foreach ($ocp_endpoint['${n}'] as`);
  fetch = fetch.replace(new RegExp(`while \\(\\$${esc} > 0\\)`, 'g'), `while ($ocp_endpoint['${n}'] > 0)`);
}
fetch = fetch.replace(/\$swal_data = array\(/, "$ocp_endpoint['swal_data'] = array(");

const left = [...fetch.matchAll(new RegExp(`^\\s*\\$(${names.join('|')}) = `, 'gm'))];
console.log(`unconverted assignments in the fetch: ${left.length}`);
for (const m of left) console.log(`  ${m[0].trim()}`);

const poDetails = fs.readFileSync(`${ROOT}\\api\\get_gasoline_po_details.php`, 'utf8')
  .replace(/\r\n/g, '\n')
  .replace(/^<\?php\r?\n/, '')
  .replace(/^session_start\(\);\r?\n/m, '')
  .replace(/^require_once '\.\.\/config\/db_config\.php';\r?\n/m, '')
  .replace(/\?>\s*$/, '');

const endpointOut = `<?php
/**
 * api/gasoline_purchase_order-endpoint.php
 *
 * Every read for gasoline_purchase_order.php lives in this one file: the suppliers,
 * vehicles, equipment, employees, tanks and users for its dropdowns, the PO listing,
 * the status counts and the pending summary - plus the single-PO lookup its
 * JavaScript posts for.
 *
 * The page pulls this in for its listing: it runs in the page's scope and returns an
 * array of the variables the markup needs, which the page unpacks. The lookup is a
 * separate HTTP request from the page's own script, so it is detected here by its
 * "po_id" parameter and answers JSON instead - it lived in api/get_gasoline_po_details.php,
 * which this file replaces.
 *
 * Requires the shared helper include, because generatePONumber() is one of those
 * helpers and the template calls it.
 *
 * Returns
${names.map(n => ` *   ${n}`).join('\n')}
 *   swal_data
 *   edit_po_data
 *   display_name
 */

// ---------------------------------------------------------------------------
// The single-PO lookup the page's JavaScript posts for. Lifted from
// api/get_gasoline_po_details.php, which this file replaces.
// ---------------------------------------------------------------------------
if (isset($_POST['po_id'])) {
    $ocp_dir = __DIR__;
    for ($ocp_i = 0; $ocp_i < 4 && !is_file($ocp_dir . '/config/db_config.php'); $ocp_i++) {
        $ocp_parent = dirname($ocp_dir);
        if ($ocp_parent === $ocp_dir) {
            break;
        }
        $ocp_dir = $ocp_parent;
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($pdo)) {
        require_once $ocp_dir . '/config/db_config.php';
    }
    unset($ocp_dir, $ocp_i, $ocp_parent);

    header('Content-Type: application/json');
${poDetails}
    exit();
}

$ocp_endpoint = [
${names.map(n => `    '${n}' => ${/^(pending_value)$/.test(n) ? 'null' : (/^(exists)$/.test(n) ? '0' : '[]')},`).join('\n')}
    // Seeded from the actions file, so a message or an edit form it set is not wiped
    // out by the endpoint running after it.
    'swal_data' => $swal_data ?? [],
    'edit_po_data' => $edit_po_data ?? null,
    'display_name' => '',
];

// Standalone guard: the read block runs in the page's scope, where the connection is
// already open. Requested on its own there is none, so it answers with the empty result.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

require_once __DIR__ . '/../includes/gasoline_purchase_order-functions.php';

${fetch}

return $ocp_endpoint;
`;

console.log(`\nfunctions include: ${helperOut.length} bytes`);
console.log(`actions file:      ${actionsOut.length} bytes`);
console.log(`endpoint file:     ${endpointOut.length} bytes`);

if (APPLY) {
  fs.writeFileSync(`${ROOT}\\includes\\gasoline_purchase_order-functions.php`, helperOut);
  fs.writeFileSync(`${ROOT}\\actions\\gasoline_purchase_order-actions.php`, actionsOut);
  fs.writeFileSync(`${ROOT}\\api\\gasoline_purchase_order-endpoint.php`, endpointOut);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
