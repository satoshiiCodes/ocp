// Data-driven test: actions/endpoint split for the two spare-parts CRUD pages.
//
// Both keep a single "if POST" dispatcher, which moved into the actions file
// whole. The forms post back to the page, so the checks follow the database.
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8234';

const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const sessions = JSON.parse(php('sessions.php'));
const COOKIE = `PHPSESSID=${sessions[0].sid}`;
const db = (sql) => php('query.php', [sql]).trim();

const categoryId = db('SELECT id FROM spare_parts_categories ORDER BY id LIMIT 1');
const stamp = Date.now();

const CASES = [
  {
    page: 'spare_parts_suppliers.php', table: 'spare_parts_suppliers',
    nameCol: 'supplier_name', prefix: 'VerifySpSupplier',
    values: (n) => ({
      supplier_name: n, contact_person: 'Verify Contact', email: `vs${stamp}@example.test`,
      phone: '0900', address: 'Verify Address',
    }),
  },
  {
    page: 'spare_parts.php', table: 'spare_parts',
    nameCol: 'part_name', prefix: 'VerifyPart',
    values: (n) => ({
      part_number: `VP-${stamp}`, part_name: n, category_id: categoryId,
      unit_of_measure: 'pcs', min_stock_level: '5', description: 'created by the verification run',
    }),
  },
];

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
  if (err) problems.push(`${url}: PHP ${text.slice(err.index, err.index + 140).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ')}`);
  return { status: res.status, text, location: res.headers.get('location') };
}

log.push(`using category id ${categoryId} for the spare-part form`);

for (const c of CASES) {
  log.push(`--- ${c.page}`);
  const count = () => Number(db(`SELECT COUNT(*) FROM ${c.table}`));
  const start = count();
  const name = `${c.prefix} ${stamp}`;
  const values = c.values(name);

  // 1. renders
  {
    const r = await req(`/${c.page}`);
    if (r.status !== 200) problems.push(`${c.page}: HTTP ${r.status}`);
    if (!/Logged in as:/.test(r.text)) problems.push(`${c.page}: the side menu is missing`);
    else log.push('  renders with the side menu');
  }

  // 2. add
  {
    const r = await req(`/${c.page}`, values);
    if (count() !== start + 1) problems.push(`${c.page}: add did not insert (${start} -> ${count()})`);
    else log.push(`  add inserted a row (HTTP ${r.status})`);
    const listed = await req(`/${c.page}`);
    if (!listed.text.includes(name)) problems.push(`${c.page}: the new row is not listed`);
    else log.push('  the new row is listed');
  }

  const id = db(`SELECT id FROM ${c.table} WHERE ${c.nameCol} = '${name}'`);

  // 3. a duplicate is not inserted twice
  {
    await req(`/${c.page}`, values);
    if (count() !== start + 1) problems.push(`${c.page}: a duplicate was inserted (${count()})`);
    else log.push('  a duplicate is not inserted');
  }

  // 4. edit
  {
    const renamed = `${name} edited`;
    const r = await req(`/${c.page}`, { edit_id: id, ...c.values(renamed) });
    const stored = db(`SELECT ${c.nameCol} FROM ${c.table} WHERE id = ${id}`);
    if (stored !== renamed) problems.push(`${c.page}: edit did not persist ("${stored}")`);
    else log.push(`  edit persisted (HTTP ${r.status})`);
  }

  // 5. delete
  {
    const r = await req(`/${c.page}`, { delete_id: id });
    if (db(`SELECT COUNT(*) FROM ${c.table} WHERE id = ${id}`) !== '0') problems.push(`${c.page}: delete did not remove the row`);
    else log.push(`  delete removed the row (HTTP ${r.status})`);
  }

  // 6. no leftovers
  if (count() !== start) {
    problems.push(`${c.page}: the test left ${count() - start} row(s) behind`);
    db(`DELETE FROM ${c.table} WHERE ${c.nameCol} LIKE '${c.prefix} %'`);
  } else {
    log.push('  no rows left behind');
  }
}

server.kill();
for (const l of log) console.log('  ' + l);
console.log(`\nproblems: ${problems.length}`);
for (const p of problems) console.log('  X ' + p);
process.exit(problems.length ? 1 : 0);
