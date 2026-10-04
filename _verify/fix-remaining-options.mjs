// Moves the remaining script-side dropdown loops into the data islands.
//
//   issue_materials : the material <option> list, built twice by the script
//   purchase_request: the warehouse <option> list, built when a row is added
//
// Both scripts are fetched by the browser as its own request, where $parts and
// $warehouses are not in scope, so those loops emitted nothing.
//
// Matching is whitespace-insensitive because the two copies in issue_materials
// are indented differently.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');

// The PHP statements that make up each block, in order. Whitespace between them
// is ignored; each must still be present exactly as written otherwise.
const partsLines = [
  `<?php foreach ($parts as $part): ?>`,
  `<option value="<?php echo ocp_js_string($part['id']); ?>"`,
  `data-quantity="<?php echo ocp_js_string($part['quantity']); ?>"`,
  `data-next-fifo-price="<?php echo ocp_js_string($part['next_fifo_price']); ?>"`,
  `data-part-number="<?php echo ocp_js_string($part['part_number']); ?>"`,
  `data-part-name="<?php echo ocp_js_string($part['part_name']); ?>"`,
  `data-unit="<?php echo ocp_js_string($part['unit_of_measure']); ?>">`,
  `<?php echo ocp_js_raw($part['part_number'] . ' - ' . $part['part_name'] . ' (' . $part['category_name'] . ')'); ?>`,
  `</option>`,
  `<?php endforeach; ?>`,
];

const warehouseLines = [
  `<?php foreach ($warehouses as $warehouse): ?>`,
  `warehouseOptions += '<option value="<?php echo ocp_js_string($warehouse['id']); ?>"><?php echo ocp_js_string($warehouse['warehouse_name'] . ' - ' . $warehouse['location']); ?></option>';`,
  `<?php endforeach; ?>`,
];

/** A regex for the block, allowing any whitespace (and newlines) between lines. */
function blockRe(lines) {
  const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return new RegExp(lines.map(esc).join('[ \\t]*\\r?\\n[ \\t]*'), 'g');
}

function patch(relPath, edits) {
  const file = `${ROOT}\\${relPath.replace(/\//g, '\\')}`;
  const raw = fs.readFileSync(file, 'utf8');
  const eol = raw.includes('\r\n') ? '\r\n' : '\n';
  let code = raw.replace(/\r\n/g, '\n');

  let ok = true;
  for (const e of edits) {
    const re = blockRe(e.lines);
    const n = [...code.matchAll(re)].length;
    console.log(`  ${relPath}: ${e.name} occurrences ${n} (want ${e.want})`);
    if (n !== e.want) ok = false;
  }
  if (!ok) return false;

  for (const e of edits) {
    code = code.replace(blockRe(e.lines), e.to);
  }
  // no loop on those lists may survive in the script
  const left = [...code.matchAll(/foreach \(\$(parts|warehouses)\b/g)].length;
  console.log(`  -> ${relPath}: patched, ${left} loop(s) left on those lists`);
  if (APPLY) fs.writeFileSync(file, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
  return left === 0;
}

console.log('scripts:');
const a = patch('assets/js/issue_materials.js.php', [{
  name: 'material options',
  lines: partsLines,
  to: `<?php echo ocp_island_get($__ocp_data, "materialOptionsHtml") ?? ""; ?>`,
  want: 2,
}]);
const b = patch('assets/js/purchase_request.js.php', [{
  name: 'warehouse options',
  lines: warehouseLines,
  to: `<?php echo ocp_island_get($__ocp_data, "warehouseOptionsJs") ?? ""; ?>`,
  want: 1,
}]);

console.log(APPLY ? 'APPLIED' : 'DRY RUN');
process.exit(a && b ? 0 : 1);
