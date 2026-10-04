// Snapshots rows before a script overwrites them, and puts them back afterwards.
//
//   node _verify/row-snapshot.mjs save <table> <id> ...      -> appends to _verify/row-snapshot.json
//   node _verify/row-snapshot.mjs restore                     -> restores everything saved
//
// The modal-edit tests have to write a real value to prove an edit saves. Doing that
// without recording the original is how three rows were left holding probe text during
// the item_names investigation: the values could not be put back because they had not been
// written down first. This makes the snapshot the first step rather than an afterthought.
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.dirname(HERE);
const STORE = path.join(HERE, 'row-snapshot.json');

const php = (f, a = []) => execFileSync('php', [path.join(HERE, f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const one = (sql) => php('query.php', [sql]).trim();

const [cmd, ...rest] = process.argv.slice(2);

if (cmd === 'save') {
  const [table, ...ids] = rest;
  if (!table || !ids.length) { console.error('usage: save <table> <id> [id...]'); process.exit(2); }
  const store = fs.existsSync(STORE) ? JSON.parse(fs.readFileSync(STORE, 'utf8')) : [];
  for (const id of ids) {
    // a JSON object of the row, read through PHP so the quoting is PDO's problem
    const raw = php('row.php', [table, id]);
    const row = JSON.parse(raw || 'null');
    if (!row) { console.log(`  ${table} ${id}: no such row`); continue; }
    store.push({ table, id: String(id), row });
    console.log(`  saved ${table} ${id}: ${JSON.stringify(row).slice(0, 120)}`);
  }
  fs.writeFileSync(STORE, JSON.stringify(store, null, 2));
  console.log(`  ${store.length} row(s) in ${path.basename(STORE)}`);
} else if (cmd === 'restore') {
  if (!fs.existsSync(STORE)) { console.log('  nothing saved'); process.exit(0); }
  const store = JSON.parse(fs.readFileSync(STORE, 'utf8'));
  let n = 0;
  for (const entry of store) {
    const cols = Object.keys(entry.row).filter(c => c !== 'id');
    const sets = cols.map(c => {
      const v = entry.row[c];
      if (v === null || v === undefined) return `\`${c}\` = NULL`;
      return `\`${c}\` = '${String(v).replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
    }).join(', ');
    one(`UPDATE \`${entry.table}\` SET ${sets} WHERE id = ${Number(entry.id)}`);
    n++;
  }
  fs.rmSync(STORE, { force: true });
  console.log(`  restored ${n} row(s)`);
} else {
  console.error('usage: node row-snapshot.mjs save <table> <id>... | restore');
  process.exit(2);
}
