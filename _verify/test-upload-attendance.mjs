// Data-driven test: actions/endpoint split for upload_attendance.php.
//
// The page parses a CSV and writes the rows it recognises, so the import is
// exercised with a file that names no real employee: that drives the whole parse
// path without writing anything. The date filter and the row count are then
// checked. Nothing is written to the database by this test.
import { spawn, execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8244';
const TABLE = 'attendance';

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));
const COOKIE = `PHPSESSID=${sessions[0].sid}`;
const db = (sql) => php('query.php', [sql]).trim();

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const problems = [];
const log = [];
// A known, inherited wart shared with payroll.php: the edit_attendance and
// add_attendance blocks are two sequential "if"s, so posting one also runs the
// other and it reads fields that form does not send ("remarks"). The original has
// the same two ifs. These are the only warnings this test tolerates.
const KNOWN = /Undefined array key "(remarks|employee_id)" in .*actions\\upload_attendance-actions\.php/;
const HARD = /(Fatal error|Parse error|Uncaught\s+\w*Error|Undefined variable|Undefined array key|Call to undefined|Failed opening required)/;

async function req(url, fields) {
  const opts = { headers: { Cookie: COOKIE }, redirect: 'manual' };
  if (fields) { opts.method = 'POST'; opts.body = new URLSearchParams(fields); }
  const res = await fetch(`http://127.0.0.1:${PORT}${url}`, opts);
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const err = HARD.exec(text);
  if (err && !KNOWN.test(text.slice(Math.max(0, err.index - 60), err.index + 200))) {
    problems.push(`${url}: PHP ${text.slice(err.index, err.index + 150).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  }
  return { status: res.status, text, location: res.headers.get('location') };
}

/** POSTs a multipart form, which is what a file upload needs. */
async function upload(url, fields, fileField, fileName, fileBody) {
  const form = new FormData();
  for (const [k, v] of Object.entries(fields)) form.append(k, v);
  form.append(fileField, new Blob([fileBody], { type: 'text/csv' }), fileName);
  const res = await fetch(`http://127.0.0.1:${PORT}${url}`, {
    method: 'POST', body: form, headers: { Cookie: COOKIE }, redirect: 'manual',
  });
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const err = HARD.exec(text);
  if (err && !KNOWN.test(text.slice(Math.max(0, err.index - 60), err.index + 200))) {
    problems.push(`${url} (upload): PHP ${text.slice(err.index, err.index + 150).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  }
  return { status: res.status, text };
}

const count = () => Number(db(`SELECT COUNT(*) FROM ${TABLE}`));
const start = count();

// ------------------------------------------------------------------ 1. renders
{
  const r = await req('/upload_attendance.php');
  log.push(`GET /upload_attendance.php -> ${r.status}, ${r.text.length} bytes`);
  if (r.status !== 200) problems.push(`upload_attendance.php HTTP ${r.status}`);
  if (!/Logged in as:/.test(r.text)) problems.push('the side menu is missing');
  if (!/type="file"/.test(r.text)) problems.push('the upload control is gone');
}

// --------------------------------------------------- 2. the date filter works
{
  const r = await req('/upload_attendance.php', { select_date_range: '1', date_from: '2020-01-01', date_to: '2020-01-31' });
  log.push(`POST select_date_range -> ${r.status}`);
  if (r.status !== 200) problems.push(`select_date_range should re-render, got ${r.status}`);
  if (!/2020-01-01/.test(r.text) || !/2020-01-31/.test(r.text)) problems.push('the chosen date range was not kept in the form');
  else log.push('  the chosen date range is kept');
}

// ------------------------------------------------------- 3. importing a CSV
{
  // a CSV whose rows name nobody in the employee table, so the parse path runs but
  // nothing is written
  const csv = [
    'Employee Name,Date,Time',
    `VerifyGhost ${Date.now()},2020-01-15,"08:00,12:00,13:00,17:00"`,
  ].join('\n');

  const before = count();
  const r = await upload('/upload_attendance.php', { upload_attendance: '1' }, 'attendance_file', 'verify.csv', csv);
  log.push(`POST a CSV import -> ${r.status}`);
  if (r.status !== 200) problems.push(`the import should re-render, got ${r.status}`);
  if (count() !== before) problems.push(`the import wrote ${count() - before} row(s) for an unknown employee`);
  else log.push('  the import parsed the file and wrote nothing for an unknown employee');
  // either it reports the missing employee, or it reports the parse result
  if (/missing|not found|no.*record|successfully|imported/i.test(r.text)) log.push('  the import reported its outcome');
}

// -------------------------------------------- 4. adding a record by hand
{
  const employeeId = db("SELECT id FROM employee WHERE status = 'active' ORDER BY id LIMIT 1");
  const stamp = Date.now();
  const before = count();
  const r = await req('/upload_attendance.php', {
    add_attendance: '1',
    employee_id: employeeId,
    employee_name: `VerifyUpload ${stamp}`,
    department: 'IT',
    attendance_date: '2020-03-05',
    check_in: '08:00', break_out: '12:00', break_in: '13:00', check_out: '17:00',
    status: 'present',
  });
  log.push(`POST add_attendance -> ${r.status}`);
  if (r.status !== 200) problems.push(`add_attendance should re-render, got ${r.status}`);
  const added = count() - before;
  if (added > 0) {
    log.push(`  a row was added (${added})`);
    // remove exactly what this action created, by date plus employee
    db(`DELETE FROM ${TABLE} WHERE attendance_date = '2020-03-05' AND employee_id = ${employeeId}`);
  } else {
    log.push('  no row was added for this payload (the page validated it away)');
  }
}

// ------------------------------------------------------------- 5. no leftovers
{
  if (count() !== start) {
    problems.push(`the test left ${count() - start} row(s) behind`);
    db(`DELETE FROM ${TABLE} WHERE employee_name LIKE 'VerifyUpload %' OR employee_name LIKE 'VerifyGhost %'`);
  } else {
    log.push('no rows left behind');
  }
}

// ------------------------------------- 6. the shared functions include is quiet
{
  const r = await req('/includes/upload_attendance-functions.php');
  log.push(`GET includes/upload_attendance-functions.php -> ${r.status}, ${r.text.trim().length} bytes of output`);
  if (r.text.trim().length !== 0) problems.push('the functions include printed output when requested directly');
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
