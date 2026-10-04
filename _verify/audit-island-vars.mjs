// Audits the data islands for values that are never defined - the mistake that left
// pr_spare_view_routing's island reading an undefined $technician_formatted.
//
//   node audit-island-vars.mjs
//
// It flags a $__ocp_data[...] entry whose right-hand variable is neither assigned
// earlier in the page (or in its endpoint / actions / partials) nor a superglobal.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const pages = fs.readdirSync(ROOT).filter(f => /\.php$/.test(f) && !/_pdf\.php$|_PDF\.php$/.test(f));

const findings = [];
for (const page of pages) {
  const slug = page.replace(/\.php$/, '');
  const src = fs.readFileSync(path.join(ROOT, page), 'utf8');
  const island = /window\.OCP_PAGE_|ocp_page_data\(/.test(src) || /\$__ocp_data/.test(src);
  if (!island) continue;

  // everything the page and its companions assign or receive
  const parts = [src];
  for (const cand of [`api/${slug}-endpoint.php`, `actions/${slug}-actions.php`, `includes/${slug}-functions.php`]) {
    const p = path.join(ROOT, cand);
    if (fs.existsSync(p)) parts.push(fs.readFileSync(p, 'utf8'));
  }
  const partialDir = path.join(ROOT, 'includes/partials', slug);
  if (fs.existsSync(partialDir)) for (const f of fs.readdirSync(partialDir)) parts.push(fs.readFileSync(path.join(partialDir, f), 'utf8'));
  const all = parts.join('\n');

  const defined = new Set();
  // assignments, foreach targets, function parameters, catch variables, list() targets
  for (const m of all.matchAll(/\$([a-z_][a-z0-9_]*)\s*(?:\[[^\]]*\])?\s*=[^=]/g)) defined.add(m[1]);
  for (const m of all.matchAll(/foreach\s*\([^)]*?\$([a-z_][a-z0-9_]*)\s+as\s+(?:&?\$([a-z_][a-z0-9_]*))?/g)) {
    if (m[1]) defined.add(m[1]);
    if (m[2]) defined.add(m[2]);
  }
  for (const m of all.matchAll(/function\s+\w+\s*\(([^)]*)\)/g)) {
    for (const p of m[1].matchAll(/\$([a-z_][a-z0-9_]*)/g)) defined.add(p[1]);
  }
  for (const m of all.matchAll(/catch\s*\(\s*\w+\s+\$([a-z_][a-z0-9_]*)/g)) defined.add(m[1]);
  for (const m of all.matchAll(/\bas\s+\$([a-z_][a-z0-9_]*)/g)) defined.add(m[1]);
  // the page's unpacking loop receives whatever the endpoint returns
  for (const m of all.matchAll(/return\s*\[([\s\S]{0,4000}?)\];/g)) {
    for (const k of m[1].matchAll(/'([a-z_][a-z0-9_]*)'\s*=>/g)) defined.add(k[1]);
  }

  const SUPERGLOBALS = new Set(['_SESSION', '_POST', '_GET', '_SERVER', '_COOKIE', '_REQUEST', 'GLOBALS', 'pdo']);
  // Values the page only reads when it is set - such as attendance_error, which the
  // unpacking loop skips when the endpoint had nothing to report - are guarded, so they
  // are not looked for here. A variable assigned with ?? also counts as safe.
  const guardedInIsland = new Set();
  for (const m of src.matchAll(/\/\*\s*(\w+)\s*=\s*\$([a-z_][a-z0-9_]*) \[guarded\]/g)) guardedInIsland.add(m[2]);
  for (const m of src.matchAll(/\$__ocp_data\["[^"]+"\]\s*=\s*\$([a-z_][a-z0-9_]*)\s*\?\?/g)) defined.add(m[1]);

  for (const m of src.matchAll(/\$__ocp_data\["[^"]+"\]\s*=\s*\$([a-z_][a-z0-9_]*)\s*[;,]/g)) {
    const name = m[1];
    if (SUPERGLOBALS.has(name) || defined.has(name) || guardedInIsland.has(name)) continue;
    if (/^_+$/.test(name)) continue;      // $_ and friends
    findings.push({ page, name });
  }
}

if (!findings.length) {
  console.log('OK: every island value comes from a variable that is assigned somewhere');
} else {
  console.log(`${findings.length} island value(s) read an undefined variable:`);
  for (const f of findings) console.log(`  ${f.page.padEnd(36)} $${f.name}`);
}
