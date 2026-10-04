// Cross-checks the island keys each script reads against the keys its page publishes.
//
//   node audit-island-key-names.mjs
//
// A script reading PAGE_DATA.foo when the page publishes PAGE_DATA.fooBar gets undefined.
// The alert still fires, with no title and no text - which is what "the sweet alert only
// shows OK" looks like.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';

const published = (slug) => {
  const keys = new Set();
  for (const cand of [`${slug}.php`, `api/${slug}-endpoint.php`, `actions/${slug}-actions.php`]) {
    const p = path.join(ROOT, cand);
    if (!fs.existsSync(p)) continue;
    for (const m of fs.readFileSync(p, 'utf8').matchAll(/\$__ocp_data\["([A-Za-z_][A-Za-z0-9_]*)"\]/g)) keys.add(m[1]);
  }
  return keys;
};

const findings = [];
for (const file of fs.readdirSync(path.join(ROOT, 'assets/js')).filter(f => f.endsWith('.js.php')).sort()) {
  const slug = file.replace(/\.js\.php$/, '');
  const src = fs.readFileSync(path.join(ROOT, 'assets/js', file), 'utf8');
  const CONST = slug.replace(/[^A-Za-z0-9]+/g, '_').toUpperCase() + '_DATA';
  if (!src.includes(CONST + '.')) continue;

  const keys = published(slug);
  const read = new Set([...src.matchAll(new RegExp(`${CONST}\\.([A-Za-z_][A-Za-z0-9_]*)`, 'g'))].map(m => m[1]));
  for (const k of read) {
    if (!keys.has(k)) findings.push({ slug, key: k, near: [...keys].filter(x => x.toLowerCase().includes(k.toLowerCase().slice(0, 5))) });
  }
}

if (!findings.length) {
  console.log('OK: every island key a script reads is one its page publishes');
} else {
  console.log(`${findings.length} key(s) read but never published:`);
  for (const f of findings) {
    console.log(`  ${f.slug.padEnd(30)} ${f.key}${f.near.length ? `   (page has: ${f.near.join(', ')})` : ''}`);
  }
}
