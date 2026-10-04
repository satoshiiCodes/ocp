// Builds pr_view_routing's three files.
//
//   node build-pvr.mjs <page> [--apply]
//
// Both routing pages share this shape:
//   13-36    the signed-in user, the PR-id guard and $pr_id
//   38-140   the number generators and updateInventoryTable() - needed by the handlers
//            AND by the template
//   141-679  the PR-detail read
//   681-682  the warehouse flag
//   684-2419 the routing dispatcher
//   2421-2504 the display helpers - used only by the template, so they stay
//   2516+    the template
// The shared helpers go into an include, the dispatcher into the actions file, and
// the read plus the two flags into the endpoint.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const args = process.argv.slice(2).filter(a => a !== '--apply');
const page = args[0];
if (!page) { console.error('usage: node build-pvr.mjs <page> [--apply]'); process.exit(2); }
const slug = page.replace(/\.php$/, '');

const lines = fs.readFileSync(`${ROOT}\\${page}`, 'utf8').replace(/\r\n/g, '\n').split('\n');
const at = (n) => lines[n - 1] ?? '';
const check = (n, re, what) => {
  if (!re.test(at(n))) {
    console.error(`ABORT: line ${n} is not ${what}: ${JSON.stringify(at(n))}`);
    process.exit(1);
  }
};

// --- locate the regions by their markers --------------------------------------
const find = (re, from = 1) => {
  for (let i = from - 1; i < lines.length; i++) if (re.test(lines[i])) return i + 1;
  return 0;
};

const idGuard = find(/^\/\/ Check if PR ID is provided$/);
const helpersStart = find(/^\/\/ Function to /, idGuard);
const fetchStart = find(/^\/\/ Fetch PR details$/);
const fetchTry = fetchStart + 1;
const warehouseFlag = find(/^\/\/ Check if user is in Warehouse department$/);
const actionsComment = find(/^\/\/ Process routing actions$/);
const dispatcherStart = actionsComment + 1;
const templatePhp = find(/^\?>$/, dispatcherStart + 1);

for (const [n, re, what] of [
  [idGuard, /^\/\/ Check if PR ID is provided$/, 'the PR-id comment'],
  [helpersStart, /^\/\/ Function to /, 'the first helper comment'],
  [fetchStart, /^\/\/ Fetch PR details$/, 'the fetch comment'],
  [fetchTry, /^try \{$/, 'the fetch try'],
  [warehouseFlag, /^\/\/ Check if user is in Warehouse department$/, 'the warehouse comment'],
  [actionsComment, /^\/\/ Process routing actions$/, 'the actions comment'],
  [dispatcherStart, /^if \(\$_SERVER\['REQUEST_METHOD'\] === 'POST'\) \{$/, 'the dispatcher'],
  [templatePhp, /^\?>$/, 'the closing PHP tag'],
]) {
  check(n, re, what);
}

// the dispatcher ends at its matching brace
let depth = 0, dispatcherEnd = 0;
{
  const clean = lines.map(l => l
    .replace(/'(?:\\.|[^'\\])*'/g, "''").replace(/"(?:\\.|[^"\\])*"/g, '""').replace(/\/\/.*$/, ''));
  for (let i = dispatcherStart - 1; i < clean.length; i++) {
    const before = depth;
    depth += (clean[i].match(/\{/g) || []).length - (clean[i].match(/\}/g) || []).length;
    if (before === 1 && depth === 0) { dispatcherEnd = i + 1; break; }
  }
}
if (!dispatcherEnd) { console.error('ABORT: could not find the end of the dispatcher'); process.exit(1); }
// the dispatcher ends at its matching brace
const helperRegion = lines.slice(helpersStart - 1, fetchStart - 1);
const helperNames = [...helperRegion.join('\n').matchAll(/^function (\w+)\(/gm)].map(m => m[1]);

console.log(`${page}:`);
console.log(`  id guard:        ${idGuard}`);
console.log(`  helpers:         ${helpersStart}-${fetchStart - 1}  (${helperNames.join(', ')})`);
console.log(`  fetch:           ${fetchStart}-${warehouseFlag - 1}`);
console.log(`  warehouse flag:  ${warehouseFlag}`);
console.log(`  dispatcher:      ${dispatcherStart}-${dispatcherEnd}`);
console.log(`  template:        ${templatePhp}`);

// --- 1. the helpers the handlers and the page both need ----------------------
// The page's own header - the signed-in user, $display_name, the PR-id guard and
// $pr_id - stays in the page: $user_id is used by the dispatcher some forty times and
// $user drives the warehouse flag, so the page must keep computing them before the
// actions file runs.

const helperOut = `<?php
/**
 * includes/${slug}-functions.php
 *
 * The helpers ${page} and its actions file share: the two document-number generators
 * and updateInventoryTable(), which recomputes one item's stock in one warehouse from
 * its movements.
 *
 * They live in their own file because both entry points need them. The routing
 * handlers call all three after they write; the page's template calls the generators
 * while rendering its forms. They must therefore be loaded before whichever runs
 * first, which is why actions/${slug}-actions.php and api/${slug}-endpoint.php both
 * require this file.
 *
 * The bodies below are lifted verbatim from ${page}.
 */

if (defined('OCP_${slug.toUpperCase().replace(/-/g, '_')}_FUNCTIONS_LOADED')) {
    return;
}
define('OCP_${slug.toUpperCase().replace(/-/g, '_')}_FUNCTIONS_LOADED', true);

${helperRegion.join('\n')}
`;

// --- 2. the actions ----------------------------------------------------------
const dispatcher = lines.slice(dispatcherStart - 1, dispatcherEnd).join('\n');

const actionsOut = `<?php
/**
 * actions/${slug}-actions.php
 *
 * Every action for ${page} lives in this one file: the routing decisions - approving,
 * rejecting, converting a request into a purchase order or a withdrawal slip, and the
 * per-item stock handling that goes with them.
 *
 * The page pulls this file in at the top, so it runs in the page's scope - which is
 * what gives it $pr_id, $user, $is_warehouse_user and the fetched request it routes.
 *
 * The helpers it calls live in includes/${slug}-functions.php, required here so they
 * are defined whichever entry point runs first.
 *
 * The block below is lifted verbatim from ${page}: the queries, the routing rules and
 * the messages are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_${slug.toUpperCase().replace(/-/g, '_')}_ACTIONS_RAN')) {
    return;
}
define('OCP_${slug.toUpperCase().replace(/-/g, '_')}_ACTIONS_RAN', true);

require_once __DIR__ . '/../includes/${slug}-functions.php';

${dispatcher}
`;

// --- 3. the endpoint ---------------------------------------------------------
const names = [];
{
  const body = lines.slice(fetchStart - 1, warehouseFlag - 1).join('\n');
  for (const m of body.matchAll(/^\s*\$([a-z_][a-z0-9_]*)\s*(?:\[[^\]]*\])?\s*=[^=]/gm)) {
    const n = m[1];
    if (['pdo', 'stmt', 'i', 'key', 'value', 'row', 'item', 'e'].includes(n)) continue;
    if (/_stmt$/.test(n)) continue;
    if (!names.includes(n)) names.push(n);
  }
}
console.log(`  fetch variables: ${names.join(', ')}`);

let fetch = lines.slice(fetchStart - 1, warehouseFlag - 1).join('\n');
for (const n of names) {
  const esc = n.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  fetch = fetch.replace(new RegExp(`^(\\s*)\\$${esc} = (\\[\\]|0|array\\(\\)|null);`, 'gm'), (m, pad, val) => `${pad}$ocp_endpoint['${n}'] = ${val};`);
  fetch = fetch.replace(new RegExp(`^(\\s*)\\$${esc} = (\\$[\\w$]+(?:->fetch(?:All)?\\([^;]*?\\)|\\[[^\\]]*\\]));$`, 'gm'), (m, pad, rhs) => `${pad}$ocp_endpoint['${n}'] = ${rhs};`);
  fetch = fetch.replace(new RegExp(`^(\\s*)\\$${esc} \\+= (.*);$`, 'gm'), (m, pad, rhs) => `${pad}$ocp_endpoint['${n}'] += ${rhs};`);
  fetch = fetch.replace(new RegExp(`foreach \\(\\$${esc} as`, 'g'), `foreach ($ocp_endpoint['${n}'] as`);
}
const left = [...fetch.matchAll(new RegExp(`^\\s*\\$(${names.join('|')}) = `, 'gm'))];
console.log(`  unconverted in the fetch: ${left.length}`);
for (const m of left) console.log(`    ${m[0].trim()}`);

const endpointOut = `<?php
/**
 * api/${slug}-endpoint.php
 *
 * Every read for ${page} lives in this one file: the request being routed, with its
 * items, approvals and related records, plus the flag telling the page and its handlers
 * whether the viewer is a Warehouse admin.
 *
 * The page pulls this in instead of querying the database itself, so all of the page's
 * fetching is in one place. It runs in the page's scope and returns an array of the
 * variables the markup and the handlers need; the page unpacks that array.
 *
 * One read is a redirect: when the request cannot be fetched, this sets the session
 * message and sends the browser back to the request list, exactly as the page did
 * before the read moved here.
 *
 * Requires the shared helper include, because the template calls the number generators.
 * The page's own header - the signed-in user, the PR-id guard and $pr_id - stays in the
 * page and is already set by the time this runs.
 *
 * Returns
${names.map(n => ` *   ${n}`).join('\n')}
 *   is_warehouse_user
 */

$ocp_endpoint = [
${names.map(n => `    '${n}' => ${/\b(total|count|amount|sum|quantity|qty)\b/i.test(n) ? '0' : '[]'},`).join('\n')}
    'is_warehouse_user' => false,
];

// Standalone guard: the read runs in the page's scope, where the connection is already
// open. Requested on its own there is none, so it answers with the empty result.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

require_once __DIR__ . '/../includes/${slug}-functions.php';

${fetch}

$is_warehouse_user = ($user['department'] === 'Warehouse' && $user['accounttype'] === 'Admin');
$ocp_endpoint['is_warehouse_user'] = $is_warehouse_user;

return $ocp_endpoint;
`;

console.log(`  functions include: ${helperOut.length} bytes`);
console.log(`  actions file:      ${actionsOut.length} bytes`);
console.log(`  endpoint file:     ${endpointOut.length} bytes`);

if (APPLY) {
  fs.writeFileSync(`${ROOT}\\includes\\${slug}-functions.php`, helperOut);
  fs.writeFileSync(`${ROOT}\\actions\\${slug}-actions.php`, actionsOut);
  fs.writeFileSync(`${ROOT}\\api\\${slug}-endpoint.php`, endpointOut);
  console.log('  APPLIED');
} else {
  console.log('  DRY RUN');
}
