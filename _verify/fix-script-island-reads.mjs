// Rewrites the last PHP island reads in the scripts into browser reads.
//
//   node fix-script-island-reads.mjs [--apply]
//
// A script under assets/js/*.js.php is fetched as its own request, so $__ocp_data does not
// exist there and `ocp_island_get($__ocp_data, "k")` always answers null. Every key these
// reads ask for is published in the island; they just have to be read in the browser.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const dir = path.join(ROOT, 'assets/js');

const constOf = (slug) => slug.replace(/[^A-Za-z0-9]+/g, '_').toUpperCase() + '_DATA';
const islandOf = (slug) => slug.replace(/[^A-Za-z0-9]+/g, '_').toUpperCase();

const report = [];
for (const file of fs.readdirSync(dir).filter(f => f.endsWith('.js.php')).sort()) {
  const slug = file.replace(/\.js\.php$/, '');
  const full = path.join(dir, file);
  const raw = fs.readFileSync(full, 'utf8');
  if (!/ocp_island_get\s*\(/.test(raw)) continue;
  const eol = raw.includes('\r\n') ? '\r\n' : '\n';
  let code = raw.replace(/\r\n/g, '\n');
  const before = code;
  const CONST = constOf(slug);
  let n = 0;

  // <?php echo ocp_js_raw(ocp_island_get($__ocp_data, "k")); ?>   ->   CONST.k
  code = code.replace(
    /<\?php echo ocp_js_raw\(ocp_island_get\(\$__ocp_data,\s*["']([A-Za-z0-9_]+)["']\)\); \?>/g,
    (m, k) => { n++; return `${CONST}.${k}`; });

  // <?php echo ocp_js_string(ocp_island_get($__ocp_data, "k")); ?> inside a string literal
  code = code.replace(
    /'<\?php echo ocp_js_string\(ocp_island_get\(\$__ocp_data,\s*["']([A-Za-z0-9_]+)["']\)\); \?>'/g,
    (m, k) => { n++; return `' + (${CONST}.${k} ?? '') + '`; });
  code = code.replace(
    /<\?php echo ocp_js_string\(ocp_island_get\(\$__ocp_data,\s*["']([A-Za-z0-9_]+)["']\)\); \?>/g,
    (m, k) => { n++; return '${' + `${CONST}.${k} ?? ''` + '}'; });

  // a truth test:  <?php if (ocp_island_get($__ocp_data, "k")): ?> ... <?php endif; ?>
  code = code.replace(
    /^([ \t]*)<\?php if \(ocp_island_get\(\$__ocp_data,\s*["']([A-Za-z0-9_]+)["']\)\): \?>$/gm,
    (m, ind, k) => { n++; return `${ind}if (${CONST}.${k}) {`; });
  code = code.replace(
    /^([ \t]*)<\?php endif; \?>$(?=[\s\S]{0,40}?\n[ \t]*(?:\/\/|const |let |var |\$|\}))/gm,
    (m, ind) => m);   // leave other endifs alone

  // <?php echo X ? 'true' : 'false'; ?>  ->  (X ? 'true' : 'false')
  code = code.replace(
    /<\?php echo ocp_js_raw\(ocp_island_get\(\$__ocp_data,\s*["']([A-Za-z0-9_]+)["']\)\) \? 'true' : 'false'; \?>/g,
    (m, k) => { n++; return `(${CONST}.${k} ? 'true' : 'false')`; });

  if (code !== before) {
    // make sure the island handle exists
    if (!code.includes(`const ${CONST} = window`)) {
      const h = /^(\?>)\n/m.exec(code);
      if (h) {
        const at = h.index + h[0].length;
        code = code.slice(0, at)
          + `// The page's data island. This script is fetched as its own request, so the page's\n`
          + `// PHP variables are NOT in scope: every value it needs is read from the island.\n`
          + `const ${CONST} = window.OCP_PAGE_${islandOf(slug)} || {};\n`
          + code.slice(at);
      }
    }
    if (APPLY) fs.writeFileSync(full, eol === '\r\n' ? code.replace(/\n/g, '\r\n') : code);
    const left = code.split('\n').filter(l => /ocp_island_get\s*\(/.test(l) && !/^\s*(\*|\/\*|\/\/)/.test(l));
    report.push({ file, changed: n, left: left.length });
  }
}

console.log(`${report.length} file(s)${APPLY ? ' written' : ' (dry run)'}:`);
for (const r of report) console.log(`  ${r.file.padEnd(34)} ${r.changed} read(s) converted, ${r.left} left`);
