// Builds inventory's three files.
//
//   node build-inventory.mjs [--apply]
//
// The page splits as:
//   13-31    the signed-in user
//   33-135   two helpers that recompute the inventory table
//   136-145  the message state, read from the session
//   147-1022 the handlers (a delete block, a movement block and an edit block) and
//            the read block at the end
// The helpers go into a shared include, the handlers into the actions file, and the
// read block into the endpoint. api/get_movement_details.php is folded into the
// endpoint, which its JavaScript calls for one movement.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');

const lines = fs.readFileSync(`${ROOT}\\inventory.php`, 'utf8').replace(/\r\n/g, '\n').split('\n');
const at = (n) => lines[n - 1] ?? '';
const check = (n, re, what) => {
  if (!re.test(at(n))) {
    console.error(`ABORT: line ${n} is not ${what}: ${JSON.stringify(at(n))}`);
    process.exit(1);
  }
};

check(33, /^\/\/ Function to update inventory table$/, 'the first helper comment');
check(98, /^\/\/ Function to update all inventory records$/, 'the second helper comment');
check(136, /^\/\/ Process stock movements$/, 'the message-state comment');
check(147, /^\/\/ Process delete action$/, 'the delete comment');
check(258, /^if \(\$_SERVER\['REQUEST_METHOD'\] === 'POST'\) \{$/, 'the movement block');
check(787, /^\/\/ Process edit action$/, 'the edit comment');
check(913, /^\/\/ Update all inventory records at the start to ensure data consistency$/, 'the recompute comment');
check(916, /^\/\/ Fetch data for dropdowns and tables$/, 'the fetch comment');
check(917, /^try \{$/, 'the fetch try');
check(1022, /^\}\s*$/, 'the closing brace of the fetch');
check(1024, /^<!DOCTYPE html>$/, 'the template');

// --- 1. the shared helpers ---------------------------------------------------
const helpers = lines.slice(32, 135).join('\n');
const helperNames = [...helpers.matchAll(/^function (\w+)\(/gm)].map(m => m[1]);
console.log(`helpers: ${helperNames.join(', ')}`);

const helperOut = `<?php
/**
 * includes/inventory-functions.php
 *
 * The two helpers inventory.php and its actions file share: updateInventoryTable(),
 * which recomputes one item's stock in one warehouse from its movements, and
 * updateAllInventory(), which does that for everything.
 *
 * They live in their own file because both entry points need them. The page calls
 * updateAllInventory() before it reads, and every movement handler calls one or both
 * after it writes. They must therefore be loaded before whichever runs first, which
 * is why actions/inventory-actions.php and api/inventory-endpoint.php both require
 * this file.
 *
 * The bodies below are lifted verbatim from inventory.php.
 */

if (defined('OCP_INVENTORY_FUNCTIONS_LOADED')) {
    return;
}
define('OCP_INVENTORY_FUNCTIONS_LOADED', true);

${helpers}
`;

// --- 2. the actions ----------------------------------------------------------
// The three handler blocks are consecutive: delete (147-256), movements (258-786) and
// edit (788-912).
const handlers = lines.slice(146, 912).join('\n');
console.log(`handler region: lines 147-912 (${handlers.split('\n').length} lines)`);

const actionsOut = `<?php
/**
 * actions/inventory-actions.php
 *
 * Every action for inventory.php lives in this one file: deleting a movement, the
 * stock movements themselves (in, out, transfer and adjustment), and editing a
 * movement.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. Each branch
 * reports through $swal_data and redirects or re-renders, so the messages the page
 * shows are unchanged.
 *
 * The helpers it calls live in includes/inventory-functions.php, required here so they
 * are defined whichever entry point runs first.
 *
 * The blocks below are lifted verbatim from inventory.php: the queries, the stock
 * arithmetic and the messages are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_INVENTORY_ACTIONS_RAN')) {
    return;
}
define('OCP_INVENTORY_ACTIONS_RAN', true);

require_once __DIR__ . '/../includes/inventory-functions.php';

// The message state the markup reads, filled in below.
$message = '';
$message_type = '';
$swal_data = array();

// Check for session-based SweetAlert data (for redirects)
if (isset($_SESSION['swal_data'])) {
    $swal_data = $_SESSION['swal_data'];
    unset($_SESSION['swal_data']);
}

${handlers}
`;

// --- 3. the endpoint ---------------------------------------------------------
const names = [];
{
  const body = lines.slice(912, 1022).join('\n');
  for (const m of body.matchAll(/^\s*\$([a-z_][a-z0-9_]*)\s*(?:\[[^\]]*\])?\s*=[^=]/gm)) {
    const n = m[1];
    if (['pdo', 'stmt', 'i', 'key', 'value', 'row', 'item', 'e'].includes(n)) continue;
    if (/_stmt$/.test(n)) continue;
    if (!names.includes(n)) names.push(n);
  }
}
console.log(`fetch variables: ${names.join(', ')}`);

let fetch = lines.slice(912, 1022).join('\n');
for (const n of names) {
  const esc = n.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  fetch = fetch.replace(new RegExp(`^(\\s*)\\$${esc} = (\\[\\]|0|array\\(\\));`, 'gm'), (m, pad, val) => `${pad}$ocp_endpoint['${n}'] = ${val};`);
  fetch = fetch.replace(new RegExp(`^(\\s*)\\$${esc} = (\\$[\\w$]+(?:->fetch(?:All)?\\([^;]*?\\)|\\[[^\\]]*\\]));$`, 'gm'), (m, pad, rhs) => `${pad}$ocp_endpoint['${n}'] = ${rhs};`);
  fetch = fetch.replace(new RegExp(`^(\\s*)\\$${esc} \\+= (.*);$`, 'gm'), (m, pad, rhs) => `${pad}$ocp_endpoint['${n}'] += ${rhs};`);
  fetch = fetch.replace(new RegExp(`foreach \\(\\$${esc} as`, 'g'), `foreach ($ocp_endpoint['${n}'] as`);
}
fetch = fetch.replace(/\$swal_data = array\(/, "$ocp_endpoint['swal_data'] = array(");

const left = [...fetch.matchAll(new RegExp(`^\\s*\\$(${names.join('|')}) = `, 'gm'))];
console.log(`unconverted assignments in the fetch: ${left.length}`);
for (const m of left) console.log(`  ${m[0].trim()}`);

const movementDetails = fs.readFileSync(`${ROOT}\\api\\get_movement_details.php`, 'utf8')
  .replace(/\r\n/g, '\n')
  .replace(/^<\?php\r?\n/, '')
  .replace(/^session_start\(\);\r?\n/m, '')
  .replace(/^require_once '\.\.\/config\/db_config\.php';\r?\n/m, '')
  .replace(/\/\*[\s\S]*?\*\/\r?\n/, '')
  .replace(/\?>\s*$/, '');

const endpointOut = `<?php
/**
 * api/inventory-endpoint.php
 *
 * Every read for inventory.php lives in this one file: the items, suppliers, projects,
 * subcons, warehouses and employees for its dropdowns, the inventory listing, the
 * movement history, and the single-movement lookup its JavaScript makes.
 *
 * Before reading it recomputes the inventory table, exactly as the page did, so the
 * listing is consistent with the movements on record.
 *
 * The page pulls this in for its listing: it runs in the page's scope and returns an
 * array of the variables the markup needs, which the page unpacks. The lookup is a
 * separate HTTP request from the page's own script, so it is detected here by its
 * "?id=" parameter and answers JSON instead - it lived in api/get_movement_details.php,
 * which this file replaces.
 *
 * Requires the shared helper include, because the recompute is one of those helpers.
 *
 * Returns
${names.map(n => ` *   ${n}`).join('\n')}
 *   display_name
 */

// ---------------------------------------------------------------------------
// The single-movement lookup the page's JavaScript performs: ?id=<movement id>.
// Lifted from api/get_movement_details.php, which this file replaces.
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

${movementDetails}
    exit();
}

$ocp_endpoint = [
${names.map(n => `    '${n}' => ${/\b(total|balance|count|amount|sum|qty|quantity)\b/i.test(n) ? '0' : '[]'},`).join('\n')}
    // Seeded from the actions file, so a message it set is not wiped out by the
    // endpoint running after it; the read block overwrites it only on a fetch failure.
    'swal_data' => $swal_data ?? [],
    'display_name' => '',
];

// Standalone guard: the read block runs in the page's scope, where the connection is
// already open. Requested on its own there is none, so it answers with the empty result.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

require_once __DIR__ . '/../includes/inventory-functions.php';

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
  fs.writeFileSync(`${ROOT}\\includes\\inventory-functions.php`, helperOut);
  fs.writeFileSync(`${ROOT}\\actions\\inventory-actions.php`, actionsOut);
  fs.writeFileSync(`${ROOT}\\api\\inventory-endpoint.php`, endpointOut);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
