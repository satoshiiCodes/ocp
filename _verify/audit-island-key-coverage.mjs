// Cross-checks every island key the scripts read against the keys the pages publish.
//
//   node audit-island-key-coverage.mjs
//
// Two ways a script reads its page's island:
//     PAGE_DATA.someFlag
//     ocpRaw(DATA, "someKey")
// An earlier version only understood the first, so purchase_request_spare_parts could ask
// for `purchaseRequestSparePartsGuard2` and swalData/swalData2/swalData3 - none of which the
// page published - and the audit stayed quiet. The result on the page was a SweetAlert that
// never appeared.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const missing = [];
const handles = new Map();

for (const file of fs.readdirSync(path.join(ROOT, 'assets/js')).filter(f => f.endsWith('.js.php')).sort()) {
  const slug = file.replace(/\.js\.php$/, '');
  const src = fs.readFileSync(path.join(ROOT, 'assets/js', file), 'utf8');

  const keys = new Set();
  // shape 1: a hard-coded handle, PAGE_DATA.key
  const CONST = slug.replace(/[^A-Za-z0-9]+/g, '_').toUpperCase() + '_DATA';
  if (src.includes(CONST + '.')) {
    for (const m of src.matchAll(new RegExp(`${CONST}\\.([A-Za-z_][A-Za-z0-9_]*)`, 'g'))) keys.add(m[1]);
  }
  // shape 2: ocpRaw(HANDLE, "key") - any handle name
  const rawHandles = new Set([...src.matchAll(/ocpRaw\(\s*([A-Za-z_$][\w$]*)\s*,/g)].map(m => m[1]));
  for (const h of rawHandles) {
    for (const m of src.matchAll(new RegExp(`ocpRaw\\(\\s*${h}\\s*,\\s*["']([A-Za-z0-9_]+)["']`, 'g'))) keys.add(m[1]);
  }
  if (!keys.size) continue;
  handles.set(slug, { keys, raw: rawHandles.size > 0 });

  // what the page publishes
  const published = new Set();
  for (const cand of [`${slug}.php`, `api/${slug}-endpoint.php`, `actions/${slug}-actions.php`]) {
    const p = path.join(ROOT, cand);
    if (!fs.existsSync(p)) continue;
    for (const m of fs.readFileSync(p, 'utf8').matchAll(/\$__ocp_data\["([A-Za-z_][A-Za-z0-9_]*)"\]/g)) published.add(m[1]);
  }
  for (const k of keys) if (!published.has(k)) missing.push({ slug, key: k });
}

if (!missing.length) {
  console.log('OK: every island key read by a script is published by its page');
} else {
  console.log(`${missing.length} island key(s) read but never published:`);
  for (const m of missing) console.log(`  ${m.slug.padEnd(32)} ${m.key}`);
}
console.log(`\nscripts reading the island: ${handles.size}`);
