// End-to-end check of the two reported problems.
//
//   node test-projects-form.mjs
//
// It adds a throw-away project (and removes it again, comparing row counts), then checks
// the page it lands on really carries the success message into the data island - which is
// what the script needs in order to show the alert.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8300';
const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const db = (sql) => php('query.php', [sql]).trim();
const sessions = JSON.parse(php('sessions.php'));
const COOKIE = `PHPSESSID=${sessions[0].sid}`;

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const problems = [];
const log = [];
const HARD = /(Fatal error|Parse error|Uncaught\s+\w*Error|Undefined variable|Undefined array key|Call to undefined|Failed opening required)/;

const req = async (url, fields) => {
  const opts = { headers: { Cookie: COOKIE }, redirect: 'manual' };
  if (fields) { opts.method = 'POST'; opts.body = new URLSearchParams(fields); }
  const res = await fetch(`http://127.0.0.1:${PORT}${url}`, opts);
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const err = HARD.exec(text);
  if (err) problems.push(`${url}: PHP ${text.slice(err.index, err.index + 160).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  return { status: res.status, text, location: res.headers.get('location') };
};

const islandOf = (html) => {
  const m = /window\.OCP_PAGE_PROJECTS\s*=\s*(\{[\s\S]*?\});/.exec(html);
  if (!m) return null;
  try { return JSON.parse(m[1]); } catch { return null; }
};

const projects = () => Number(db('SELECT COUNT(*) FROM projects'));
const links = () => Number(db('SELECT COUNT(*) FROM project_engineers'));
const start = { projects: projects(), links: links() };
log.push(`fixture: ${start.projects} project(s), ${start.links} engineer link(s)`);

const engineerId = db("SELECT id FROM users WHERE department = 'Engineering' AND status = 'active' ORDER BY id LIMIT 1");
if (!engineerId) { console.log('  (no Engineering user to assign; cannot run)'); server.kill(); process.exit(0); }

// ---------------------------------------------- 1. the engineer options are real
{
  const r = await req('/projects.php');
  const data = islandOf(r.text);
  if (!data) problems.push('the page renders no projects data island');
  else {
    const opts = data.engineerOptionsHtml || '';
    const count = (opts.match(/<option/g) || []).length;
    log.push(`engineerOptionsHtml carries ${count} option(s)`);
    if (count < 1) problems.push('the engineer options handed to the script are empty');
    // The server-rendered first select carries a placeholder option as well, so the
    // island's list should be exactly one short of it.
    const first = /<select class="form-select" name="engineers\[\]"[\s\S]*?<\/select>/.exec(r.text);
    const firstCount = first ? (first[0].match(/<option/g) || []).length : 0;
    log.push(`  the first (server-rendered) select has ${firstCount} option(s), including its placeholder`);
    if (firstCount > 0 && count !== firstCount - 1) {
      problems.push(`a newly added select would get ${count} options but the first one offers ${firstCount - 1} engineers`);
    } else {
      log.push('  a newly added select gets the same engineers as the first');
    }
  }
}

// ------------------------------ 2. a successful add carries a message into the island
{
  const name = 'VERIFY PROJECT 1780000000000';
  const code = 'VERIFY-1780000000000';
  const r = await req('/projects.php', {
    project_name: name, project_code: code, status: 'planning',
    'engineers[]': engineerId, address: 'x', description: 'created by the verification run',
    // the columns are DATE NOT NULL-ish: an empty string is not accepted by MySQL
    start_date: '2026-01-01', end_date: '2026-12-31',
  });
  log.push(`POST a new project -> ${r.status} ${r.location || ''}`);
  if (r.status !== 302) problems.push(`adding a project returned ${r.status}, expected a redirect`);

  // follow the redirect with the same cookie, as the browser would
  const after = await req('/projects.php');
  const data = islandOf(after.text);
  const msg = data && data.successMessage;
  log.push(`  the page after the redirect carries successMessage: ${JSON.stringify(msg || null)}`);
  if (!msg) problems.push('the success message never reached the island, so no alert can show');
  else if (!/added successfully/i.test(msg)) problems.push(`the success message reads ${JSON.stringify(msg)}`);

  // the row is really there
  if (projects() !== start.projects + 1) problems.push(`the project was not added (${start.projects} -> ${projects()})`);
  else log.push('  the project was added');

  // ------------------------------------------- clean up
  const pid = db(`SELECT id FROM projects WHERE project_name = '${name}' ORDER BY id DESC LIMIT 1`);
  if (pid) {
    db(`DELETE FROM project_engineers WHERE project_id = ${pid}`);
    db(`DELETE FROM projects WHERE id = ${pid}`);
    log.push('  the throw-away project was removed');
  }
}

// ----------------------------------- 3. a validation error comes back as a message
{
  const before = projects();
  const r = await req('/projects.php', { project_name: '', project_code: '', 'engineers[]': '' });
  const data = islandOf(r.text);
  const msg = data && data.errorMessage;
  log.push(`POST an incomplete project -> ${r.status}, errorMessage: ${JSON.stringify(msg || null)}`);
  if (!msg) problems.push('a rejected form carries no error message into the island');
  if (projects() !== before) problems.push('a rejected form still wrote a row');
}

// ------------------------------------------------------------- 4. no leftovers
{
  if (projects() !== start.projects || links() !== start.links) {
    problems.push(`the test left data behind: ${start.projects}/${start.links} -> ${projects()}/${links()}`);
  } else {
    log.push('no rows left behind');
  }
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
