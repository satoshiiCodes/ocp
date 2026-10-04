// Finds data islands that only render for some submissions.
//
//   node audit-island-nesting.mjs
//
// An island wrapped in `if (isset($something)): ... endif;` is printed only when that
// branch runs. The script it feeds is loaded on EVERY load of the page, so on all the other
// loads it finds no island: flags are undefined, messages are never shown. On
// employee_registration.php the island sat inside the deductions modal's guard, so only a
// deductions submission ever produced one - which is why the page's sweetalerts never
// appeared.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const findings = [];

for (const f of fs.readdirSync(ROOT).filter(x => x.endsWith('.php')).sort()) {
  const src = fs.readFileSync(path.join(ROOT, f), 'utf8');
  if (!/ocp_page_data\s*\(/.test(src)) continue;
  const lines = src.split(/\r?\n/);

  const stack = [];
  for (let i = 0; i < lines.length; i++) {
    for (const m of lines[i].matchAll(/<\?php\s+(if|elseif|else|endif)\b/g)) {
      const kw = m[1];
      if (kw === 'if') stack.push({ line: i + 1, text: lines[i].trim().slice(0, 70) });
      else if (kw === 'endif') stack.pop();
      else if (kw === 'else' || kw === 'elseif') {
        // a branch other than the first: still conditional
      }
    }
    if (/ocp_page_data\s*\(/.test(lines[i]) && stack.length) {
      findings.push({ file: f, line: i + 1, open: stack[stack.length - 1] });
    }
  }
}

if (!findings.length) {
  console.log('OK: every data island renders on every load of its page');
} else {
  console.log(`${findings.length} data island(s) nested inside a conditional:`);
  for (const x of findings) {
    console.log(`  ${x.file.padEnd(34)} island at ${String(x.line).padStart(5)}  inside the guard at line ${x.open.line}: ${x.open.text}`);
  }
  console.log('\nthe script is loaded on every load, so on the other branches it gets no island');
}
