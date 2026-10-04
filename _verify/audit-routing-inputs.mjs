// Lists every variable the routing actions read but nothing in the actions file assigns,
// and shows where each one is computed in the endpoint the page loads afterwards.
//
//   node audit-routing-inputs.mjs
//
// The routing rules read a lot of page scope: $document_type, $request_type, $po_exists,
// $ws_exists, $threshold_status, $threshold_amount_adjusted and more. They were computed
// above the switch in the original single-file page. The restructure moved those computations
// into api/pr_view_routing-endpoint.php, which the page requires AFTER the actions file - so
// on a POST none of them exist yet, the rules read null, and approvals are refused or allowed
// for the wrong reason. The actions file has to fetch what it needs itself.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const read = (p) => fs.readFileSync(path.join(ROOT, p), 'utf8');
const actions = read('actions/pr_view_routing-actions.php');
const endpoint = read('api/pr_view_routing-endpoint.php');
const page = read('pr_view_routing.php');

// every $var the actions file reads
const read_vars = new Set();
for (const m of actions.matchAll(/\$([a-z_][a-z0-9_]*)\b/g)) read_vars.add(m[1]);
// every $var the actions file assigns somewhere.
//
// `foreach ($a as $b)` assigns $b ONLY. Crediting the collection $a as well is what let the
// po_items bug through this audit: the handler iterated $items_for_po, the array was never
// built anywhere in the file, and the `foreach` was counted as its assignment - so the audit
// said every input was provided while "Create Purchase Order" was writing orders with no
// items. Only the bound variable counts here.
const assigned = new Set();
for (const m of actions.matchAll(/\$([a-z_][a-z0-9_]*)\s*=[^=]/g)) assigned.add(m[1]);
for (const m of actions.matchAll(/\bas\s+\$([a-z_][a-z0-9_]*)/g)) assigned.add(m[1]);

// things that are not page state. `received_data` is a foreach value the actions file binds
// itself (foreach ($_POST['received_items'] as $po_item_id => $received_data)), so it is
// assigned locally and needs nothing from the page.
const ignore = new Set([
  'pdo', 'pr_id', 'user', 'user_id', 'current_stage', 'pr', 'action', 'remarks', 'historyStmt', 'routingStmt',
  'success_message', 'swal_data', 'ocp_', 'e', 'i', 'k', 'v', 'key', 'value', 'row', 'item', 'items', 'stmt',
  'b', 'nbsp', 'this', 'GLOBALS', 'SERVER', 'POST', 'GET', 'SESSION', 'REQUEST', 'FILES', 'received_data',
]);
const isIgnored = (n) => ignore.has(n) || n.startsWith('ocp_') || n.startsWith('__ocp');

const missing = [...read_vars]
  .filter(n => !assigned.has(n) && !isIgnored(n))
  .filter(n => new RegExp(`\\$${n}\\b`).test(actions))
  // only the ones the actions file actually reads on the routing path
  .filter(n => !/^(t|nbsp)$/.test(n));

console.log(missing.length
  ? `${missing.length} variable(s) the actions file reads but never assigns:`
  : 'OK: the actions file fetches every page-scope variable its rules read');

// A second, narrower question: a variable the file only ever ITERATES or only ever sets to
// [] is a collection it expects someone else to have filled. That is exactly the shape the
// po_items bug had, so it is reported even when the plain check above is satisfied.
const neverFilled = [...read_vars].filter(n => {
  if (isIgnored(n)) return false;
  const assignedTo = [...actions.matchAll(new RegExp(`\\$${n}\\s*=\\s*([^;\\n]+)`, 'g'))].map(m => m[1].trim());
  if (!assignedTo.length) return false;                 // covered by the check above
  const onlyEmpty = assignedTo.every(v => /^\[\s*\]$/.test(v));
  return onlyEmpty;
});
const iterated = neverFilled.filter(n => new RegExp(`foreach\\s*\\(\\s*\\$${n}\\b`).test(actions));
for (const n of missing.sort()) {
  const inEndpoint = new RegExp(`\\$${n}\\s*=`).test(endpoint);
  const inPage = new RegExp(`\\$${n}\\s*=`).test(page);
  const where = [inEndpoint && 'endpoint', inPage && 'page'].filter(Boolean).join(' + ') || 'NOWHERE';
  console.log(`    $${n.padEnd(32)} assigned in: ${where}`);
}

// which of them the approve rules specifically depend on
const approveBlock = actions.slice(actions.indexOf("case 'approve_purchasing'"), actions.indexOf("case 'approve_purchasing'") + 9000);
const usedInApprove = missing.filter(n => new RegExp(`\\$${n}\\b`).test(approveBlock));
console.log(`\n  of those, the approve rules read: ${usedInApprove.sort().join(', ') || '(none)'}`);

if (iterated.length) {
  console.log(`\n${iterated.length} collection(s) the actions file iterates but never fills:`);
  for (const n of iterated.sort()) console.log(`    $${n}`);
  console.log('  Each is built somewhere else, so a handler that reads it acts on an empty array.');
  console.log('  Build them in this file too, from the same source the page uses.');
  process.exit(1);
}
process.exit(missing.length ? 1 : 0);
