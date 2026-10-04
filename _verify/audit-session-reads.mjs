// Compares the session keys each page READ before the restructure with the keys it and
// its actions/endpoint files read now.
//
//   node audit-session-reads.mjs
//
// A dropped session read is easy to miss and quietly changes what the page shows: it is
// how pr_spare_view_routing lost the swal_data its routing actions leave behind.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const pages = fs.readdirSync(ROOT).filter(f => /\.php$/.test(f) && !/_pdf\.php$|_PDF\.php$/.test(f));

const keysReadIn = (src) => {
  const keys = new Set();
  // $_SESSION['key'] read (not an assignment to it)
  for (const m of src.matchAll(/\$_SESSION\['([a-z_][a-z0-9_]*)'\]/g)) {
    // skip "= ..." immediately after
    const after = src.slice(m.index + m[0].length, m.index + m[0].length + 3);
    if (/^\s*=[^=]/.test(after)) continue;
    keys.add(m[1]);
  }
  for (const m of src.matchAll(/isset\(\$_SESSION\['([a-z_][a-z0-9_]*)'\]\)/g)) keys.add(m[1]);
  return keys;
};

const findings = [];
for (const page of pages) {
  const slug = page.replace(/\.php$/, '');
  const backup = path.join(ROOT, '_restructure_backup', page);
  if (!fs.existsSync(backup)) continue;

  const before = keysReadIn(fs.readFileSync(backup, 'utf8'));
  if (!before.size) continue;

  let now = fs.readFileSync(path.join(ROOT, page), 'utf8');
  for (const cand of [`actions/${slug}-actions.php`, `api/${slug}-endpoint.php`]) {
    const p = path.join(ROOT, cand);
    if (fs.existsSync(p)) now += '\n' + fs.readFileSync(p, 'utf8');
  }
  const after = keysReadIn(now);

  const lost = [...before].filter(k => !after.has(k));
  if (lost.length) findings.push({ page, lost });
}

if (!findings.length) {
  console.log('OK: every session key a page read before is still read somewhere');
} else {
  console.log(`${findings.length} page(s) no longer read a session key they used to:`);
  for (const f of findings) console.log(`  ${f.page.padEnd(36)} ${f.lost.join(', ')}`);
}
