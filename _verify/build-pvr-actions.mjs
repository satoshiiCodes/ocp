// Builds a routing page's shared helper include and its actions file.
//
//   node build-pvr-actions.mjs <page> [--apply]
//
// The page keeps its own header (the signed-in user, $display_name, the PR-id guard and
// $pr_id) because the dispatcher uses $user_id throughout and $user drives the warehouse
// flag. What moves is:
//   38-140    the number generators and updateInventoryTable() -> the helper include,
//             because the handlers call them and the template does too
//   684-2419  the routing dispatcher -> the actions file
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const page = process.argv.slice(2).filter(a => a !== '--apply')[0];
if (!page) { console.error('usage: node build-pvr-actions.mjs <page> [--apply]'); process.exit(2); }
const slug = page.replace(/\.php$/, '');
const CONST = `OCP_${slug.toUpperCase().replace(/-/g, '_')}`;

const lines = fs.readFileSync(`${ROOT}\\${page}`, 'utf8').replace(/\r\n/g, '\n').split('\n');
const find = (re, from = 1) => {
  for (let i = from - 1; i < lines.length; i++) if (re.test(lines[i])) return i + 1;
  return 0;
};
const at = (n) => lines[n - 1] ?? '';
const check = (n, re, what) => {
  if (!re.test(at(n))) { console.error(`ABORT: line ${n} is not ${what}: ${JSON.stringify(at(n))}`); process.exit(1); }
};

const idGuard = find(/^\/\/ Check if PR ID is provided$/);
const helpersStart = find(/^\/\/ Function to /, idGuard);
const fetchStart = find(/^\/\/ Fetch PR details$/);
const actionsComment = find(/^\/\/ Process routing actions$/);
const dispatcherStart = actionsComment + 1;

check(helpersStart, /^\/\/ Function to /, 'the first helper comment');
check(dispatcherStart, /^if \(\$_SERVER\['REQUEST_METHOD'\] === 'POST'\) \{$/, 'the dispatcher');

let depth = 0, dispatcherEnd = 0;
{
  const clean = lines.map(l => l.replace(/'(?:\\.|[^'\\])*'/g, "''").replace(/"(?:\\.|[^"\\])*"/g, '""').replace(/\/\/.*$/, ''));
  for (let i = dispatcherStart - 1; i < clean.length; i++) {
    const before = depth;
    depth += (clean[i].match(/\{/g) || []).length - (clean[i].match(/\}/g) || []).length;
    if (before === 1 && depth === 0) { dispatcherEnd = i + 1; break; }
  }
}
if (!dispatcherEnd) { console.error('ABORT: could not find the end of the dispatcher'); process.exit(1); }

const helperRegion = lines.slice(helpersStart - 1, fetchStart - 1);
const helperNames = [...helperRegion.join('\n').matchAll(/^function (\w+)\(/gm)].map(m => m[1]);
const dispatcher = lines.slice(dispatcherStart - 1, dispatcherEnd).join('\n');

console.log(`${page}:`);
console.log(`  helpers:     ${helpersStart}-${fetchStart - 1} (${helperNames.join(', ')})`);
console.log(`  dispatcher:  ${dispatcherStart}-${dispatcherEnd} (${dispatcher.split('\n').length} lines)`);

const helperOut = `<?php
/**
 * includes/${slug}-functions.php
 *
 * The helpers ${page} and its actions file share: ${helperNames.join('(), ')}().
 *
 * They live in their own file because both entry points need them. The routing handlers
 * call them after they write; the page's template calls the generators while rendering
 * its forms. They must therefore be loaded before whichever runs first, which is why
 * actions/${slug}-actions.php and api/${slug}-endpoint.php both require this file.
 *
 * The bodies below are lifted verbatim from ${page}.
 */

if (defined('${CONST}_FUNCTIONS_LOADED')) {
    return;
}
define('${CONST}_FUNCTIONS_LOADED', true);

${helperRegion.join('\n')}
`;

const actionsOut = `<?php
/**
 * actions/${slug}-actions.php
 *
 * Every action for ${page} lives in this one file: the routing decisions - approving,
 * rejecting, converting a request into a purchase order or a withdrawal slip, and the
 * per-item stock handling that goes with them.
 *
 * The page pulls this file in at the top, so it runs in the page's scope - which is what
 * gives it $pr_id, $user, $user_id, $is_warehouse_user and the fetched request it routes.
 *
 * The helpers it calls live in includes/${slug}-functions.php, required here so they are
 * defined whichever entry point runs first.
 *
 * The block below is lifted verbatim from ${page}: the queries, the routing rules and
 * the messages are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('${CONST}_ACTIONS_RAN')) {
    return;
}
define('${CONST}_ACTIONS_RAN', true);

require_once __DIR__ . '/../includes/${slug}-functions.php';

${dispatcher}
`;

console.log(`  functions include: ${helperOut.length} bytes`);
console.log(`  actions file:      ${actionsOut.length} bytes`);
if (APPLY) {
  fs.writeFileSync(`${ROOT}\\includes\\${slug}-functions.php`, helperOut);
  fs.writeFileSync(`${ROOT}\\actions\\${slug}-actions.php`, actionsOut);
  console.log('  APPLIED');
} else {
  console.log('  DRY RUN');
}
