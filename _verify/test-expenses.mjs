// Data-driven test: actions/endpoint split for expenses.php.
//
// Adding or editing an expense also moves the cash-on-hand ledger, so this test does
// not post a real expense: it checks the page renders with its listing, types,
// dropdowns and balance, that the filters round-trip, and that an invalid submission
// is rejected without writing anything. The expense and cash row counts are compared
// before and after.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8264';

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));
const COOKIE = `PHPSESSID=${sessions[0].sid}`;
const db = (sql) => php('query.php', [sql]).trim();

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2300));

const problems = [];
const log = [];
const HARD = /(Fatal error|Parse error|Uncaught\s+\w*Error|Undefined variable|Undefined array key|Call to undefined|Failed opening required)/;

async function req(url, fields) {
  const opts = { headers: { Cookie: COOKIE }, redirect: 'manual' };
  if (fields) { opts.method = 'POST'; opts.body = new URLSearchParams(fields); }
  const res = await fetch(`http://127.0.0.1:${PORT}${url}`, opts);
  const text = Buffer.from(await res.arrayBuffer()).toString('utf8');
  const err = HARD.exec(text);
  if (err) problems.push(`${url}: PHP ${text.slice(err.index, err.index + 150).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  return { status: res.status, text, location: res.headers.get('location') };
}

const expenses = () => Number(db('SELECT COUNT(*) FROM expenses'));
const cash = () => Number(db('SELECT COUNT(*) FROM cash_on_hand'));
const start = { expenses: expenses(), cash: cash() };
log.push(`fixture: ${start.expenses} expense(s), ${start.cash} cash row(s)`);

// ------------------------------------------------------------------ 1. renders
{
  const r = await req('/expenses.php');
  log.push(`GET /expenses.php -> ${r.status}, ${r.text.length} bytes`);
  if (r.status !== 200) problems.push(`expenses.php HTTP ${r.status}`);
  if (!/Logged in as:/.test(r.text)) problems.push('the side menu is missing');
  // the form's dropdowns and the balance come from the endpoint
  if (!/Cash on Hand|Balance/i.test(r.text)) problems.push('the cash balance panel is gone');
  else log.push('  the cash balance panel renders');
  if (!/name="expense_type_id"/.test(r.text)) problems.push('the expense-type control is missing');
  else log.push('  the expense-type control is present');
  if (!/name="employee_id"/.test(r.text)) problems.push('the employee control is missing');
  else log.push('  the employee control is present');
}

// ------------------------------------------------------- 2. the filters round-trip
{
  const r = await req('/expenses.php?start_date=2020-01-01&end_date=2020-12-31');
  log.push(`GET with a date filter -> ${r.status}, ${r.text.length} bytes`);
  if (r.status !== 200) problems.push(`the filtered view HTTP ${r.status}`);
  if (!/2020-01-01/.test(r.text)) problems.push('the start-date filter was not kept');
  else log.push('  the date filter is kept');

  const t = await req('/expenses.php?filter_type=999999');
  if (t.status !== 200) problems.push(`an unknown type filter HTTP ${t.status}`);
  else log.push('  an unknown type filter still renders');
}

// ------------------------------------------ 3. an invalid submission writes nothing
{
  const before = { expenses: expenses(), cash: cash() };
  const r = await req('/expenses.php', {
    add_expense: '1', expense_type_id: '', amount: '0', expense_date: '', description: 'created by the verification run',
  });
  log.push(`POST add with empty required fields -> ${r.status}`);
  if (expenses() !== before.expenses) problems.push(`an invalid expense was written (${before.expenses} -> ${expenses()})`);
  else log.push('  no expense was written');
  if (cash() !== before.cash) problems.push(`the cash ledger moved (${before.cash} -> ${cash()})`);
  else log.push('  the cash ledger did not move');
}

// --------------------------------- 4. deleting a missing expense moves nothing
{
  const before = { expenses: expenses(), cash: cash() };
  await req('/expenses.php', { delete_expense: '1', expense_id: 999999999 });
  if (expenses() !== before.expenses) problems.push('deleting a non-existent expense changed the expense count');
  else if (cash() !== before.cash) problems.push('deleting a non-existent expense moved the cash ledger');
  else log.push('deleting a non-existent expense left both ledgers alone');
}

// ------------------------------------------------------------- 5. no leftovers
{
  if (expenses() !== start.expenses || cash() !== start.cash) {
    problems.push(`the test left ${expenses() - start.expenses} expense(s) and ${cash() - start.cash} cash row(s) behind`);
  } else {
    log.push('no rows left behind');
  }
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
