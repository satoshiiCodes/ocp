// Data-driven test: actions/endpoint split for projects.php.
//
// The add form inserts a project plus its engineers, so the checks cover both
// tables and clean up after themselves.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8188';
const BASE = `http://127.0.0.1:${PORT}`;

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));
const SID = sessions[0].sid;
const COOKIE = `PHPSESSID=${SID}`;
const db = (sql) => php('query.php', [sql]).trim();

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const problems = [];
const log = [];
const HARD = /(Fatal error|Parse error|Uncaught\s+\w*Error|Undefined variable|Undefined array key|Call to undefined|Failed opening required|Error creating)/;

async function req(url, fields) {
  const opts = { headers: { Cookie: COOKIE }, redirect: 'manual' };
  if (fields) { opts.method = 'POST'; opts.body = fields; }
  const res = await fetch(`${BASE}${url}`, opts);
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const err = HARD.exec(text);
  if (err) problems.push(`${url}: PHP ${text.slice(err.index, err.index + 130).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  return { status: res.status, text, location: res.headers.get('location') };
}

const count = () => Number(db('SELECT COUNT(*) FROM projects'));
const engCount = () => Number(db('SELECT COUNT(*) FROM project_engineers'));
const start = count();
const stamp = Date.now();
const name = `VerifyProject ${stamp}`;
const code = `VP-${stamp}`;

// the engineers the form offers
const engineers = JSON.parse(php('engineers.php'));

// ------------------------------------------------------------------ 1. renders
{
  const r = await req('/projects.php');
  log.push(`GET /projects.php -> ${r.status}, table=${/id="datatablesSimple"|projectForm/.test(r.text)}, engineer options=${(r.text.match(/Select an Engineer/g) || []).length}`);
  if (r.status !== 200) problems.push(`projects.php HTTP ${r.status}`);
  if (!/id="projectForm"/.test(r.text)) problems.push('the add-project form is gone');
  if (!/Select an Engineer/.test(r.text)) problems.push('the engineer dropdown is gone');
}

// ------------------------------------------------------------ 2. add succeeds
{
  const body = new URLSearchParams();
  body.append('project_name', name);
  body.append('project_code', code);
  body.append('address', 'Verification Address');
  body.append('description', 'created by the verification run');
  body.append('start_date', '2026-01-01');
  body.append('end_date', '2026-12-31');
  body.append('status', 'planning');
  body.append('threshold_amount', '1,234.56');
  body.append('engineers[]', String(engineers[0]));

  const r = await req('/projects.php', body);
  log.push(`POST add "${name}" -> ${r.status} ${r.location || ''}`);
  if (r.status !== 302) problems.push(`a successful add should redirect, got ${r.status}`);
  if (count() !== start + 1) problems.push(`projects ${start} -> ${count()}, expected +1`);
  if (engCount() < 1) problems.push('no project_engineers row was written');
  else log.push('the project and its engineer row were written');

  const listed = await req('/projects.php');
  if (!listed.text.includes(name)) problems.push('the new project is not listed');
  else log.push('the new project is listed');
  if (!/Project added successfully/.test(listed.text)) problems.push('the success message was not shown');
  else log.push('the success message is shown');
}

const id = db(`SELECT id FROM projects WHERE project_code = '${code}'`);

// ------------------------------------------- 3. validation error repopulates
{
  const body = new URLSearchParams();
  body.append('project_name', '');
  body.append('project_code', 'X');
  body.append('engineers[]', String(engineers[0]));
  const r = await req('/projects.php', body);
  log.push(`POST add with an empty name -> ${r.status}`);
  if (r.status !== 200) problems.push(`a validation error should re-render, got ${r.status}`);
  if (!/Project name is required/.test(r.text)) problems.push('the required-name message was not shown');
  else log.push('the required-name message is shown');
  if (count() !== start + 1) problems.push('the rejected add changed the project count');
}

{
  const body = new URLSearchParams();
  body.append('project_name', 'X');
  body.append('project_code', 'X');
  const r = await req('/projects.php', body);
  if (!/At least one engineer is required/.test(r.text)) problems.push('a project with no engineer was accepted');
  else log.push('a project with no engineer is rejected');
}

// -------------------------------------------------------------- 4. no leftovers
{
  if (id) db(`DELETE FROM projects WHERE id = ${id}`);
  if (count() !== start) {
    problems.push(`the test left ${count() - start} project(s) behind`);
    db(`DELETE FROM projects WHERE project_name LIKE 'VerifyProject %'`);
  } else {
    log.push('no rows left behind');
  }
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
