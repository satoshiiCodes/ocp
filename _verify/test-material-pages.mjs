// Data-driven test: actions/endpoint split for issue_materials and
// spare_parts_inventory.
//
// Both are "one POST dispatcher" pages whose forms post back to the page. The
// action that inserts rows is exercised on a throw-away part, and everything it
// creates is removed again; the row counts are compared before and after.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8241';

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

/* --------------------------------------------------------- the pages render */
for (const page of ['issue_materials.php', 'spare_parts_inventory.php']) {
  const r = await req(`/${page}`);
  if (r.status !== 200) problems.push(`${page}: HTTP ${r.status}`);
  if (!/Logged in as:/.test(r.text)) problems.push(`${page}: the side menu is missing`);
  else log.push(`${page}: renders (${r.text.length} bytes)`);
}

/* -------------------------------------------------- issue_materials: no part */
{
  log.push('--- issue_materials.php: an issuance with no part chosen');
  const issued = () => Number(db('SELECT COUNT(*) FROM employee_materials_issued'));
  const movements = () => Number(db('SELECT COUNT(*) FROM spare_parts_movements'));
  const before = { issued: issued(), movements: movements() };

  const employeeId = db('SELECT id FROM employee WHERE status = \'active\' ORDER BY id LIMIT 1');
  const r = await req('/issue_materials.php', {
    action: 'issue_to_employee',
    employee_id: employeeId,
    purpose: 'created by the verification run',
    date_issued: new Date().toISOString().slice(0, 10),
  });
  log.push(`  POST with no part -> ${r.status}, ${r.text.length} bytes`);
  if (r.status !== 200) problems.push(`issue_materials: a rejected issuance should re-render, got ${r.status}`);
  if (issued() !== before.issued || movements() !== before.movements) {
    problems.push(`issue_materials: the rejected issuance changed data (issued ${before.issued}->${issued()}, movements ${before.movements}->${movements()})`);
  } else {
    log.push('  nothing was written');
  }
}

/* ------------------------------------------ spare_parts_inventory: initial_part */
{
  log.push('--- spare_parts_inventory.php: an initial stock entry');
  const parts = () => Number(db('SELECT COUNT(*) FROM spare_parts'));
  const movements = () => Number(db('SELECT COUNT(*) FROM spare_parts_movements'));
  const batches = () => Number(db('SELECT COUNT(*) FROM spare_parts_batches'));

  const stamp = Date.now();
  const before = { parts: parts(), movements: movements(), batches: batches() };

  // a throw-away part to receive the stock
  const categoryId = db('SELECT id FROM spare_parts_categories ORDER BY id LIMIT 1');
  db(`INSERT INTO spare_parts (part_number, part_name, category_id, unit_of_measure, min_stock_level)
      VALUES ('VERIFY-${stamp}', 'VerifyPart ${stamp}', ${categoryId}, 'pcs', 0)`);
  const partId = db(`SELECT id FROM spare_parts WHERE part_number = 'VERIFY-${stamp}'`);
  log.push(`  fixture part id ${partId}`);

  const r = await req('/spare_parts_inventory.php', {
    action: 'initial_part', part_id: partId, quantity: '7', price_per_unit: '11.50',
    date_added: new Date().toISOString().slice(0, 10),
  });
  log.push(`  POST initial_part -> ${r.status}`);
  if (movements() <= before.movements) problems.push('spare_parts_inventory: initial_part wrote no movement');
  else log.push('  a movement was recorded');
  if (batches() <= before.batches) problems.push('spare_parts_inventory: initial_part wrote no batch');
  else log.push('  a batch was recorded');

  // the page should now show the part it just stocked
  const listed = await req('/spare_parts_inventory.php');
  if (!listed.text.includes(`VerifyPart ${stamp}`)) problems.push('spare_parts_inventory: the new stock is not listed');
  else log.push('  the new stock is listed');

  // clean up everything the test created
  db(`DELETE FROM spare_parts_movements WHERE part_id = ${partId}`);
  db(`DELETE FROM spare_parts_batches WHERE part_id = ${partId}`);
  db(`DELETE FROM spare_parts_inventory WHERE part_id = ${partId}`);
  db(`DELETE FROM spare_parts WHERE id = ${partId}`);

  if (parts() !== before.parts) problems.push(`spare_parts_inventory: the test left ${parts() - before.parts} part(s) behind`);
  else if (movements() !== before.movements) problems.push('spare_parts_inventory: the test left movements behind');
  else if (batches() !== before.batches) problems.push('spare_parts_inventory: the test left batches behind');
  else log.push('  no rows left behind');
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
