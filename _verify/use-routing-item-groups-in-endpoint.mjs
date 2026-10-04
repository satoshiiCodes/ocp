// Replaces the endpoint's inline item fetch and categorisation with a call to the shared
// ocp_routing_item_groups(), so the actions file can build the same groups.
//
//   node use-routing-item-groups-in-endpoint.mjs [--apply]
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const file = `${ROOT}\\api\\pr_view_routing-endpoint.php`;

const raw = fs.readFileSync(file, 'utf8');
const eol = raw.includes('\r\n') ? '\r\n' : '\n';
const lines = raw.replace(/\r\n/g, '\n').split('\n');

const start = lines.findIndex(l => /\/\/ Fetch PR items with stock information/.test(l));
if (start < 0) { console.log('  could not find the item fetch'); process.exit(1); }
const loopOpen = lines.findIndex((l, i) => i > start && /^\s*foreach \(\$items as \$item\) \{\s*$/.test(l));
let loopClose = -1;
for (let i = loopOpen + 1; i < lines.length; i++) {
  if (/^        \}\s*$/.test(lines[i])) { loopClose = i; break; }
}
if (start < 0 || loopClose < 0) { console.log('  could not find the block bounds'); process.exit(1); }

const replacement = [
  '        // Fetch the request\'s items and sort them into the groups the markup and the',
  '        // handlers work from. The body of this used to sit here; it moved to',
  '        // includes/pr_view_routing-functions.php as ocp_routing_item_groups() because the',
  '        // actions file needs the same groups and runs before this file is loaded - which is',
  '        // why "Create Purchase Order" produced a purchase order with no po_items rows: it',
  '        // looked each chosen item up in $items_for_po, which did not exist yet.',
  '        $ocp_item_groups = ocp_routing_item_groups($pdo, $pr_id, $request_type, $document_type);',
  '        $items = $ocp_item_groups[\'items\'];',
  '        $items_with_sufficient_stock = $ocp_item_groups[\'items_with_sufficient_stock\'];',
  '        $items_with_insufficient_stock = $ocp_item_groups[\'items_with_insufficient_stock\'];',
  '        $items_with_no_stock = $ocp_item_groups[\'items_with_no_stock\'];',
  '        $items_for_po = $ocp_item_groups[\'items_for_po\'];',
  '        $items_for_withdrawal = $ocp_item_groups[\'items_for_withdrawal\'];',
  '        unset($ocp_item_groups);',
];

console.log(`  replacing endpoint lines ${start + 1}-${loopClose + 1} (${loopClose - start + 1}) with ${replacement.length}`);
const out = [...lines.slice(0, start), ...replacement, ...lines.slice(loopClose + 1)].join('\n');
if (APPLY) {
  fs.writeFileSync(file, eol === '\r\n' ? out.replace(/\n/g, '\r\n') : out);
  console.log('  APPLIED');
} else {
  console.log('  DRY RUN');
}
