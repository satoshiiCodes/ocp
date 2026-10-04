// Parses every generated JavaScript file.
//
// Plain .js files are parsed directly. For a .js.php file the PHP islands are
// replaced with `0`, which is valid both as an expression and inside a string,
// so the surrounding JavaScript can be parsed even with PHP present.
//
// A `const`/`let` declared in two mutually exclusive PHP branches is accepted:
// only one branch is ever rendered.
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const ROOT = 'E:\\laragon\\www\\OCP';
const dir = path.join(ROOT, 'assets/js');
const SEG_RE = /<\?php[\s\S]*?\?>|<\?=[\s\S]*?\?>/g;
const failures = [];
let plain = 0, server = 0;

function walk(d) {
  for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    const p = path.join(d, e.name);
    if (e.isDirectory()) { walk(p); continue; }
    if (!e.name.endsWith('.js') && !e.name.endsWith('.js.php')) continue;
    const code = fs.readFileSync(p, 'utf8');
    const rel = path.relative(ROOT, p);

    if (e.name.endsWith('.js.php')) {
      server++;
      try {
        new vm.Script(code.replace(SEG_RE, '0'), { filename: rel });
      } catch (err) {
        const msg = err.message.split('\n')[0];
        const redecl = /Identifier '([^']+)' has already been declared/.test(msg);
        const hasBranches = /<\?php\s*(elseif|else)\b/.test(code);
        if (!(redecl && hasBranches)) failures.push(`${rel}: ${msg}`);
      }
      const opens = (code.match(/<\?php|<\?=/g) || []).length;
      const closes = (code.match(/\?>/g) || []).length;
      if (opens !== closes) failures.push(`${rel}: unbalanced PHP tags (${opens}/${closes})`);
      if (!code.trimStart().startsWith('/*')) failures.push(`${rel}: does not start with its header comment`);
      if (!/^<\?php$/m.test(code)) failures.push(`${rel}: no PHP bootstrap`);
    } else {
      plain++;
      try { new vm.Script(code, { filename: rel }); }
      catch (err) { failures.push(`${rel}: ${err.message.split('\n')[0]}`); }
      if (/<\?php|<\?=/.test(code)) failures.push(`${rel}: plain JS contains PHP`);
    }
  }
}
walk(dir);

console.log(`plain .js parsed                  : ${plain}`);
console.log(`server-rendered .js.php parsed    : ${server}`);
console.log(`failures: ${failures.length}`);
for (const f of failures) console.log('  X ' + f);
process.exit(failures.length ? 1 : 0);
