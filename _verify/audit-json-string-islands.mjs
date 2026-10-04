// Finds island values that arrive as JSON *strings* but are dereferenced as objects.
//
//   node audit-json-string-islands.mjs
//
// A page builds some island members by capturing a fragment (ob_start + include, or
// ocp_capture). The fragment prints JSON text, so the member is a string. Inline, the page
// used to hand the script a real object, so scripts written then do
// `PURCHASE_REQUEST_DATA.items.filter(...)` or `.employees.length` - on a string that is
// undefined, which throws and takes the rest of the script down with it.
//
// Two real faults came from this: purchase_request's item list (empty dropdowns, dead View
// Details) and upload_attendance's missing-employees block (which killed Late Time).
//
// The check is deliberately narrow: it looks for an island member that the page builds by
// capturing a fragment, and a line that reaches into it - `MEMBER` followed by `.` or `[` -
// with no JSON.parse or typeof test within the few lines around it. That is precisely the
// shape of both faults, and it keeps the false positives out.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const findings = [];

for (const file of fs.readdirSync(path.join(ROOT, 'assets/js')).filter(f => f.endsWith('.js.php')).sort()) {
  const slug = file.replace(/\.js\.php$/, '');
  const src = fs.readFileSync(path.join(ROOT, 'assets/js', file), 'utf8');
  const CONST = slug.replace(/[^A-Za-z0-9]+/g, '_').toUpperCase() + '_DATA';
  if (!src.includes(CONST + '.')) continue;

  // island members the page builds through a captured fragment -> JSON text
  const captured = new Set();
  for (const cand of [`${slug}.php`, `api/${slug}-endpoint.php`]) {
    const p = path.join(ROOT, cand);
    if (!fs.existsSync(p)) continue;
    const page = fs.readFileSync(p, 'utf8');
    for (const m of page.matchAll(/\$__ocp_data\["([A-Za-z_][A-Za-z0-9_]*)"\]\s*=\s*ob_get_clean\(\)/g)) captured.add(m[1]);
    for (const m of page.matchAll(/\$__ocp_data\["([A-Za-z_][A-Za-z0-9_]*)"\]\s*=\s*ocp_capture\(/g)) captured.add(m[1]);
  }
  if (!captured.size) continue;

  const lines = src.split(/\r?\n/);
  const isGuarded = (lineNo) => {
    // look a little either side: a multi-line declaration puts the parse a line or two
    // after the fetch, and the fetch itself sits under a comment explaining it.
    const window = lines.slice(Math.max(0, lineNo - 4), lineNo + 5).join('\n');
    if (/JSON\.parse\s*\(/.test(window)) return true;
    if (/typeof\s+[A-Za-z_$][\w$]*\s*(===|!==)\s*['"]string['"]/.test(window)) return true;
    return false;
  };

  // shape 1: the member is reached into directly -  MEMBER.items.filter(...)
  for (const m of src.matchAll(new RegExp(`${CONST}\\.([A-Za-z_][A-Za-z0-9_]*)\\s*(?=[.\\[])`, 'g'))) {
    if (!captured.has(m[1])) continue;
    const lineNo = src.slice(0, m.index).split('\n').length;
    if (isGuarded(lineNo)) continue;
    findings.push({ file, line: lineNo, key: m[1], how: 'reached into directly', text: (lines[lineNo - 1] || '').trim().slice(0, 88) });
  }

  // shape 2: the member is assigned to a local and that local is reached into -
  // const itemsData = DATA.items;  ...  itemsData.filter(...)
  for (const m of src.matchAll(new RegExp(`(?:var|let|const)\\s+([A-Za-z_$][\\w$]*)\\s*=\\s*[^;\\n]*?\\b${CONST}\\.([A-Za-z_][A-Za-z0-9_]*)`, 'g'))) {
    const [, local, key] = m;
    if (!captured.has(key)) continue;
    const assignLine = src.slice(0, m.index).split('\n').length;
    if (isGuarded(assignLine)) continue;
    // is that local ever used as an object?
    const reach = new RegExp(`\\b${local}\\s*(?=[.\\[])`);
    const used = reach.test(src.slice(m.index + m[0].length)) || reach.test(src.slice(0, m.index));
    if (!used) continue;
    findings.push({ file, line: assignLine, key, how: `assigned to ${local}, then used as an object`, text: (lines[assignLine - 1] || '').trim().slice(0, 88) });
  }
}

if (!findings.length) {
  console.log('OK: no script dereferences a captured-fragment island member as an object');
} else {
  console.log(`${findings.length} place(s) where a JSON string may be treated as an object:`);
  for (const f of findings) console.log(`  ${f.file.padEnd(32)} ${String(f.line).padStart(5)}  [${f.key}] ${f.how}`);
}
