// Moves the item-row dropdown options of gasoline_purchase_order out of the script
// and into the data island.
//
// The script is fetched by the browser as its own request, where $gasolineTypes,
// $suppliers, $vehicles, $equipment and $employees are not in scope, so those
// foreach loops emitted nothing. The page now renders the options and the script
// reads them from the island.
import fs from 'node:fs';

const APPLY = process.argv.includes('--apply');
const file = 'E:\\laragon\\www\\OCP\\assets\\js\\gasoline_purchase_order.js.php';
const raw = fs.readFileSync(file, 'utf8');
// the files use CRLF; match on LF and put CRLF back when writing
const eol = raw.includes('\r\n') ? '\r\n' : '\n';
let code = raw.replace(/\r\n/g, '\n');

const swaps = [
  {
    name: 'gasoline types',
    from: `<?php foreach ($gasolineTypes as $type): ?>
                                        <option value="<?php echo ocp_js_string($type); ?>"><?php echo ocp_js_raw($type); ?></option>
                                        <?php endforeach; ?>`,
    to: `<?php echo ocp_island_get($__ocp_data, "gasolineTypeOptionsHtml") ?? ""; ?>`,
  },
  {
    name: 'suppliers',
    from: `<?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?php echo ocp_js_string($supplier['id']); ?>">
                                            <?php echo ocp_js_raw($supplier['supplier_name']); ?>
                                        </option>
                                        <?php endforeach; ?>`,
    to: `<?php echo ocp_island_get($__ocp_data, "supplierOptionsHtml") ?? ""; ?>`,
  },
  {
    name: 'vehicles',
    from: `<?php foreach ($vehicles as $vehicle): ?>
                                        <option value="<?php echo ocp_js_string($vehicle['id']); ?>">
                                            <?php echo ocp_js_raw($vehicle['vehicle_name'] . ' (' . $vehicle['plate_number'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>`,
    to: `<?php echo ocp_island_get($__ocp_data, "vehicleOptionsHtml") ?? ""; ?>`,
  },
  {
    name: 'equipment',
    from: `<?php foreach ($equipment as $eq): ?>
                                        <option value="<?php echo ocp_js_string($eq['id']); ?>">
                                            <?php echo ocp_js_raw($eq['equipment_name']); ?>
                                        </option>
                                        <?php endforeach; ?>`,
    to: `<?php echo ocp_island_get($__ocp_data, "equipmentOptionsHtml") ?? ""; ?>`,
  },
  {
    name: 'employees',
    from: `<?php foreach ($employees as $employee): ?><?php $employee_name = formatEmployeeNameWithPosition($employee); ?>
                                        <option value="<?php echo ocp_js_string($employee['id']); ?>">
                                            <?php echo ocp_js_raw($employee_name); ?>
                                        </option>
                                        <?php endforeach; ?>`,
    to: `<?php echo ocp_island_get($__ocp_data, "employeeOptionsHtml") ?? ""; ?>`,
  },
];

let ok = true;
for (const s of swaps) {
  const n = code.split(s.from).length - 1;
  console.log(`${s.name.padEnd(16)} occurrences: ${n}`);
  if (n !== 1) ok = false;
}
if (!ok) {
  console.error('ABORT: each block must appear exactly once');
  process.exit(1);
}
for (const s of swaps) code = code.replace(s.from, s.to);

const left = [...code.matchAll(/foreach \(\$(gasolineTypes|suppliers|vehicles|equipment|employees)\b/g)].length;
console.log(`foreach loops left on those lists: ${left}`);
if (APPLY) {
  fs.writeFileSync(file, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
  console.log(`APPLIED (${eol === '\r\n' ? 'CRLF' : 'LF'} preserved)`);
} else {
  console.log('DRY RUN');
}
