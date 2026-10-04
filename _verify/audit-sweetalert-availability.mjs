// Finds pages whose SweetAlert calls cannot work because the library is never loaded.
//
//   node audit-sweetalert-availability.mjs
//
// includes/top_bar.php wires the Logout link with Swal.fire, and assets/js/app.js does the
// same for pages without the top bar. Eight pages included one of those without loading the
// library, so on them `Swal` was undefined and clicking Logout threw before it could redirect
// - the button did nothing at all.
//
// The check is made against the SERVED page, not the source: includes/sweetalert.php loads
// the library with a script it injects, so a page can be correct without the source naming
// sweetalert2 anywhere.
import fs from 'node:fs';
import path from 'node:path';
import { spawn, execFileSync } from 'node:child_process';

const ROOT = 'E:\\laragon\\www\\OCP';
const PORT = '8590';
const php = (f, a = []) => execFileSync('php', [path.join(ROOT, '_verify', f), ...a], { encoding: 'utf8' }).replace(/^\uFEFF/, '');
const SID = JSON.parse(php('sessions.php'))[0].sid;

const pages = fs.readdirSync(ROOT).filter(f => f.endsWith('.php') && !/_pdf\.php$/.test(f));
const app = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', '.'], { cwd: ROOT, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 2200));

const broken = [];
const checked = [];
for (const page of pages) {
  const src = fs.readFileSync(path.join(ROOT, page), 'utf8');
  const slug = page.replace(/\.php$/, '');
  let scriptUsesSwal = false;
  for (const cand of [`assets/js/${slug}.js.php`, `assets/js/${slug}.js`]) {
    const p = path.join(ROOT, cand);
    if (fs.existsSync(p) && /Swal\.fire/.test(fs.readFileSync(p, 'utf8'))) scriptUsesSwal = true;
  }
  const includesTopBar = /includes\/top_bar\.php/.test(src);
  const loadsApp = /assets\/js\/app\.js/.test(src);
  const includesLoader = /includes\/sweetalert\.php/.test(src);
  // A page needs the library only if something on it actually reaches a Swal call. app.js
  // wires the logout link when one is present, so a page that loads app.js but renders no
  // logoutLink - index.php, the login screen - needs nothing.
  const topBarWiresLogout = includesTopBar && /logoutLink/.test(fs.readFileSync(path.join(ROOT, 'includes', 'top_bar.php'), 'utf8'));
  if (!topBarWiresLogout && !scriptUsesSwal && !/Swal\.fire/.test(src)) continue;

  const res = await fetch(`http://127.0.0.1:${PORT}/${page}`, { headers: { Cookie: `PHPSESSID=${SID}` }, redirect: 'manual' });
  const html = Buffer.from(await res.arrayBuffer()).toString('utf8');
  // and that page must really render the element the handler is attached to
  if (loadsApp && !topBarWiresLogout && !/id="logoutLink"/.test(html)) continue;

  // The library arrives one of three ways, and the third is easy to miss:
  //   the page links the CDN itself
  //   the page includes includes/sweetalert.php
  //   the page includes includes/top_bar.php, WHICH includes includes/sweetalert.php
  const hasLibrary = /sweetalert2(@|\/|\.min)/.test(html)
    || includesLoader
    || (includesTopBar && /^\s*require_once __DIR__ \. '\/sweetalert\.php';/m.test(fs.readFileSync(path.join(ROOT, 'includes', 'top_bar.php'), 'utf8')));
  checked.push(page);
  if (!hasLibrary) {
    broken.push({ page, why: [includesTopBar && 'top_bar', loadsApp && 'app.js', scriptUsesSwal && 'its script'].filter(Boolean).join(', ') });
  }
  void res;
}
app.kill();

if (!broken.length) {
  console.log(`OK: all ${checked.length} pages that use SweetAlert load it`);
} else {
  console.log(`${broken.length} of ${checked.length} pages use Swal but never load sweetalert2:`);
  for (const b of broken) console.log(`    ${b.page.padEnd(34)} (wired by ${b.why})`);
  console.log('\n  on these pages Swal is undefined, so the handler throws on its first line.');
}
process.exit(broken.length ? 1 : 0);
