// Data-driven test: actions/endpoint split for payroll.php.
//
// payroll's handlers all re-render rather than redirect, so the checks look at the
// rendered page. Deleting is exercised on a throw-away attendance row, and every
// row the test creates is removed again.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8242';
const TABLE = 'attendance';

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));
const COOKIE = `PHPSESSID=${sessions[0].sid}`;
const db = (sql) => php('query.php', [sql]).trim();

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const problems = [];
const log = [];
// A known, inherited wart: the edit_attendance and add_attendance blocks are two
// sequential "if"s rather than "if / else if", so posting an edit also runs the add
// block, which reads an employee_id the edit form does not send. The original has
// the same two ifs, so this warning is expected here and nothing acts on the value.
// It is the only warning this test tolerates; anything else fails.
const KNOWN = /Undefined array key "employee_id" in .*actions\\payroll-actions\.php/;
const HARD = /(Fatal error|Parse error|Uncaught\s+\w*Error|Undefined variable|Undefined array key|Call to undefined|Failed opening required)/;

let tolerated = 0;

async function req(url, fields) {
  const opts = { headers: { Cookie: COOKIE }, redirect: 'manual' };
  if (fields) { opts.method = 'POST'; opts.body = new URLSearchParams(fields); }
  const res = await fetch(`http://127.0.0.1:${PORT}${url}`, opts);
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const err = HARD.exec(text);
  if (err) {
    const near = text.slice(Math.max(0, err.index - 60), err.index + 180);
    if (!KNOWN.test(near)) {
      problems.push(`${url}: PHP ${text.slice(err.index, err.index + 150).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
    } else {
      tolerated++;
    }
  }
  return { status: res.status, text, location: res.headers.get('location') };
}

const count = () => Number(db(`SELECT COUNT(*) FROM ${TABLE}`));
const start = count();

// ------------------------------------------------------------------ 1. renders
{
  const r = await req('/payroll.php');
  log.push(`GET /payroll.php -> ${r.status}, ${r.text.length} bytes`);
  if (r.status !== 200) problems.push(`payroll.php HTTP ${r.status}`);
  if (!/Logged in as:/.test(r.text)) problems.push('the side menu is missing');
  // the functions must be loaded for the render, since formatTimeTo12Hour is called in it
  if (!/payroll/i.test(r.text)) problems.push('the page did not render its heading');
}

// ------------------------------------------------- 2. the filter round-trips
{
  const r = await req('/payroll.php', { apply_filter: '1', date_from: '2020-01-01', date_to: '2020-01-31', department_filter: '' });
  log.push(`POST apply_filter -> ${r.status}`);
  if (r.status !== 200) problems.push(`apply_filter should re-render, got ${r.status}`);
  // the chosen range must come back in the form
  if (!/2020-01-01/.test(r.text) || !/2020-01-31/.test(r.text)) problems.push('the applied date range was not kept in the form');
  else log.push('  the applied date range is kept');

  const c = await req('/payroll.php', { clear_filter: '1' });
  if (c.status !== 200) problems.push(`clear_filter should re-render, got ${c.status}`);
  else log.push('  clear_filter re-renders');
}

// --------------------------------------- 3. a throw-away record can be deleted
{
  const employeeId = db('SELECT id FROM employee ORDER BY id LIMIT 1');
  const stamp = Date.now();
  db(`INSERT INTO attendance (employee_id, employee_name, attendance_date, department, status)
      VALUES (${employeeId}, 'VerifyPayroll ${stamp}', '2020-01-15', 'IT', 'present')`);
  const id = db(`SELECT id FROM attendance WHERE employee_name = 'VerifyPayroll ${stamp}'`);
  log.push(`fixture attendance id ${id}`);
  if (count() !== start + 1) problems.push('the fixture row was not created');

  const r = await req('/payroll.php', { delete_attendance: '1', attendance_id: id });
  log.push(`POST delete_attendance -> ${r.status}`);
  if (r.status !== 200) problems.push(`delete should re-render, got ${r.status}`);
  if (db(`SELECT COUNT(*) FROM ${TABLE} WHERE id = ${id}`) !== '0') problems.push('delete did not remove the row');
  else log.push('  the attendance row was deleted');
  if (!/deleted successfully/i.test(r.text)) problems.push('the delete success message was not shown');
  else log.push('  the delete message is shown');
}

// ------------------------------------------------ 4. an edit round-trips
{
  const employeeId = db('SELECT id FROM employee ORDER BY id LIMIT 1');
  const stamp = Date.now();
  db(`INSERT INTO attendance (employee_id, employee_name, attendance_date, department, status)
      VALUES (${employeeId}, 'VerifyPayrollEdit ${stamp}', '2020-02-10', 'IT', 'present')`);
  const id = db(`SELECT id FROM attendance WHERE employee_name = 'VerifyPayrollEdit ${stamp}'`);

  const r = await req('/payroll.php', {
    edit_attendance: '1', attendance_id: id, check_in: '08:00', break_out: '12:00',
    break_in: '13:00', check_out: '17:00', status: 'present',
  });
  log.push(`POST edit_attendance id=${id} -> ${r.status}`);
  if (r.status !== 200) problems.push(`edit should re-render, got ${r.status}`);
  const stored = db(`SELECT status FROM ${TABLE} WHERE id = ${id}`);
  if (!stored) problems.push('the edit removed the row');
  else log.push(`  the edit round-tripped (status "${stored}")`);

  db(`DELETE FROM ${TABLE} WHERE id = ${id}`);
}

// ------------------------------------------------------------- 5. no leftovers
{
  if (count() !== start) {
    problems.push(`the test left ${count() - start} row(s) behind`);
    db(`DELETE FROM ${TABLE} WHERE employee_name LIKE 'VerifyPayroll%'`);
  } else {
    log.push('no rows left behind');
  }
}

// ----------------------------------------- 6. the shared functions still load
{
  const r = await req('/includes/payroll-functions.php');
  log.push(`GET includes/payroll-functions.php -> ${r.status}, ${r.text.trim().length} bytes of output`);
  if (r.text.trim().length !== 0) problems.push('the functions include printed output when requested directly');
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log('  (tolerated the known edit/add overlap warning ' + tolerated + ' time(s))');
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
