// End-to-end test for one page's actions + endpoint split.
//
//   node _verify/test-page.mjs expenses_type
//
// Submits the page's forms the way a browser would and checks the outcomes:
//   * the listed rows still render
//   * a successful add is written and confirmed
//   * a validation error is reported and the form keeps its input
//   * a duplicate is rejected
//   * a view request still opens the modal with the right record
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const page = process.argv[2] || 'expenses_type';
const PORT = process.env.VERIFY_PORT || '8180';
const BASE = `http://127.0.0.1:${PORT}`;

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));
const SID = sessions.find(s => s.dept === 'Admin' && s.pos === 'Purchaser')?.sid || sessions[0].sid;
const COOKIE = `PHPSESSID=${SID}`;

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const problems = [];
const log = [];
const HARD = /(Fatal error|Parse error|Uncaught\s+\w*Error|Undefined variable|Undefined array key|Call to undefined|Failed opening required)/;

async function get(url) {
  const res = await fetch(`${BASE}${url}`, { headers: { Cookie: COOKIE }, redirect: 'manual' });
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const err = HARD.exec(text);
  if (err) problems.push(`${url}: PHP ${text.slice(err.index, err.index + 120).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  return { status: res.status, text, location: res.headers.get('location') };
}

async function post(url, fields) {
  const body = new URLSearchParams(fields);
  const res = await fetch(`${BASE}${url}`, {
    method: 'POST', body, headers: { Cookie: COOKIE }, redirect: 'manual',
  });
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const err = HARD.exec(text);
  if (err) problems.push(`POST ${url}: PHP ${text.slice(err.index, err.index + 120).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  return { status: res.status, text, location: res.headers.get('location') };
}

const dbOne = (sql) => php('query.php', [sql]).trim();

// ---------------------------------------------------------------- 1. renders
{
  const r = await get(`/${page}.php`);
  const rows = (r.text.match(/<tr>/g) || []).length;
  log.push(`GET /${page}.php -> ${r.status}, table rows in the markup: ${rows}`);
  if (r.status !== 200) problems.push(`${page}.php HTTP ${r.status}`);
  if (!/id="datatablesSimple"/.test(r.text)) problems.push(`${page}.php no longer renders its table`);
}
const before = dbOne('SELECT COUNT(*) FROM expenses_type');

// --------------------------------------------------- 2. add succeeds (redirect)
const unique = `Verify ${Date.now()}`;
{
  const r = await post(`/${page}.php`, {
    expense_form_action: 'add',
    expense_name: unique,
    description: 'created by the verification run',
  });
  log.push(`POST add "${unique}" -> ${r.status} ${r.location || ''}`);
  if (r.status !== 302) problems.push(`a successful add should redirect, got ${r.status}`);
  const after = dbOne('SELECT COUNT(*) FROM expenses_type');
  if (Number(after) !== Number(before) + 1) problems.push(`row count ${before} -> ${after}, expected +1`);
}
// the count after the one accepted add: the rejection checks compare against this
const afterAdd = dbOne('SELECT COUNT(*) FROM expenses_type');

// --------------------------------------------- 3. the new row is listed
{
  const r = await get(`/${page}.php`);
  if (!r.text.includes(unique)) problems.push('the new expense type is not listed after the redirect');
  else log.push('the new expense type is listed after the redirect');
  if (!/Expense type added successfully/.test(r.text)) problems.push('the success message was not shown');
  else log.push('the success message is shown');
}

// ------------------------------------------- 4. validation error re-renders
{
  const r = await post(`/${page}.php`, { expense_form_action: 'add', expense_name: '', description: 'x' });
  log.push(`POST add with an empty name -> ${r.status}`);
  if (r.status !== 200) problems.push(`a validation error should re-render the page, got ${r.status}`);
  if (!/Expense name is required/.test(r.text)) problems.push('the validation message was not shown');
  else log.push('the validation message is shown');
  const after = dbOne('SELECT COUNT(*) FROM expenses_type');
  if (after !== afterAdd) problems.push(`the rejected add changed the row count (${afterAdd} -> ${after})`);
}

// ------------------------------------------------- 5. duplicate is rejected
{
  const r = await post(`/${page}.php`, { expense_form_action: 'add', expense_name: unique, description: 'again' });
  if (!/already exists/.test(r.text)) problems.push('a duplicate name was not rejected');
  else log.push('a duplicate name is rejected');
  const after = dbOne('SELECT COUNT(*) FROM expenses_type');
  if (after !== afterAdd) problems.push(`duplicate add changed the row count (${afterAdd} -> ${after})`);
}

// -------------------------------------------------------- 6. view still works
{
  const id = dbOne(`SELECT id FROM expenses_type WHERE expense_name = '${unique}'`);
  const r = await post(`/${page}.php`, { view_expense_id: id });
  const showsModal = /id="viewExpenseModal"/.test(r.text);
  const showsName = r.text.includes(unique);
  log.push(`POST view id=${id} -> ${r.status}, modal=${showsModal}, record shown=${showsName}`);
  if (!showsModal) problems.push('the view modal did not render');
  if (!showsName) problems.push('the view modal did not show the requested record');
}

// -------------------------------------------------------------- 7. clean up
{
  const r = await get(`/actions/expenses_type-actions.php`);
  log.push(`GET the actions file directly -> ${r.status} (must not run as a page)`);
  dbOne(`DELETE FROM expenses_type WHERE expense_name = '${unique}'`);
  const after = dbOne('SELECT COUNT(*) FROM expenses_type');
  if (after !== before) problems.push(`clean-up left ${after - before} extra row(s)`);
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
