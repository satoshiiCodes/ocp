// Generates spare_parts_categories' actions + endpoint files from the
// items_categories pair, which has the same shape with a different table.
import fs from 'node:fs';
const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');

const pairs = [
  ['actions/items_categories-actions.php', 'actions/spare_parts_categories-actions.php'],
  ['api/items_categories-endpoint.php', 'api/spare_parts_categories-endpoint.php'],
];

for (const [from, to] of pairs) {
  let code = fs.readFileSync(`${ROOT}\\${from}`, 'utf8');
  code = code.replace(/items_categories/g, 'spare_parts_categories')
             .replace(/OCP_ITEMS_CATEGORIES_ACTIONS_RAN/g, 'OCP_SPARE_PARTS_CATEGORIES_ACTIONS_RAN')
             .replace(/items_categories-actions\.php/g, 'spare_parts_categories-actions.php');
  // the file name and page name inside the header comment
  code = code.replace(/for items_categories\.php/g, 'for spare_parts_categories.php');
  console.log(`--- ${to} (${code.length} bytes)`);
  if (APPLY) fs.writeFileSync(`${ROOT}\\${to}`, code);
}
console.log(APPLY ? 'APPLIED' : 'DRY RUN');
