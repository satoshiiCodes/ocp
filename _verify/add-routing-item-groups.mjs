// Adds ocp_routing_item_groups() to includes/pr_view_routing-functions.php by lifting the
// item fetch and categorisation out of api/pr_view_routing-endpoint.php verbatim.
//
//   node add-routing-item-groups.mjs [--apply]
//
// Why: "Create Purchase Order" writes one po_items row per selected item, looking the item up
// in $items_for_po. That array is built in the endpoint - which the page requires AFTER the
// actions file - so on the POST that submits the form the array did not exist, the loop body
// never ran, and a purchase order was created with no items in it. The function below lets
// both files build the groups, so the handler sees the same categorisation the page rendered.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const endpointFile = `${ROOT}\\api\\pr_view_routing-endpoint.php`;
const functionsFile = `${ROOT}\\includes\\pr_view_routing-functions.php`;

const raw = fs.readFileSync(endpointFile, 'utf8');
const eol = raw.includes('\r\n') ? '\r\n' : '\n';
const lines = raw.replace(/\r\n/g, '\n').split('\n');

// the block to lift: from the "Fetch PR items with stock information" comment to the end of the
// categorisation loop (the line closing the foreach)
const start = lines.findIndex(l => /\/\/ Fetch PR items with stock information/.test(l));
if (start < 0) { console.log('  could not find the item fetch in the endpoint'); process.exit(1); }
const loopOpen = lines.findIndex((l, i) => i > start && /^\s*foreach \(\$items as \$item\) \{\s*$/.test(l));
if (loopOpen < 0) { console.log('  could not find the categorisation loop'); process.exit(1); }
// the loop's closing brace is the next line that is exactly 8 spaces then }
let loopClose = -1;
for (let i = loopOpen + 1; i < lines.length; i++) {
  if (/^        \}\s*$/.test(lines[i])) { loopClose = i; break; }
}
if (loopClose < 0) { console.log('  could not find the end of the categorisation loop'); process.exit(1); }

const body = lines.slice(start, loopClose + 1);
console.log(`  lifting lines ${start + 1}-${loopClose + 1} of the endpoint (${body.length} lines)`);

// Reindent one level deeper (function body) and turn the query's bound parameter into $pr_id.
const lifted = body.map(l => {
  if (l.trim() === '') return '';
  // the block sits at 8 spaces inside the endpoint's if; inside a function it needs 4
  const stripped = l.replace(/^ {8}/, '');
  return '    ' + stripped;
});

const fn = [
  '// Function to fetch a purchase request\'s items and sort them into the groups the routing',
  '// works from: what can be served from stock, what is short, and what must be ordered.',
  '//',
  '// Returns every group. Both api/pr_view_routing-endpoint.php, which renders them, and',
  '// actions/pr_view_routing-actions.php, which turns the chosen ones into po_items and',
  '// withdrawal_slip_items rows, call it - the handler needs the same arrays the page showed,',
  '// and it runs before the endpoint is loaded.',
  'function ocp_routing_item_groups($pdo, $pr_id, $request_type, $document_type) {',
  ...lifted,
  '',
  '    return [',
  '        \'items\' => $items,',
  '        \'items_with_sufficient_stock\' => $items_with_sufficient_stock,',
  '        \'items_with_insufficient_stock\' => $items_with_insufficient_stock,',
  '        \'items_with_no_stock\' => $items_with_no_stock,',
  '        \'items_for_po\' => $items_for_po,',
  '        \'items_for_withdrawal\' => $items_for_withdrawal,',
  '    ];',
  '}',
].join('\n');

const fnSrc = fs.readFileSync(functionsFile, 'utf8');
const fnEol = fnSrc.includes('\r\n') ? '\r\n' : '\n';
let out = fnSrc.replace(/\r\n/g, '\n');
if (out.includes('function ocp_routing_item_groups')) {
  console.log('  the function is already present - nothing to do');
  process.exit(0);
}
out = out.replace(/\n?$/, '\n') + '\n' + fn + '\n';

console.log(`  function is ${fn.split('\n').length} lines`);
if (APPLY) {
  fs.writeFileSync(functionsFile, fnEol === '\r\n' ? out.replace(/\n/g, '\r\n') : out);
  console.log('  APPLIED to includes/pr_view_routing-functions.php');
} else {
  console.log('  DRY RUN');
}
