// Builds an endpoint from a read block that assigns and REASSIGNS its variables across
// branches, by running the block unchanged inside a closure and handing back everything
// it defined.
//
//   node wrap-fetch-block.mjs <page> <name,name,...> [--apply]
//
// The per-assignment rewriting used elsewhere does not fit these routing pages: their
// read block sets $flow_type four times, $show_po_button five, and so on, as it works
// out what the request can become. Rewriting each assignment would be a rewrite of the
// logic rather than a move. Wrapping keeps the block byte-for-byte identical and lets
// the closure's own variable table supply the result.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const args = process.argv.slice(2).filter(a => a !== '--apply');
const [page, needsList] = args;
if (!page) { console.error('usage: node wrap-fetch-block.mjs <page> <needs> [--apply]'); process.exit(2); }
const slug = page.replace(/\.php$/, '');
const needs = (needsList || '').split(',').map(s => s.trim()).filter(Boolean);

const lines = fs.readFileSync(`${ROOT}\\${page}`, 'utf8').replace(/\r\n/g, '\n').split('\n');
const find = (re, from = 1) => {
  for (let i = from - 1; i < lines.length; i++) if (re.test(lines[i])) return i + 1;
  return 0;
};

const fetchStart = find(/^\/\/ Fetch PR details/) || find(/^\/\/ Fetch /);
const fetchTry = fetchStart + 1;
// The block runs to the actions comment, so the flags computed just before the
// dispatcher - which decide which routing buttons the page offers - travel with the
// read that feeds them.
const warehouseFlag = find(/^\/\/ Process routing actions$/);
if (!fetchStart || !warehouseFlag) { console.error('ABORT: could not locate the read block'); process.exit(1); }
if (!/^try \{$/.test(lines[fetchTry - 1])) { console.error(`ABORT: line ${fetchTry} is not the try: ${JSON.stringify(lines[fetchTry - 1])}`); process.exit(1); }

// the block runs from the fetch comment to just before the actions comment
const block = lines.slice(fetchStart - 1, warehouseFlag - 1).join('\n');
// what the block reads from the page scope
const readNames = new Set();
for (const m of block.matchAll(/\$([a-z_][a-z0-9_]*)\b/g)) readNames.add(m[1]);
// what the block assigns
const assigned = [];
for (const m of block.matchAll(/^\s*\$([a-z_][a-z0-9_]*)\s*(?:\[[^\]]*\])?\s*=[^=]/gm)) {
  if (!assigned.includes(m[1])) assigned.push(m[1]);
}
const AMBIENT = new Set(['pdo', 'i', 'key', 'value', 'row', '_SESSION', '_POST', '_GET', '_SERVER', 'GLOBALS']);
const produced = assigned.filter(n => !AMBIENT.has(n) && !/_stmt$/.test(n));
// Scratch names such as $item and $e are loop and catch variables the closure makes for
// itself, so they are never carried in. The page's own values - the id, the user, the
// flags - are, but only when the block actually reads them.
const SCRATCH = new Set(['item', 'e', 'stmt', 'k', 'v', 'arr', 'data', 'result']);
const carried = [...readNames].filter(n =>
  !produced.includes(n) && !SCRATCH.has(n) && !/_stmt$/.test(n) &&
  (AMBIENT.has(n) || needs.includes(n) || ['pr_id', 'user', 'user_id', 'is_warehouse_user'].includes(n)));

console.log(`${page}:`);
console.log(`  read block:  lines ${fetchStart}-${warehouseFlag - 1} (${block.split('\n').length} lines)`);
console.log(`  produces:    ${produced.length} variables`);
console.log(`  reads:       ${carried.join(', ')}`);

const endpointOut = `<?php
/**
 * api/${slug}-endpoint.php
 *
 * Every read for ${page} lives in this one file: the request being routed with its
 * items, the stock position of each one, what documents already exist for it, and the
 * flags that decide which of the routing buttons the page offers.
 *
 * The page pulls this in instead of querying the database itself, so all of the page's
 * fetching is in one place. It runs in the page's scope and returns an array of the
 * variables the markup and the handlers need; the page unpacks that array.
 *
 * The read block below is lifted verbatim from ${page}. It is wrapped in a function so
 * that the block - which assigns and reassigns its working variables as it works out
 * what the request can become - keeps its own variables and hands its final state back
 * in one place, rather than each assignment being rewritten to address the return
 * array. One read is a redirect: when the request cannot be fetched, this sets the
 * session message and sends the browser back to the request list, exactly as before.
 *
 * Reads from the page's scope: ${carried.join(', ')}
 *
 * Returns
${produced.map(n => ` *   ${n}`).join('\n')}
 *   is_warehouse_user
 */

$ocp_endpoint = [
${produced.map(n => `    '${n}' => ${/^(total_amount|threshold_amount)$/.test(n) ? '0' : (/^(pr|po|ws|latest_po|latest_ws|current_stage|po_status|ws_status|threshold_status|flow_type|request_type|document_type)$/.test(n) ? 'null' : '[]')},`).join('\n')}
    'is_warehouse_user' => false,
];

// Standalone guard: the read runs in the page's scope, where the connection is already
// open. Requested on its own there is none, so it answers with the empty result.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

require_once __DIR__ . '/../includes/${slug}-functions.php';

$ocp_read = function (${carried.map(n => `$${n}`).join(', ')}) {
${block.split('\n').map(l => l ? '    ' + l : l).join('\n')}

    // Hand back the block's own variables, which is its final state. The names carried
    // in are dropped so the caller's own values are not overwritten by copies - and the
    // scratch names, including this array itself, with them.
    $ocp_out = get_defined_vars();
    foreach (['${carried.join("', '")}'${carried.length ? ", " : ""}'ocp_out', 'ocp_read', 'ocp_key', 'ocp_value'] as $ocp_drop) {
        unset($ocp_out[$ocp_drop]);
    }
    unset($ocp_out['ocp_drop']);
    return $ocp_out;
};

foreach ($ocp_read(${carried.map(n => `$${n}`).join(', ')}) as $ocp_key => $ocp_value) {
    $ocp_endpoint[$ocp_key] = $ocp_value;
}
unset($ocp_read, $ocp_key, $ocp_value);

$is_warehouse_user = ($user['department'] === 'Warehouse' && $user['accounttype'] === 'Admin');
$ocp_endpoint['is_warehouse_user'] = $is_warehouse_user;

return $ocp_endpoint;
`;

console.log(`  endpoint:    ${endpointOut.length} bytes`);
if (APPLY) {
  fs.writeFileSync(`${ROOT}\\api\\${slug}-endpoint.php`, endpointOut);
  console.log('  APPLIED');
} else {
  console.log('  DRY RUN');
}
