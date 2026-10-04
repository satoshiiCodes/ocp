// Fails if any lookup that must return the NEWEST row of a second-precision timestamp table
// orders by that timestamp alone.
//
//   node audit-timestamp-tiebreak.mjs
//
// pr_routing.created_at is a TIMESTAMP, which is stored to the second. A purchase request
// moved twice inside one second - exactly what happens when a user clicks through the routing
// actions - gets two rows sharing that value, and `ORDER BY created_at DESC LIMIT 1` then
// returns whichever the engine likes. In practice it returned the OLDER row, so the page was
// told the request still sat on a stage it had already left: approve_warehouse wrote
// 'purchasing', the next action read 'warehouse', and every approval after the first refused
// itself with "You are not authorized to perform this action."
//
// The fix is to order by created_at DESC, id DESC. This audit looks for the pattern and for
// the same shape in the other routing-ish tables.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const SKIP = /\\vendor\\|\\_restructure_backup\\|\\_verify\\|\\_baseline\\/;

// tables whose rows are appended and whose newest row is the current state
const TABLES = ['pr_routing', 'spare_parts_pr_routing', 'pr_routing_history'];

const files = [];
const walk = (dir) => {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (SKIP.test(p)) continue;
    if (e.isDirectory()) walk(p);
    else if (/\.php$/.test(e.name)) files.push(p);
  }
};
walk(ROOT);

const problems = [];
let scanned = 0;

for (const file of files) {
  const src = fs.readFileSync(file, 'utf8');
  const rel = path.relative(ROOT, file);
  // look at each string literal / statement chunk that selects from one of the tables
  for (const table of TABLES) {
    const re = new RegExp(`FROM\\s+${table}\\b[\\s\\S]{0,400}?ORDER BY\\s+([^"'\\n;]+)`, 'gi');
    for (const m of src.matchAll(re)) {
      const order = m[1].replace(/\s+/g, ' ').trim();
      scanned++;
      // only the newest-row lookups matter: those with LIMIT 1 and no other discriminator
      const hasLimit1 = /LIMIT\s+1/i.test(src.slice(m.index, m.index + m[0].length + 60));
      if (!hasLimit1) continue;
      if (/created_at\s+DESC/i.test(order) && !/id\s+DESC/i.test(order)) {
        const line = src.slice(0, m.index).split('\n').length;
        problems.push(`${rel}:${line} orders ${table} by "${order}" with LIMIT 1 - no id tie-break, so it may return a stale row`);
      }
    }
  }
}

if (!problems.length) {
  console.log(`OK: all ${scanned} newest-row lookups break timestamp ties by id`);
} else {
  console.log(`${problems.length} newest-row lookup(s) can return a stale row:`);
  for (const p of problems) console.log(`    ${p}`);
  console.log('\n  created_at is stored to the second; add ", id DESC" so the newest row is unambiguous.');
}
process.exit(problems.length ? 1 : 0);
