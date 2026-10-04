// Builds actions/purchase_request-actions.php and api/purchase_request-endpoint.php
// from purchase_request.php, and folds api/get_pr_details.php into the endpoint.
//
//   node build-purchase-request.mjs [--apply]
//
// The page's controller has three parts:
//   33-42   the message state, read from the session
//   44-112  two helper functions the handlers call
//   114-409 the POST dispatcher
//   411-479 the rendering fetch
// The first three are the page's actions; the last is its fetching. get_pr_details.php
// is a GET endpoint the page's JavaScript calls for one PR, so it becomes the
// endpoint's AJAX branch.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');

const lines = fs.readFileSync(`${ROOT}\\purchase_request.php`, 'utf8').replace(/\r\n/g, '\n').split('\n');
const at = (n) => lines[n - 1] ?? '';
const check = (n, re, what) => {
  if (!re.test(at(n))) {
    console.error(`ABORT: line ${n} is not ${what}: ${JSON.stringify(at(n))}`);
    process.exit(1);
  }
};

// boundaries
check(44, /^\/\/ Generate PR number$/, 'the PR-number comment');
check(45, /^function generatePRNumber/, 'the first helper');
check(114, /^if \(\$_SERVER\['REQUEST_METHOD'\] === 'POST'\) \{$/, 'the POST dispatcher');
check(408, /^\}\s*$/, 'the closing brace of the dispatcher');
check(411, /^try \{$/, 'the fetch try');
check(479, /^\}\s*$/, 'the closing brace of the fetch');
check(481, /^\/\/ Function to get status badge class$/, 'the badge helper comment');

// --- the actions: helpers plus dispatcher -----------------------------------
const helpers = lines.slice(43, 112);     // 44-112
const dispatcher = lines.slice(113, 408); // 114-408

const actionsHeader = `<?php
/**
 * actions/purchase_request-actions.php
 *
 * Every action for purchase_request.php lives in this one file: creating a purchase
 * request, updating one, changing its status, and deleting it.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. Every
 * branch reports through the session and then redirects or re-renders, so the
 * messages the page shows are unchanged.
 *
 * The two helpers the handlers call (generatePRNumber and checkStockAvailability)
 * live here with them, because that is what they serve.
 *
 * The bodies below are lifted verbatim from purchase_request.php: the queries, the
 * stock checks and the messages are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_PURCHASE_REQUEST_ACTIONS_RAN')) {
    return;
}
define('OCP_PURCHASE_REQUEST_ACTIONS_RAN', true);

// The message state the markup reads, filled in below.
$message = '';
$message_type = '';
$swal_data = array();

// Check for session-based SweetAlert data
if (isset($_SESSION['swal_data'])) {
    $swal_data = $_SESSION['swal_data'];
    unset($_SESSION['swal_data']);
}

`;

const actions = actionsHeader + helpers.join('\n') + '\n\n' + dispatcher.join('\n') + '\n';

// --- the endpoint: the fetch plus the AJAX branch ---------------------------
const fetch = lines.slice(410, 479);      // 411-479

const ajax = fs.readFileSync(`${ROOT}\\api\\get_pr_details.php`, 'utf8').replace(/\r\n/g, '\n')
  // drop the standalone bootstrap: the endpoint carries its own
  .replace(/^<\?php\n/, '')
  .replace(/^session_start\(\);\n/m, '')
  .replace(/^require_once '\.\.\/config\/db_config\.php';\n/m, '')
  .replace(/\?>\s*$/, '');

const endpointHeader = `<?php
/**
 * api/purchase_request-endpoint.php
 *
 * Every read for purchase_request.php lives in this one file: the page's own listing
 * with its filters and dropdowns, and the single-record lookup the page's JavaScript
 * makes for one purchase request.
 *
 * The page pulls this in for its listing: it runs in the page's scope and returns an
 * array of the variables the markup needs, which the page unpacks. The lookup is a
 * separate HTTP request from the page's own script, so it is detected here by its
 * "?id=" parameter and answers JSON instead - which is why this file both returns an
 * array and, on that one path, prints.
 *
 * Reads from the page's scope:
 *   $pdo  the connection, opened by the page
 *
 * Returns (listing)
${[]}
 */

// ---------------------------------------------------------------------------
// The single-record lookup the page's JavaScript performs: ?id=<pr id>.
// Lifted from api/get_pr_details.php, which this file replaces.
// ---------------------------------------------------------------------------
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
if (isset($_GET['id'])) {
    if (!isset($pdo)) {
        require_once $ocp_dir . '/config/db_config.php';
    }
    header('Content-Type: application/json');
${ajax.split('\n').filter(l => l.trim() !== '').map(l => l.length ? l : l).join('\n')}
    exit();
}
unset($ocp_dir, $ocp_i, $ocp_parent);

// ---------------------------------------------------------------------------
// The listing the page renders.
// ---------------------------------------------------------------------------
$ocp_endpoint = [];

${fetch.join('\n')}

return $ocp_endpoint;
`;

// find what the fetch assigns, for the return
const fetchVars = new Map();
for (const line of fetch) {
  for (const m of line.matchAll(/\$([a-z_][a-z0-9_]*)\s*(?:\[[^\]]*\])?\s*=[^=]/g)) {
    const n = m[1];
    if (['pdo', 'stmt', 'i', 'key', 'value', 'row', 'item'].includes(n)) continue;
    if (/_stmt$/.test(n)) continue;
    if (!fetchVars.has(n)) fetchVars.set(n, true);
  }
}
const returns = [...fetchVars.keys()];
const finalEndpoint = endpointHeader
  .replace('${[]}', returns.map(n => ` *   ${n}`).join('\n'))
  .replace('$ocp_endpoint = [];', '$ocp_endpoint = [\n' + returns.map(n => `    '${n}' => null,`).join('\n') + '\n];');

console.log(`fetch assigns: ${returns.join(', ')}`);
console.log(`actions file:  ${actions.length} bytes`);
console.log(`endpoint file: ${finalEndpoint.length} bytes`);

if (APPLY) {
  fs.writeFileSync(`${ROOT}\\actions\\purchase_request-actions.php`, actions);
  fs.writeFileSync(`${ROOT}\\api\\purchase_request-endpoint.php`, finalEndpoint);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
