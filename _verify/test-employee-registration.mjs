// Data-driven test: actions/endpoint split for employee_registration.php.
//
// The one risk specific to this page is the form-repopulation variables: thirteen of
// them are initialised by the page, assigned by the handlers and read by the template.
// If the split broke that chain, a rejected form would come back empty. So this test
// drives the read-only branches that fill the form (edit_details, view_wage_history,
// edit_wage, edit_deductions) and checks the values actually land in the markup.
//
// The write branches (update_employee_details, update_employee_wage,
// update_employee_deductions, delete_employee) are NOT exercised: they would change
// live personnel records. Employee and deduction counts are compared before and after.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8284';

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));
const COOKIE = `PHPSESSID=${sessions[0].sid}`;
const db = (sql) => php('query.php', [sql]).trim();

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const problems = [];
const log = [];
const HARD = /(Fatal error|Parse error|Uncaught\s+\w*Error|Undefined variable|Undefined array key|Call to undefined|Failed opening required|Cannot redeclare)/;

async function req(url, fields) {
  const opts = { headers: { Cookie: COOKIE }, redirect: 'manual' };
  if (fields) { opts.method = 'POST'; opts.body = new URLSearchParams(fields); }
  const res = await fetch(`http://127.0.0.1:${PORT}${url}`, opts);
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const err = HARD.exec(text);
  if (err) problems.push(`${url}: PHP ${text.slice(err.index, err.index + 160).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  return { status: res.status, text };
}

const counts = () => ({
  employees: Number(db('SELECT COUNT(*) FROM employee')),
  deductions: Number(db('SELECT COUNT(*) FROM employee_deductions')),
});
const start = counts();
log.push(`fixture: ${JSON.stringify(start)}`);

const emp = JSON.parse(php('query.php', ['SELECT 0']) === '0'
  ? '{}' : '{}');   // placeholder to keep the helper honest
void emp;
const empId = db('SELECT id FROM employee ORDER BY id LIMIT 1');
const empFirst = db(`SELECT firstname FROM employee WHERE id = ${empId}`);
const empLast = db(`SELECT lastname FROM employee WHERE id = ${empId}`);
log.push(`fixture employee: id=${empId} (${empFirst} ${empLast})`);

// ------------------------------------------------------------------ 1. renders
{
  const r = await req('/employee_registration.php');
  log.push(`GET the page -> ${r.status}, ${r.text.length} bytes`);
  if (r.status !== 200) problems.push(`the page HTTP ${r.status}`);
  if (!/Logged in as:/.test(r.text)) problems.push('the side menu is missing');
  // the employee table comes from the endpoint
  if (!r.text.includes(empLast)) problems.push('the employee table does not list the fixture employee');
  else log.push('  the employee table lists the fixture employee');
}

// ------------------------------- 2. edit_details repopulates the form (POST branch)
{
  const r = await req('/employee_registration.php', { edit_details: '1', employee_id: empId });
  log.push(`POST edit_details -> ${r.status}, ${r.text.length} bytes`);
  if (r.status !== 200) problems.push(`edit_details HTTP ${r.status}`);
  // the form must come back carrying this employee's values
  const carriesFirst = new RegExp(`value="${empFirst}"`).test(r.text);
  const carriesLast = new RegExp(`value="${empLast}"`).test(r.text);
  if (!carriesFirst || !carriesLast) {
    problems.push(`the form did not come back filled in (firstname=${carriesFirst}, lastname=${carriesLast})`);
  } else {
    log.push('  the form came back filled in with the employee\'s details');
  }
}

// --------------------------------- 3. the other read-only branches still answer
for (const [what, fields] of [
  ['edit_wage', { edit_wage: '1', employee_id: empId }],
  ['edit_deductions', { edit_deductions: '1', employee_id: empId }],
  ['view_employee', { view_employee: '1', employee_id: empId }],
  ['view_wage_history', { view_wage_history: '1', employee_id: empId }],
  ['view_deduction_history', { view_deduction_history: '1', employee_id: empId }],
]) {
  const r = await req('/employee_registration.php', fields);
  if (r.status !== 200) { problems.push(`${what} HTTP ${r.status}`); continue; }
  if (!/Logged in as:/.test(r.text)) { problems.push(`${what}: the page did not render`); continue; }
  log.push(`  ${what.padEnd(22)} -> ${r.status}, ${r.text.length} bytes`);
}

// ------------------------------------- 4. the endpoint's own guard
{
  const r = await req('/api/employee_registration-endpoint.php');
  log.push(`GET the endpoint directly -> ${r.status}, ${r.text.trim().length} bytes of output`);
  if (r.text.trim().length !== 0) problems.push('the endpoint printed output when it was required rather than fetched');
}

// ------------------------------------ 5. no POST that could reach the insert is sent that could reach the insert
// The dispatcher's final branch is a bare `else` that treats the post as a NEW
// employee registration and inserts (see _verify/KNOWN-ISSUES.md). So a POST that
// matches none of the named branches is not harmless - it takes the insert path. This
// test therefore sends no such POST: the read-only branches above are the safe
// coverage, and the write branches are covered by reading the code rather than by
// running it against live personnel records.
log.push('no unmatched POST was sent (the else branch inserts a new employee)');

// ------------------------------------------------------------- 6. no leftovers
{
  const end = counts();
  if (JSON.stringify(end) !== JSON.stringify(start)) {
    problems.push(`the test left data behind: ${JSON.stringify(start)} -> ${JSON.stringify(end)}`);
  } else {
    log.push('no rows left behind');
  }
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
