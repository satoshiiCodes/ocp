// Audits the server-rendered scripts for the mistake behind the projects.php bugs:
// echoing an ISLAND value from PHP.
//
//   node audit-script-island-echoes.mjs
//
// A script under assets/js/*.js.php is fetched as its own request, where the page's
// $__ocp_data does not exist. So `ocp_island_get($__ocp_data, 'x')` is always null
// there, and any markup or message built from it comes out empty. Values must be read
// from window.OCP_PAGE_<SLUG> in the browser instead.
//
// Two shapes are reported:
//   - a PHP conditional on a PAGE variable (!empty($success_message)), which is false in
//     that request, so the block it guards is dropped;
//   - an island value echoed into a position where it becomes output (markup, a message),
//     rather than into a literal that is harmless when empty.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';

// A key that ends up as visible output is one echoed outside of a PHP string-escaping
// helper. 'swalDataIcon' and friends are only ever used after a client-side check, so a
// null answer is harmless there; markup and message text are not.
const OUTPUT_BUILDERS = /fieldHtml|innerHTML|OptionsHtml|optionsHtml|OptionsJs|\+=\s*<\?php echo ocp_island_get/;

const phpVarsOfScript = (constName) => constName;   // documentation aid

const findings = [];
for (const file of fs.readdirSync(path.join(ROOT, 'assets/js')).filter(f => f.endsWith('.js.php'))) {
  const slug = file.replace(/\.js\.php$/, '');
  const src = fs.readFileSync(path.join(ROOT, 'assets/js', file), 'utf8');
  const lines = src.split(/\r?\n/);

  lines.forEach((line, i) => {
    // PHP conditional guarding on a page variable that cannot be in scope here
    const cond = /<\?php\s+if\s*\(\s*(?:!empty\(|isset\()?\$([a-z_][a-z0-9_]*)/i.exec(line);
    if (cond && !['__ocp_data', '__ocp_dir', '__ocp_i', '__ocp_parent'].includes(cond[1])) {
      findings.push({ file, line: i + 1, kind: 'PHP conditional on a page variable', detail: `$${cond[1]}`, text: line.trim().slice(0, 90) });
    }
    // an island value echoed into markup
    if (OUTPUT_BUILDERS.test(line) && /ocp_island_get\(/.test(line)) {
      findings.push({ file, line: i + 1, kind: 'island value echoed into markup', detail: '', text: line.trim().slice(0, 90) });
    }
  });
}

if (!findings.length) {
  console.log('OK: no script builds output from a value that is only known to the page');
} else {
  console.log(`${findings.length} place(s) where a script reads page-side state it cannot have:`);
  for (const f of findings) {
    console.log(`  ${f.file.padEnd(34)} line ${String(f.line).padStart(4)}  ${f.kind}${f.detail ? ' ' + f.detail : ''}`);
    console.log(`      ${f.text}`);
  }
}
void phpVarsOfScript;
