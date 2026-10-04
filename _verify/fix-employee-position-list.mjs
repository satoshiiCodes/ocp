// Makes the "Edit Employee Details" modal offer exactly the Position list that "Register
// New Employee" offers.
//
//   node fix-employee-position-list.mjs [--apply]
//
// The two modals had drifted: register had 20 positions, edit had 14, with eleven positions
// only register offered - so an employee could be registered with a position that could not
// be chosen when editing.
//
// The register list is the single source. It is copied verbatim, with the selected test
// pointed at the employee being edited. One employee in the live data holds a position the
// register list does not offer ("Auto Electrician"); for that employee only, the value is
// appended, so opening the modal can never silently blank a saved position. Everyone else
// sees precisely the register list.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const file = `${ROOT}\\employee_registration.php`;

const raw = fs.readFileSync(file, 'utf8');
const eol = raw.includes('\r\n') ? '\r\n' : '\n';
let code = raw.replace(/\r\n/g, '\n');

const registerRe = /(<select class="form-select" id="modal_position"[\s\S]*?<option value="">Select Position<\/option>\n)([\s\S]*?)(\n[ \t]*<\/select>)/;
const rm = registerRe.exec(code);
if (!rm) { console.log('  could not find the register modal\'s Position select'); process.exit(1); }
const registerItems = rm[2];
const values = [...registerItems.matchAll(/<option value="([^"]+)"/g)].map(m => m[1]);
console.log(`  register list: ${values.length} option(s)`);

const indent = (/^([ \t]*)<option/.exec(registerItems) || [, '                                            '])[1];

// the register markup, with the selected test pointed at the employee being edited
const editItems = registerItems
  .replace(/\(\$position == '([^']+)'\)/g, "($edit_employee_details['position'] == '$1')");

// only if the employee holds a position the register list does not offer
const phpValues = values.map(v => `'${v.replace(/'/g, "\\'")}'`).join(', ');
const tail = `<?php /* The register list is the list. If the employee being edited holds a
${indent} * position it does not offer, that one value is added so opening this modal can
${indent} * never silently blank a saved position. */ ?>
${indent}<?php $__ocp_edit_pos = $edit_employee_details['position'] ?? ''; ?>
${indent}<?php if ($__ocp_edit_pos !== '' && !in_array($__ocp_edit_pos, [${phpValues}], true)): ?>
${indent}<option value="<?php echo htmlspecialchars($__ocp_edit_pos, ENT_QUOTES); ?>" selected><?php echo htmlspecialchars($__ocp_edit_pos); ?></option>
${indent}<?php endif; ?>`;

const editRe = /(<select class="form-select" id="edit_position"[\s\S]*?<option value="">Select Position<\/option>\n)([\s\S]*?)(\n[ \t]*<\/select>)/;
if (!editRe.test(code)) { console.log('  could not find the edit modal\'s Position select'); process.exit(1); }
code = code.replace(editRe, (m, open, old, close) => open + editItems + '\n' + tail + close);

const count = (code.match(/id="edit_position"[\s\S]*?<\/select>/) || [''])[0].match(/value="[^"]*"/g);
console.log(`  edit list now: ${count ? count.length - 1 : 0} option(s) (plus any held position)`);
console.log(`  register modal untouched: ${/id="modal_position"/.test(code)}`);

if (APPLY) {
  fs.writeFileSync(file, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
  console.log('  APPLIED');
} else {
  console.log('  DRY RUN');
}
