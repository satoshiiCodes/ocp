// Cross-checks the island flags the scripts read against the keys the pages publish.
//
//   node audit-flag-coverage.mjs
//
// A converted script tests PAGE_DATA.someFlag; if the page never publishes it, the test is
// undefined and the block silently disappears - the same bug wearing a new coat.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const missing = [];
const used = new Map();

for (const file of fs.readdirSync(path.join(ROOT, 'assets/js')).filter(f => f.endsWith('.js.php'))) {
  const slug = file.replace(/\.js\.php$/, '');
  const src = fs.readFileSync(path.join(ROOT, 'assets/js', file), 'utf8');
  const CONST = slug.replace(/[^A-Za-z0-9]+/g, '_').toUpperCase() + '_DATA';
  const keys = new Set();
  for (const m of src.matchAll(new RegExp(`${CONST}\\.([A-Za-z_][A-Za-z0-9_]*)`, 'g'))) keys.add(m[1]);
  if (!keys.size) continue;
  used.set(slug, keys);

  // what the page publishes: literal keys plus the flag block's own writes
  const parts = [];
  for (const cand of [`${slug}.php`, `api/${slug}-endpoint.php`, `actions/${slug}-actions.php`]) {
    const p = path.join(ROOT, cand);
    if (fs.existsSync(p)) parts.push(fs.readFileSync(p, 'utf8'));
  }
  for (const part of parts) {
    for (const m of part.matchAll(/\$__ocp_data\["([A-Za-z_][A-Za-z0-9_]*)"\]/g)) keys.delete(m[1]);
  }
  // keys the page's island comes from the endpoint's returned array are unpacked into
  // scope, but not into the island, so they still need an explicit $__ocp_data entry
  for (const k of keys) missing.push({ slug, key: k });
}

if (!missing.length) {
  console.log(`OK: every flag read by a script is published by its page`);
} else {
  console.log(`${missing.length} flag(s) read but never published:`);
  for (const m of missing) console.log(`  ${m.slug.padEnd(30)} ${m.key}`);
}
console.log(`\nscripts reading a named island handle: ${used.size}`);
