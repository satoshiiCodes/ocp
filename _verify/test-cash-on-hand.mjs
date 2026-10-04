// Data-driven test: actions/endpoint split for cash_on_hand.php.
//
// The actions change a running balance, so this test does NOT post a real
// transaction: that would move the ledger and the totals every other page reads.
// Instead it checks the page renders with its balance and totals, that the filters
// round-trip, and that a deliberately invalid submission is rejected without
// writing anything. The row count is compared before and after.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8250';
const TABLE = 'cash_on_hand';

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

const count = () => Number(db(`SELECT COUNT(*) FROM ${TABLE}`));
const start = count();
log.push(`fixture: ${start} transaction(s) in the ledger`);

// ------------------------------------------------------------------ 1. renders
{
  const r = await req('/cash_on_hand.php');
  log.push(`GET /cash_on_hand.php -> ${r.status}, ${r.text.length} bytes`);
  if (r.status !== 200) problems.push(`cash_on_hand.php HTTP ${r.status}`);
  if (!/Logged in as:/.test(r.text)) problems.push('the side menu is missing');
  if (!/Current Balance|Balance/i.test(r.text)) problems.push('the balance panel is gone');
  if (!/Total (In|Out)|total_in|Total/i.test(r.text)) problems.push('the totals are gone');
}

// ------------------------------------------------------- 2. the filters round-trip
{
  const r = await req('/cash_on_hand.php?filter_type=in&start_date=2020-01-01&end_date=2020-12-31');
  log.push(`GET with filters -> ${r.status}, ${r.text.length} bytes`);
  if (r.status !== 200) problems.push(`filtered view HTTP ${r.status}`);
  if (!/2020-01-01/.test(r.text)) problems.push('the start-date filter was not kept');
  if (count() !== start) problems.push('reading with filters changed the ledger');
}

// -------------------------------------- 3. an invalid submission writes nothing
{
  // the handler requires a positive amount, so this must be rejected
  const before = count();
  const r = await req('/cash_on_hand.php', {
    add_transaction: '1', transaction_type: 'in', amount: '0',
    transaction_date: '2020-01-01', description: 'created by the verification run',
  });
  log.push(`POST add with amount 0 -> ${r.status} ${r.location || ''}`);
  if (count() !== before) problems.push(`an invalid transaction was written (${before} -> ${count()})`);
  else log.push('  the invalid transaction was rejected and nothing was written');

  const after = await req('/cash_on_hand.php');
  if (!/required fields and ensure amount/i.test(after.text)) problems.push('the validation message was not shown');
  else log.push('  the validation message is shown');
}

// --------------------------------------------- 4. delete of a missing id is safe
{
  const before = count();
  await req('/cash_on_hand.php', { delete_transaction: '1', transaction_id: 999999999 });
  if (count() !== before) problems.push('deleting a non-existent transaction changed the ledger');
  else log.push('deleting a non-existent id left the ledger alone');
}

// ------------------------------------------------------------- 5. no leftovers
{
  if (count() !== start) {
    problems.push(`the test left ${count() - start} row(s) behind`);
    db(`DELETE FROM ${TABLE} WHERE description = 'created by the verification run'`);
  } else {
    log.push('no rows left behind');
  }
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
