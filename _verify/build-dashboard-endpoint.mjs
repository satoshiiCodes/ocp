// Builds api/dashboard-endpoint.php from dashboard.php's controller.
//
//   node build-dashboard-endpoint.mjs [--apply]
//
// dashboard.php has no actions: it is one large fetch block, gated by the viewer's
// department and position, that fills about fifty variables the markup then reads.
// That whole block is the page's fetching, so it moves here verbatim, and the
// variables it defines are handed back explicitly - the list is derived from the
// code, not typed by hand.
//
// The role flags ($is_motorpool and friends) are computed by the page from the
// signed-in user and read from the page's scope, which the endpoint shares. They are
// listed in the header so that dependency is explicit.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');

const lines = fs.readFileSync(`${ROOT}\\dashboard.php`, 'utf8').replace(/\r\n/g, '\n').split('\n');
const FROM = 44;
const TO = 699;

const at = (n) => lines[n - 1] ?? '';
if (!/^\/\/ Initialize all variables with default values/.test(at(FROM).trim())) {
  console.error(`ABORT: line ${FROM} is not the init comment: ${JSON.stringify(at(FROM))}`);
  process.exit(1);
}
if (at(TO).trim() !== '}') {
  console.error(`ABORT: line ${TO} is not a closing brace: ${JSON.stringify(at(TO))}`);
  process.exit(1);
}
if (!/^\?>/.test(at(TO + 1))) {
  console.error(`ABORT: line ${TO + 1} is not the closing PHP tag: ${JSON.stringify(at(TO + 1))}`);
  process.exit(1);
}

const body = lines.slice(FROM - 1, TO);

// --- every variable the block assigns, in first-assignment order ------------
const SKIP = new Set([
  'pdo', 'stmt', 'user', 'user_id', 'display_name', 'months', 'months_full',
  'GLOBALS', '_SESSION', '_POST', '_GET', '_SERVER', 'e', 'i', 'key', 'value',
  'is_motorpool', 'is_warehouse', 'is_admin_hr_officer', 'is_admin_accounting', 'is_admin_purchaser',
]);
const defined = new Map();
for (const line of body) {
  for (const m of line.matchAll(/\$([a-z_][a-z0-9_]*)\s*(?:\[[^\]]*\])?\s*=[^=]/g)) {
    const name = m[1];
    if (SKIP.has(name)) continue;
    if (!defined.has(name)) defined.set(name, true);
  }
}
const handles = [...defined.keys()].filter(n => /_stmt$/.test(n));
const data = [...defined.keys()].filter(n => !/_stmt$/.test(n));
console.log(`variables assigned: ${defined.size} (${data.length} data, ${handles.length} statement handles)`);

// the markup must not read a statement handle
const pageSrc = lines.slice(TO).join('\n');
const leaked = handles.filter(h => new RegExp(`\\$${h}\\b`).test(pageSrc));
if (leaked.length) {
  console.error(`ABORT: the markup reads statement handle(s): ${leaked.join(', ')}`);
  process.exit(1);
}

// the markup must not read a data variable the block defines but we would omit
const missing = data.filter(n => !new RegExp(`\\$${n}\\b`).test(pageSrc));
console.log(`data variables the markup never reads (still returned): ${missing.length ? missing.join(', ') : 'none'}`);

const out = `<?php
/**
 * api/dashboard-endpoint.php
 *
 * Every read for dashboard.php lives in this one file. The dashboard is a set of
 * summary panels, and which ones it can fill depends on the viewer: the fetch block
 * below is gated by department and position, exactly as it was when it sat in the
 * page.
 *
 * The page pulls this in instead of querying the database itself, so all of the
 * page's fetching is in one place. It runs in the page's scope and returns an array
 * of the variables the markup needs; the page unpacks that array. Nothing is printed
 * here, so this file cannot disturb the page's output.
 *
 * Reads from the page's scope:
 *   $pdo                 the connection, opened by the page
 *   $user                the signed-in user, with department and position
 *   $is_motorpool, $is_warehouse, $is_admin_hr_officer, $is_admin_accounting,
 *   $is_admin_purchaser  the role gates, computed by the page from $user
 *
 * The block is lifted verbatim from dashboard.php: the queries, the thresholds and
 * the role checks are unchanged. It is the page's whole controller, which is why the
 * returned list is long - each entry is a panel's data.
 *
 * Returns
${data.map(n => ` *   ${n}`).join('\n')}
 */

${body.join('\n')}

// Hand back every variable the block defined. The list is the block's own variable
// set, so nothing it produced is left behind. Two of them ($late_threshold and
// $current_year) are assigned inside role blocks only, so each entry is read with
// ?? null: a viewer whose branch did not run gets null rather than a notice.
return [
${data.map(n => `    '${n}' => $${n} ?? null,`).join('\n')}
];
`;

console.log(`api/dashboard-endpoint.php: ${out.length} bytes`);
if (APPLY) {
  fs.writeFileSync(`${ROOT}\\api\\dashboard-endpoint.php`, out);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
