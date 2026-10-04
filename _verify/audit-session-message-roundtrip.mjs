// Finds pages whose handlers report by redirecting through the session, but that never
// READ the session on a plain load.
//
//   node audit-session-message-roundtrip.mjs
//
// The original pages read $_SESSION['swal_data'] (or swal_message / alert) in their own
// preamble. If that read moved into the actions file, it only runs on a POST - so a
// handler that stores a message and redirects writes it, and the page it lands on never
// shows it.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const KEYS = ['swal_data', 'swal_message', 'swal_message_type', 'alert', 'sweetalert', 'success_message'];
const pages = fs.readdirSync(ROOT).filter(f => /\.php$/.test(f) && !/_pdf\.php$|_PDF\.php$/.test(f));

const broken = [];
const ok = [];
for (const page of pages) {
  const slug = page.replace(/\.php$/, '');
  const backup = path.join(ROOT, '_restructure_backup', page);
  if (!fs.existsSync(backup)) continue;
  const before = fs.readFileSync(backup, 'utf8');

  // did the original page READ one of these from the session?
  const readKeys = new Set();
  for (const k of KEYS) {
    const re = new RegExp(`\\$_SESSION\\['${k}'\\]`, 'g');
    for (const m of before.matchAll(re)) {
      const after = before.slice(m.index + m[0].length, m.index + m[0].length + 3);
      if (!/^\s*=[^=]/.test(after)) readKeys.add(k);      // a read, not an assignment
    }
    if (new RegExp(`isset\\(\\$_SESSION\\['${k}'\\]\\)`).test(before)) readKeys.add(k);
  }
  if (!readKeys.size) continue;

  // where does the page read them now?
  const pageSrc = fs.readFileSync(path.join(ROOT, page), 'utf8');
  const actionsPath = path.join(ROOT, 'actions', `${slug}-actions.php`);
  const actionsSrc = fs.existsSync(actionsPath) ? fs.readFileSync(actionsPath, 'utf8') : '';
  const endpointPath = path.join(ROOT, 'api', `${slug}-endpoint.php`);
  const endpointSrc = fs.existsSync(endpointPath) ? fs.readFileSync(endpointPath, 'utf8') : '';

  const readIn = (src, k) => new RegExp(`isset\\(\\$_SESSION\\['${k}'\\]\\)`).test(src)
    || new RegExp(`\\$_SESSION\\['${k}'\\]`).test(src.replace(new RegExp(`\\$_SESSION\\['${k}'\\]\\s*=[^=]`, 'g'), ''));

  const missing = [...readKeys].filter(k => !readIn(pageSrc, k) && !readIn(endpointSrc, k) && !readIn(actionsSrc, k));
  const onlyInActions = [...readKeys].filter(k => !readIn(pageSrc, k) && !readIn(endpointSrc, k) && readIn(actionsSrc, k));

  if (missing.length) broken.push({ page, keys: missing, why: 'read nowhere' });
  else if (onlyInActions.length) broken.push({ page, keys: onlyInActions, why: 'read only in the actions file (POST only)' });
  else ok.push(page);
}

if (!broken.length) {
  console.log('OK: every page that reports through the session also reads it on a plain load');
} else {
  console.log(`${broken.length} page(s) may lose a redirected message:`);
  for (const b of broken) console.log(`  ${b.page.padEnd(34)} ${b.keys.join(', ')}  (${b.why})`);
}
console.log(`\nchecked ${broken.length + ok.length} page(s) with a session message`);
