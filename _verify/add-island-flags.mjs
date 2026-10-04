// Adds the flags the rewritten scripts read to each page's data island.
//
//   node add-island-flags.mjs [--apply]
//
// fix-script-island-patterns.mjs turns `<?php if (!empty($x)): ?>` into a client-side
// `if (PAGE_DATA.hasMessage)`. That flag has to exist in the island, or the test is
// simply undefined and the block disappears anyway - the same bug wearing a new coat.
//
// Each flag is a boolean computed from the page's OWN variables, right before the island
// is rendered. They are derived from those variables rather than from $_SESSION: the page
// has usually consumed and unset the session's copy by that point, which is exactly how
// a first attempt at item_names.php got reopenAddModal wrong.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');

// page slug -> [flagName, phpExpression]
const FLAGS = {
  cash_on_hand: [['hasMessage', "!empty($swal_message)"]],
  expenses: [['hasMessage', "!empty($swal_message)"]],
  expenses_type: [
    ['hasMessage', "!empty($swal_message)"],
    ['reopenAddModal', "$_SERVER['REQUEST_METHOD'] === 'POST' && $swal_message_type === 'error'"],
    ['openViewModal', "(bool) $view_expense"],
    ['isError', "$swal_message_type === 'error'"],
  ],
  dashboard: [
    ['isMotorpool', "(bool) $is_motorpool"],
    ['isWarehouse', "(bool) $is_warehouse"],
    ['isHrOfficer', "(bool) $is_admin_hr_officer"],
    ['isAccounting', "(bool) $is_admin_accounting"],
    ['isPurchaser', "(bool) $is_admin_purchaser"],
  ],
  employee_registration: [
    ['hasMessage', "!empty($swal_data)"],
    ['isError', "($swal_data['icon'] ?? '') === 'error'"],
    ['openEditDeductions', "!empty($edit_deductions_data)"],
    ['deductionKind', "!empty($edit_deductions_data['cash_advance']) ? 'cash_advance' : (!empty($edit_deductions_data['sss']) ? 'sss' : (!empty($edit_deductions_data['pag_ibig']) ? 'pag_ibig' : (!empty($edit_deductions_data['philhealth']) ? 'philhealth' : '')))"],
    ['postedForm', "isset($_POST['edit_deductions']) ? 'edit_deductions' : (isset($_POST['edit_details']) ? 'edit_details' : (isset($_POST['edit_wage']) ? 'edit_wage' : ''))"],
    ['postedIsUpdate', "isset($_POST['update_employee_details']) || isset($_POST['update_employee_wage']) || isset($_POST['update_employee_deductions']) ? 1 : ''"],
  ],
  gasoline_inventory: [
    ['hasMessage', "!empty($swal_data)"],
    ['isError', "($swal_data['icon'] ?? '') === 'error'"],
    ['postedAction', "$_POST['action'] ?? ''"],
  ],
  gasoline_purchase_order: [
    ['hasMessage', "!empty($swal_data)"],
    ['openEditPo', "(bool) $edit_po_data"],
  ],
  gasoline_suppliers: [
    ['hasMessage', "!empty($swal_message)"],
    ['reopenAddModal', "$_SERVER['REQUEST_METHOD'] === 'POST' && ($swal_type ?? '') === 'error' && !isset($_POST['update_supplier']) && !isset($_POST['delete_supplier'])"],
  ],
  gasoline_tank: [
    ['hasMessage', "!empty($swal_data)"],
    ['isError', "($swal_data['icon'] ?? '') === 'error'"],
    ['formIsAdd', "($form_type ?? '') === 'add'"],
    ['formIsEdit', "($form_type ?? '') === 'edit' && !empty($form_data['tank_id'])"],
    ['formDataTankId', "$form_data['tank_id'] ?? ''"],
  ],
  heavy_equipment: [['hasAlert', "!empty($alert)"]],
  inventory: [
    ['hasMessage', "!empty($swal_data)"],
    ['isError', "($swal_data['icon'] ?? '') === 'error'"],
    ['postedAction', "$_POST['action'] ?? ''"],
    ['postedEditMovement', "isset($_POST['edit_movement']) ? 1 : ''"],
  ],
  issue_materials: [
    ['hasMessage', "!empty($swal_data)"],
    ['isSuccess', "($swal_data['icon'] ?? '') === 'success'"],
    ['isError', "($swal_data['icon'] ?? '') === 'error'"],
  ],
  items_categories: [['hasMessage', "!empty($sweetalert)"]],
  item_names: [
    ['hasMessage', "!empty($swal_message)"],
    ['reopenAddModal', "$_SERVER['REQUEST_METHOD'] === 'POST' && $swal_message_type === 'error'"],
    ['openViewModal', "(bool) $view_item"],
  ],
  payroll: [
    ['hasMessage', "!empty($message)"],
    ['hasAttendanceError', "isset($attendance_error)"],
  ],
  pr_spare_view_routing: [
    ['hasMessage', "!empty($swal_data)"],
    ['isIssueMaterials', "!empty($is_issue_materials)"],
    ['isMaterialRequest', "$is_issue_materials ? 'Materials' : 'Spare Parts'"],
  ],
  pr_view_routing: [['hasMessage', "!empty($swal_data)"]],
  purchase_request: [
    ['hasMessage', "!empty($swal_data)"],
    ['isError', "($swal_data['icon'] ?? '') === 'error'"],
  ],
  spare_parts: [
    ['hasMessage', "isset($_SESSION['alert'])"],
    ['isSuccess', "(isset($_SESSION['alert']) && ($_SESSION['alert']['type'] ?? '') === 'success')"],
  ],
  spare_parts_categories: [['hasMessage', "!empty($sweetalert)"]],
  spare_parts_inventory: [
    ['hasMessage', "!empty($swal_data)"],
    ['isError', "($swal_data['icon'] ?? '') === 'error'"],
    ['postedAction', "$_POST['action'] ?? ''"],
  ],
  spare_parts_suppliers: [
    ['hasMessage', "!empty($swal_data)"],
    ['isError', "($swal_data['icon'] ?? '') === 'error'"],
    ['isSuccess', "($swal_data['icon'] ?? '') === 'success'"],
    ['postedEditId', "$_POST['edit_id'] ?? ''"],
    ['postedDeleteId', "$_POST['delete_id'] ?? ''"],
    ['isSuccessOnPost', "($swal_data['icon'] ?? '') === 'success' && $_SERVER['REQUEST_METHOD'] === 'POST'"],
    ['wasPost', "$_SERVER['REQUEST_METHOD'] === 'POST'"],
  ],
  subcon_name: [
    ['hasMessage', "!empty($swal_data)"],
    ['isError', "($swal_data['icon'] ?? '') === 'error'"],
    ['pOSTEditId', "$_POST['edit_id'] ?? ''"],
  ],
  suppliers: [
    ['hasMessage', "!empty($swal_data)"],
    ['isError', "($swal_data['icon'] ?? '') === 'error'"],
    ['pOSTEditId', "$_POST['edit_id'] ?? ''"],
  ],
  upload_attendance: [
    ['hasMessage', "!empty($message)"],
    ['hasAttendanceError', "isset($attendance_error)"],
  ],
  vehicles: [
    ['hasMessage', "isset($_SESSION['alert'])"],
    ['showModal', "(string) ($show_modal ?: '')"],
  ],
  warehouses: [
    ['hasMessage', "!empty($swal_data)"],
    ['isError', "($swal_data['icon'] ?? '') === 'error'"],
    ['reopenAddModal', "$_SERVER['REQUEST_METHOD'] === 'POST' && ($swal_data['icon'] ?? '') === 'error' && !isset($_POST['delete_id']) && !isset($_POST['update_id'])"],
    ['reopenEditModal', "$_SERVER['REQUEST_METHOD'] === 'POST' && ($swal_data['icon'] ?? '') === 'error' && isset($_POST['update_id'])"],
    ['pOST', "$_POST['update_id'] ?? ''"],
    ['pOST2', "$_POST['warehouse_name'] ?? ''"],
    ['pOST3', "$_POST['location'] ?? ''"],
    ['pOST4', "$_POST['capacity'] ?? ''"],
    ['pOST5', "$_POST['manager'] ?? ''"],
    ['pOST6', "$_POST['phone'] ?? ''"],
  ],
};

const done = [];
const problems = [];
for (const [slug, flags] of Object.entries(FLAGS)) {
  const file = path.join(ROOT, `${slug}.php`);
  if (!fs.existsSync(file)) { problems.push(`${slug}.php: no such page`); continue; }
  const raw = fs.readFileSync(file, 'utf8');
  const eol = raw.includes('\r\n') ? '\r\n' : '\n';
  let code = raw.replace(/\r\n/g, '\n');

  const marker = new RegExp(`^(\\s*)ocp_page_data\\("${slug}"`, 'm');
  const m = marker.exec(code);
  if (!m) { problems.push(`${slug}.php: no ocp_page_data("${slug}") call`); continue; }

  const todo = flags.filter(([name]) => !new RegExp(`\\$__ocp_data\\["${name}"\\]`).test(code));
  if (!todo.length) { done.push(`${slug}: already has its flags`); continue; }

  const indent = m[1];
  const block = [
    `${indent}/* Flags for the script. It is a separate request, so it cannot test this`,
    `${indent} * page's variables itself; these answer for it. Each is false on an ordinary`,
    `${indent} * load, so nothing is shown unless there is something to show. */`,
    ...todo.map(([name, expr]) => `${indent}$__ocp_data["${name}"] = (${expr});`),
    '',
  ].join('\n');

  code = code.slice(0, m.index) + block + code.slice(m.index);
  if (APPLY) fs.writeFileSync(file, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
  done.push(`${slug}: +${todo.map(([n]) => n).join(', +')}`);
}

console.log(`${done.length} page(s)${APPLY ? '' : ' (dry run)'}:`);
for (const d of done) console.log(`  ${d}`);
if (problems.length) {
  console.log(`\n${problems.length} problem(s):`);
  for (const p of problems) console.log(`  ${p}`);
}
