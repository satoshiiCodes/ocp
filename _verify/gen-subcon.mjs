// Generates subcon_name's actions + endpoint files from the suppliers pair.
// Same shape, different table, column and wording - all taken from the original
// subcon_name.php so the messages stay identical.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');

const subs = [
  // longest / most specific first
  ['actions/items_categories-actions.php', null], // guard: never used
];

const replacements = [
  // table and column
  [/\bsuppliers\b/g, 'subcons'],
  [/\bsupplier_name\b/g, 'subcon_name'],
  // variables
  [/\$add_supplier_name\b/g, '$add_subcon_name'],
  [/\$edit_supplier_name\b/g, '$edit_subcon_name'],
  [/\$suppliersStmt\b/g, '$subconsStmt'],
  [/\$suppliers\b/g, '$subcons'],
  // wording, exactly as subcon_name.php had it
  ["'Supplier deleted successfully!'", "'Subcontractor deleted successfully!'"],
  ["'Error deleting supplier. Please try again.'", "'Error deleting subcontractor. Please try again.'"],
  ["'Supplier name is required.'", "'Subcontractor name is required.'"],
  ["'Supplier name already exists. Please use a different name.'", "'Subcontractor name already exists. Please use a different name.'"],
  ["'Supplier updated successfully!'", "'Subcontractor updated successfully!'"],
  ["'Error updating supplier. Please try again.'", "'Error updating subcontractor. Please try again.'"],
  ["'Supplier already exists. Please use a different name.'", "'Subcontractor already exists. Please use a different name.'"],
  ["'Supplier added successfully!'", "'Subcontractor added successfully!'"],
  ["'Error adding supplier. Please try again.'", "'Error adding subcontractor. Please try again.'"],
  ["'Error fetching suppliers: '", "'Error fetching subcontractors: '"],
  // guards / headers
  [/OCP_SUPPLIERS_ACTIONS_RAN/g, 'OCP_SUBCON_NAME_ACTIONS_RAN'],
  [/actions\/suppliers-actions\.php/g, 'actions/subcon_name-actions.php'],
  [/api\/suppliers-endpoint\.php/g, 'api/subcon_name-endpoint.php'],
  [/for suppliers\.php/g, 'for subcon_name.php'],
];

const pairs = [
  ['actions/suppliers-actions.php', 'actions/subcon_name-actions.php'],
  ['api/suppliers-endpoint.php', 'api/subcon_name-endpoint.php'],
];

for (const [from, to] of pairs) {
  let code = fs.readFileSync(`${ROOT}\\${from}`, 'utf8');
  for (const [re, val] of replacements) code = code.replace(re, val);

  // Anything left over is a capitalised "Supplier" inside a message. Replace it
  // case-preservingly, plurals first so "Suppliers" does not become
  // "Subcontractors" via the singular rule.
  code = code.replace(/Suppliers/g, 'Subcontractors').replace(/Supplier/g, 'Subcontractor');
  code = code.replace(/\bsuppliers\b/g, 'subcontractors').replace(/\bsupplier\b/g, 'subcontractor');

  // no trace of the supplier wording may survive
  const left = code.match(/supplier/gi);
  console.log(`--- ${to}: ${code.length} bytes${left ? `  WARNING ${left.length} "supplier" left: ${left.join(', ')}` : '  clean'}`);
  if (APPLY) fs.writeFileSync(`${ROOT}\\${to}`, code);
}
void subs;
console.log(APPLY ? 'APPLIED' : 'DRY RUN');
