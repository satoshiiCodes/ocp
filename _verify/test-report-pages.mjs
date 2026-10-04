// Data-driven test: actions/endpoint split for the three pages finished together.
//
//   fuel_report.php  read-only: filters on the query string, no actions
//   backup_sql.php   creates a backup file on request; the auto-backup check runs
//                    on load but has no schedule, so it cannot fire
//   index.php        the login page
//
// Nothing here changes stored data: the only file it touches is a backup .sql,
// which it removes again.
import { spawn, execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8237';

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));
const COOKIE = `PHPSESSID=${sessions[0].sid}`;

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const problems = [];
const log = [];
const HARD = /(Fatal error|Parse error|Uncaught\s+\w*Error|Undefined variable|Undefined array key|Call to undefined|Failed opening required)/;

async function req(url, fields, cookie = COOKIE) {
  const opts = { headers: { Cookie: cookie }, redirect: 'manual' };
  if (fields) { opts.method = 'POST'; opts.body = new URLSearchParams(fields); }
  const res = await fetch(`http://127.0.0.1:${PORT}${url}`, opts);
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const err = HARD.exec(text);
  if (err) problems.push(`${url}: PHP ${text.slice(err.index, err.index + 140).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  return { status: res.status, text, location: res.headers.get('location') };
}

/* ------------------------------------------------------------- fuel_report */
{
  log.push('--- fuel_report.php');
  const r = await req('/fuel_report.php');
  if (r.status !== 200) problems.push(`fuel_report.php: HTTP ${r.status}`);
  log.push(`  GET -> ${r.status}, ${r.text.length} bytes`);
  if (!/Logged in as:/.test(r.text)) problems.push('fuel_report.php: the side menu is missing');

  // the page summarises the same rows it lists, so both must be present
  const hasTable = /<table/.test(r.text);
  const hasTotal = /Total|total/i.test(r.text);
  if (!hasTable) problems.push('fuel_report.php: the report table is gone');
  if (!hasTotal) problems.push('fuel_report.php: the totals are gone');
  log.push(`  table=${hasTable} totals=${hasTotal}`);

  // a filter that matches nothing must still render, not crash
  const f = await req('/fuel_report.php?start_date=1990-01-01&end_date=1990-01-02');
  if (f.status !== 200) problems.push(`fuel_report.php with an empty filter: HTTP ${f.status}`);
  else log.push('  an empty date filter still renders');

  const t = await req('/fuel_report.php?gasoline_type=__none__');
  if (t.status !== 200) problems.push(`fuel_report.php with an unknown type: HTTP ${t.status}`);
  else log.push('  an unknown type filter still renders');

  // the actions file is a guard only
  const a = await req('/actions/fuel_report-actions.php');
  log.push(`  its actions file alone -> ${a.status} ${a.text.trim().slice(0, 60)}`);
  if (a.status !== 200) problems.push('fuel_report.php: the actions file alone should answer JSON');
}

/* --------------------------------------------------------------- backup_sql */
{
  log.push('--- backup_sql.php');
  const r = await req('/backup_sql.php');
  if (r.status !== 200) problems.push(`backup_sql.php: HTTP ${r.status}`);
  if (!/Logged in as:/.test(r.text)) problems.push('backup_sql.php: the side menu is missing');
  log.push(`  GET -> ${r.status}, ${r.text.length} bytes`);

  // the page must not have created a backup just by being loaded
  const backupsDir = path.join(ROOT, 'backups');
  const before = fs.existsSync(backupsDir) ? fs.readdirSync(backupsDir).filter(f => f.endsWith('.sql')) : [];
  const load = await req('/backup_sql.php');
  const after = fs.existsSync(backupsDir) ? fs.readdirSync(backupsDir).filter(f => f.endsWith('.sql')) : [];
  if (after.length !== before.length) problems.push('backup_sql.php: loading the page created a backup');
  else log.push('  loading the page does not create a backup');
  void load;

  // the endpoint on its own must be harmless
  const e = await req('/api/backup_sql-endpoint.php');
  if (e.text.trim().length !== 0) problems.push('backup_sql.php: its endpoint printed output when requested directly');
  else log.push('  its endpoint alone prints nothing');
}

/* ------------------------------------------------------------------- index */
{
  log.push('--- index.php');
  // logged out: the login form
  const res = await fetch(`http://127.0.0.1:${PORT}/index.php`, { redirect: 'manual' });
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  if (res.status !== 200) problems.push(`index.php: HTTP ${res.status}`);
  if (!/name="username"/.test(text) || !/name="password"/.test(text)) problems.push('index.php: the login form is gone');
  else log.push(`  GET -> ${res.status}, the login form renders`);

  // bad credentials are still rejected
  const bad = await fetch(`http://127.0.0.1:${PORT}/index.php`, {
    method: 'POST', body: new URLSearchParams({ username: '__no_such_user__', password: 'wrong' }), redirect: 'manual',
  });
  const badText = Buffer.from(await bad.arrayBuffer()).toString('utf8');
  log.push(`  POST bad credentials -> ${bad.status}`);
  if (bad.status !== 200) problems.push(`index.php: bad credentials should re-render, got ${bad.status}`);
  if (!/Invalid username or password/.test(badText)) problems.push('index.php: bad credentials were not rejected');
  else log.push('  bad credentials are rejected');

  // an empty submission is reported
  const empty = await fetch(`http://127.0.0.1:${PORT}/index.php`, {
    method: 'POST', body: new URLSearchParams({ username: '', password: '' }), redirect: 'manual',
  });
  const emptyText = Buffer.from(await empty.arrayBuffer()).toString('utf8');
  if (!/Please enter both username and password/.test(emptyText)) problems.push('index.php: the empty-form message is gone');
  else log.push('  an empty submission is reported');
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
