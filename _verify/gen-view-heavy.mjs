// Generates view_heavy_equipment's actions + endpoint files from view_vehicle's,
// which have the same shape with a different table, key and wording. All wording
// is taken from the original page so the messages stay identical.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');

const pairs = [
  ['actions/view_vehicle-actions.php', 'actions/view_heavy_equipment-actions.php'],
  ['api/view_vehicle-endpoint.php', 'api/view_heavy_equipment-endpoint.php'],
];

const swaps = [
  // guard constant
  [/OCP_VIEW_VEHICLE_ACTIONS_RAN/g, 'OCP_VIEW_HEAVY_EQUIPMENT_ACTIONS_RAN'],
  // the posted / queried key
  [/\$_POST\['vehicle_id'\]/g, "$_POST['equipment_id']"],
  // the variable carrying it
  [/\$vehicle_id\b/g, '$equipment_id'],
  // the table and column
  [/\bFROM vehicles\b/g, 'FROM equipment'],
  [/\bvehicles WHERE id\b/g, 'equipment WHERE id'],
  [/\bvehicle_id = :vehicle_id\b/g, 'equipment_id = :equipment_id'],
  [/gm\.vehicle_id|spm\.vehicle_id|pr\.vehicle_id/g, (m) => m.replace('vehicle_id', 'equipment_id')],
  // the redirect target
  [/Location: vehicles\.php/g, 'Location: heavy_equipment.php'],
  // the returned variable
  [/'vehicle' =>/g, "'equipment' =>"],
  [/\$ocp_endpoint\['vehicle'\]/g, "$ocp_endpoint['equipment']"],
  [/\$vehicle = \$stmt->fetch/g, '$equipment = $stmt->fetch'],
  [/if \(!\$vehicle\)/g, 'if (!$equipment)'],
  // wording
  [/'Vehicle not found\.'/g, "'Equipment not found.'"],
  [/'Error fetching vehicle details: '/g, "'Error fetching equipment details: '"],
  [/for view_vehicle\.php/g, 'for view_heavy_equipment.php'],
  [/it is opened either by a POST\n \* from the vehicles list or by a link/g, 'it is opened either by a POST\n * from the equipment list or by a link'],
  [/the request goes back to\n \* the list/g, 'the request goes back to\n * the list'],
  [/'actions\/view_vehicle-actions\.php'/g, "'actions/view_heavy_equipment-actions.php'"],
  [/'api\/view_vehicle-endpoint\.php'/g, "'api/view_heavy_equipment-endpoint.php'"],
];

for (const [from, to] of pairs) {
  let code = fs.readFileSync(`${ROOT}\\${from}`, 'utf8');
  for (const [re, val] of swaps) code = code.replace(re, val);

  // nothing about vehicles may survive except the shared column names on the
  // joined tables, which are genuinely called vehicle_id in the schema
  const left = [...code.matchAll(/\bvehicle\b/gi)].map(m => m[0]);
  console.log(`--- ${to}: ${code.length} bytes, ${left.length} "vehicle" left`);
  for (const m of code.matchAll(/.{50}vehicle.{50}/gi)) {
    console.log(`      ...${m[0].replace(/\s+/g, ' ')}`);
  }
  if (APPLY) fs.writeFileSync(`${ROOT}\\${to}`, code);
}
console.log(APPLY ? 'APPLIED' : 'DRY RUN');
