// Verifies the Position select in "Edit Employee Details" offers the same list as
// "Register New Employee", and still shows a position the register list does not offer.
//
//   node test-employee-position-list.mjs
//
// The two modals had drifted apart: register offered 20 positions, edit 14, with eleven
// positions only register offered - so an employee could be registered with a position that
// could not then be chosen when editing. One employee in the live data holds "Auto
// Electrician", which the register list does not offer; opening that employee's modal must
// still show it, or saving would blank it.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8510';
const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const rows = (s) => php('rows.php', [s]).split('\n').map(l => l.trim()).filter(l => l.startsWith('{')).map(l => JSON.parse(l));
const SID = JSON.parse(php('sessions.php'))[0].sid;

const problems = [];
const log = [];

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2200));

const get = async (body) => {
  const res = await fetch(`http://127.0.0.1:${PORT}/employee_registration.php`, {
    method: body ? 'POST' : 'GET',
    ...(body ? { body: new URLSearchParams(body) } : {}),
    headers: { Cookie: `PHPSESSID=${SID}` }, redirect: 'manual',
  });
  return Buffer.from(await res.arrayBuffer()).toString('utf8');
};

const optionsOf = (html, id) => {
  // anchor on the attribute itself: `id="x"` also appears in a label's for="x", and a loose
  // match then runs past the end of the real select and swallows the next one
  const m = new RegExp(`<select[^>]*\\bid="${id}"[\\s\\S]*?</select>`).exec(html);
  if (!m) return null;
  return [...m[0].matchAll(/<option value="([^"]*)"([^>]*)>([^<]*)<\/option>/g)].map(x => ({
    value: x[1], selected: /selected/.test(x[2]), label: x[3].trim(),
  }));
};

// ------------------------------------------------ the register modal
// It is always rendered, so it is the reference list. The edit modal only renders when
// edit_details is posted, so it is read from the POST answer below.
const plain = await get(null);
const register = optionsOf(plain, 'modal_position');
if (!register) { console.log('  could not find the register Position select'); server.kill(); process.exit(1); }
const regValues = register.map(o => o.value).filter(Boolean);
log.push(`register: ${regValues.length} positions`);

// ------------------------------------------------ the employee with an unusual position
const odd = rows(`SELECT id, position FROM employee WHERE position NOT IN (${regValues.map(v => `'${v.replace(/'/g, "''")}'`).join(',')}) LIMIT 1`)[0];
if (!odd) {
  log.push('no employee holds a position outside the register list - nothing to check');
} else {
  const html = await get({ edit_details: '1', employee_id: String(odd.id) });
  const options = optionsOf(html, 'edit_position');
  if (!options) problems.push('the edit modal did not render for an employee with an unusual position');
  else {
    const chosen = options.filter(o => o.selected);
    log.push(`editing employee ${odd.id} (position "${odd.position}"): ${options.length} options, selected=${JSON.stringify(chosen.map(o => o.value))}`);
    if (!chosen.length) problems.push(`editing employee ${odd.id} preselects nothing - saving would blank "${odd.position}"`);
    else if (chosen[0].value !== odd.position) problems.push(`editing employee ${odd.id} preselects "${chosen[0].value}", expected "${odd.position}"`);
    else log.push('its position is offered and preselected');
    if (!options.some(o => o.value === '')) problems.push('the placeholder is missing from this render');
    const missing = regValues.filter(v => !options.map(o => o.value).includes(v));
    if (missing.length) problems.push(`register positions missing when editing: ${missing.join(', ')}`);
  }
}

// ------------------------------------------------ an ordinary employee
const normal = rows(`SELECT id, position FROM employee WHERE position IN (${regValues.map(v => `'${v.replace(/'/g, "''")}'`).join(',')}) LIMIT 1`)[0];
if (normal) {
  const html = await get({ edit_details: '1', employee_id: String(normal.id) });
  const options = optionsOf(html, 'edit_position');
  const values = (options || []).map(o => o.value).filter(Boolean);
  const chosen = (options || []).filter(o => o.selected);
  log.push(`edit:     ${values.length} positions (employee ${normal.id}, position "${normal.position}")`);
  if (JSON.stringify(values) !== JSON.stringify(regValues)) {
    const onlyReg = regValues.filter(v => !values.includes(v));
    const onlyEdit = values.filter(v => !regValues.includes(v));
    problems.push(`an ordinary employee sees a different list: only in register [${onlyReg.join(', ')}], only in edit [${onlyEdit.join(', ')}]`);
  } else {
    log.push('the two lists are identical');
  }
  if (!chosen.length || chosen[0].value !== normal.position) problems.push(`editing employee ${normal.id} did not preselect "${normal.position}"`);
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
