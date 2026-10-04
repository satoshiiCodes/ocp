// Builds purchase_request_spare_parts's three files.
//
//   node build-prsp.mjs [--apply]
//
// The page splits as:
//   12-27   the signed-in user
//   29-39   the message state, read from the session
//   40-110  six helper functions the handlers and the markup both call
//   112-324 the POST dispatcher
//   326-489 the read block
// The helpers go into a shared include, the dispatcher into the actions file, and the
// read block into the endpoint. api/get_spare_parts_pr_details.php and
// actions/generate_document_number.php are folded into those two files as well.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');

const lines = fs.readFileSync(`${ROOT}\\purchase_request_spare_parts.php`, 'utf8').replace(/\r\n/g, '\n').split('\n');
const at = (n) => lines[n - 1] ?? '';
const check = (n, re, what) => {
  if (!re.test(at(n))) {
    console.error(`ABORT: line ${n} is not ${what}: ${JSON.stringify(at(n))}`);
    process.exit(1);
  }
};

check(40, /^\/\/ Generate PR number for spare parts$/, 'the first helper comment');
check(110, /^\s*$/, 'a blank line');
check(111, /^\/\/ Process form submissions$/, 'the dispatcher comment');
check(112, /^if \(\$_SERVER\['REQUEST_METHOD'\] === 'POST'\) \{$/, 'the dispatcher');
check(324, /^\}\s*$/, 'the closing brace of the dispatcher');
check(326, /^\/\/ Fetch data$/, 'the fetch comment');
check(327, /^try \{$/, 'the fetch try');
check(489, /^\}\s*$/, 'the closing brace of the fetch');
check(491, /^\/\/ Function to get status badge class$/, 'the badge comment');

// --- 1. the shared helpers ---------------------------------------------------
const helpers = lines.slice(39, 110).join('\n');
const helperNames = [...helpers.matchAll(/^function (\w+)\(/gm)].map(m => m[1]);
console.log(`helpers: ${helperNames.join(', ')}`);

const helperOut = `<?php
/**
 * includes/purchase_request_spare_parts-functions.php
 *
 * The helpers purchase_request_spare_parts.php and its actions file share: the PR,
 * withdrawal-slip and job-order number generators, the employee-name and date
 * formatters, and the status badge class.
 *
 * They live in their own file because both entry points need them. The handlers call
 * the three generators; the page's markup calls the formatters and the badge helper;
 * and the endpoint calls formatEmployeeName() while building its dropdowns. They must
 * therefore be loaded before whichever runs first, which is why
 * actions/purchase_request_spare_parts-actions.php and
 * api/purchase_request_spare_parts-endpoint.php both require this file.
 *
 * The bodies below are lifted verbatim from purchase_request_spare_parts.php.
 */

if (defined('OCP_PURCHASE_REQUEST_SPARE_PARTS_FUNCTIONS_LOADED')) {
    return;
}
define('OCP_PURCHASE_REQUEST_SPARE_PARTS_FUNCTIONS_LOADED', true);

${helpers}
`;

// --- 2. the actions ----------------------------------------------------------
const dispatcher = lines.slice(111, 324).join('\n');
const genDoc = fs.readFileSync(`${ROOT}\\actions\\generate_document_number.php`, 'utf8')
  .replace(/\r\n/g, '\n')
  .replace(/^<\?php\r?\n/, '')
  .replace(/^session_start\(\);\r?\n/m, '')
  .replace(/^require_once '\.\.\/config\/db_config\.php';\r?\n/m, '')
  .replace(/\/\*\*[\s\S]*?\*\/\r?\n/, '')
  .replace(/\?>\s*$/, '');

const actionsOut = `<?php
/**
 * actions/purchase_request_spare_parts-actions.php
 *
 * Every action for purchase_request_spare_parts.php lives in this one file: creating
 * a purchase request, updating one, changing its status, deleting it, and handing the
 * page's JavaScript the next document numbers it asks for.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. Each branch
 * reports through the session and then redirects or re-renders, so the messages the
 * page shows are unchanged.
 *
 * The document-number action is a separate HTTP request from the page's JavaScript, so
 * it is detected by its "?generate=" parameter and answers JSON - it lived in
 * actions/generate_document_number.php, which this file replaces.
 *
 * The helpers this calls live in includes/purchase_request_spare_parts-functions.php,
 * required here so they are defined whichever entry point runs first.
 *
 * The blocks below are lifted verbatim from purchase_request_spare_parts.php and
 * generate_document_number.php: the queries and the messages are unchanged.
 */

if (defined('OCP_PURCHASE_REQUEST_SPARE_PARTS_ACTIONS_RAN')) {
    return;
}
define('OCP_PURCHASE_REQUEST_SPARE_PARTS_ACTIONS_RAN', true);

require_once __DIR__ . '/../includes/purchase_request_spare_parts-functions.php';

// ---------------------------------------------------------------------------
// The document-number lookup the page's JavaScript performs: ?generate=<what>.
// Lifted from actions/generate_document_number.php, which this file replaces.
// ---------------------------------------------------------------------------
if (isset($_GET['generate'])) {
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

${genDoc.split('\n').filter(l => l.trim() !== '' || true).join('\n')}
    exit();
}

// ---------------------------------------------------------------------------
// The form submissions.
// ---------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}

// The message state the markup reads, filled in below.
$message = '';
$message_type = '';
$swal_data = array();

// Check for session-based SweetAlert data
if (isset($_SESSION['swal_data'])) {
    $swal_data = $_SESSION['swal_data'];
    unset($_SESSION['swal_data']);
}

${dispatcher}
`;

// --- 3. the endpoint ---------------------------------------------------------
const names = ['purchase_requests', 'all_parts', 'issue_parts', 'issue_materials', 'suppliers', 'vehicles',
  'equipment', 'employees', 'formatted_employees', 'mechanics', 'formatted_mechanics', 'drivers',
  'formatted_drivers', 'status_counts', 'totalCount', 'total_requests', 'swal_data'];

let fetch = lines.slice(325, 489).join('\n');
for (const n of names) {
  const esc = n.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  fetch = fetch.replace(new RegExp(`^(\\s*)\\$${esc} = (\\[\\]|0|array\\(\\));$`, 'gm'), (m, pad, val) => `${pad}$ocp_endpoint['${n}'] = ${val};`);
  fetch = fetch.replace(new RegExp(`^(\\s*)\\$${esc} = (\\$[\\w$]+->fetch(?:All)?\\([^;]*?\\));$`, 'gm'), (m, pad, rhs) => `${pad}$ocp_endpoint['${n}'] = ${rhs};`);
  fetch = fetch.replace(new RegExp(`^(\\s*)\\$${esc} = (\\$[\\w$]+\\['total'\\]);$`, 'gm'), (m, pad, rhs) => `${pad}$ocp_endpoint['${n}'] = ${rhs};`);
  // a loop must read the entry, not a variable that is gone
  fetch = fetch.replace(new RegExp(`foreach \\(\\$${esc} as`, 'g'), `foreach ($ocp_endpoint['${n}'] as`);
}
// the error path hands its message back
fetch = fetch.replace(/\$swal_data = array\(/, "$ocp_endpoint['swal_data'] = array(");
// the array the formatted lists are appended to
fetch = fetch.replace(/\$formatted_employees\[\] = \$emp;/g, "$ocp_endpoint['formatted_employees'][] = $emp;");
fetch = fetch.replace(/\$formatted_mechanics\[\] = \$mech;/g, "$ocp_endpoint['formatted_mechanics'][] = $mech;");
fetch = fetch.replace(/\$formatted_drivers\[\] = \$drv;/g, "$ocp_endpoint['formatted_drivers'][] = $drv;");

const left = [...fetch.matchAll(new RegExp(`^\\s*\\$(${names.join('|')}) = `, 'gm'))];
console.log(`unconverted assignments in the fetch: ${left.length}`);
for (const m of left) console.log(`  ${m[0].trim()}`);

const prDetails = fs.readFileSync(`${ROOT}\\api\\get_spare_parts_pr_details.php`, 'utf8')
  .replace(/\r\n/g, '\n')
  .replace(/^<\?php\r?\n/, '')
  .replace(/^session_start\(\);\r?\n/m, '')
  .replace(/^require_once '\.\.\/config\/db_config\.php';\r?\n/m, '')
  .replace(/\/\*[\s\S]*?\*\/\r?\n/, '')
  .replace(/\?>\s*$/, '');

const endpointOut = `<?php
/**
 * api/purchase_request_spare_parts-endpoint.php
 *
 * Every read for purchase_request_spare_parts.php lives in this one file: the purchase
 * request listing with its related records, the part lists for each request type, the
 * suppliers, vehicles, equipment and employee dropdowns, the status counts and the
 * document total.
 *
 * The page pulls this in for its listing: it runs in the page's scope and returns an
 * array of the variables the markup needs, which the page unpacks. The single-record
 * lookup is a separate HTTP request from the page's own script, so it is detected here
 * by its "?id=" parameter and answers JSON instead - it lived in
 * api/get_spare_parts_pr_details.php, which this file replaces.
 *
 * Requires the shared helper include, because formatEmployeeName() builds the
 * dropdowns' display names.
 *
 * Returns
${names.map(n => ` *   ${n}`).join('\n')}
 *   display_name
 */

// ---------------------------------------------------------------------------
// The single-record lookup the page's JavaScript performs: ?id=<pr id>.
// Lifted from api/get_spare_parts_pr_details.php, which this file replaces.
// ---------------------------------------------------------------------------
if (isset($_GET['id'])) {
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

    if (!isset($_SESSION['user_id'])) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

${prDetails}
    exit();
}

$ocp_endpoint = [
${names.map(n => `    '${n}' => ${/\b(total|balance|count|amount|sum)\b/i.test(n) ? '0' : '[]'},`).join('\n')}
    'display_name' => '',
];

// Standalone guard: the read block runs in the page's scope, where the connection is
// already open. Requested on its own there is none, so it answers with the empty result.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

require_once __DIR__ . '/../includes/purchase_request_spare_parts-functions.php';

${fetch}

// --- the signed-in user, whose name the side menu prints ---------------------
$ocp_user_id = $_SESSION['user_id'] ?? null;
$stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM users WHERE id = :id");
$stmt->bindParam(':id', $ocp_user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $display_name = $user['firstname'];
    if (!empty($user['middlename'])) {
        $display_name .= ' ' . substr($user['middlename'], 0, 1) . '.';
    }
    $display_name .= ' ' . $user['lastname'];
    if (!empty($user['suffix'])) {
        $display_name .= ' ' . $user['suffix'];
    }
    $ocp_endpoint['display_name'] = $display_name;
}
unset($ocp_user_id, $user, $display_name);

return $ocp_endpoint;
`;

console.log(`\nfunctions include: ${helperOut.length} bytes`);
console.log(`actions file:      ${actionsOut.length} bytes`);
console.log(`endpoint file:     ${endpointOut.length} bytes`);

if (APPLY) {
  fs.writeFileSync(`${ROOT}\\includes\\purchase_request_spare_parts-functions.php`, helperOut);
  fs.writeFileSync(`${ROOT}\\actions\\purchase_request_spare_parts-actions.php`, actionsOut);
  fs.writeFileSync(`${ROOT}\\api\\purchase_request_spare_parts-endpoint.php`, endpointOut);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
