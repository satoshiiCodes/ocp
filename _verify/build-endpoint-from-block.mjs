// Builds a per-page endpoint from a page's read block, lifting it verbatim.
//
//   node build-endpoint-from-block.mjs <page> <fromLine> <toLine> <name,name,...> [--apply]
//
// The block is copied unchanged; each plain assignment to one of the named variables
// becomes an entry on the returned array, and any loop that iterated one of them is
// pointed at the entry instead - that last part is what a naive rename misses.
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const APPLY = process.argv.includes('--apply');
const args = process.argv.slice(2).filter(a => a !== '--apply');
const [page, fromArg, toArg, nameList] = args;

if (!page || !fromArg || !toArg || !nameList) {
  console.error('usage: node build-endpoint-from-block.mjs <page> <from> <to> <names> [--apply]');
  process.exit(2);
}

const names = nameList.split(',').map(s => s.trim()).filter(Boolean);
const lines = fs.readFileSync(`${ROOT}\\${page}.php`, 'utf8').replace(/\r\n/g, '\n').split('\n');
const FROM = Number(fromArg);
const TO = Number(toArg);
const at = (n) => lines[n - 1] ?? '';

if (!/^\s*\/\//.test(at(FROM))) {
  console.error(`ABORT: line ${FROM} is not a comment: ${JSON.stringify(at(FROM))}`);
  process.exit(1);
}
if (at(TO).trim() !== '}') {
  console.error(`ABORT: line ${TO} is not a closing brace: ${JSON.stringify(at(TO))}`);
  process.exit(1);
}

let code = lines.slice(FROM - 1, TO).join('\n');
const report = [];

for (const n of names) {
  const esc = n.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  let c = 0;
  // "$x = [];" / "$x = 0;" / "$x = ['a','b'];"
  code = code.replace(new RegExp(`^(\\s*)\\$${esc} = (\\[\\]|0|array\\(\\)|\\[[^\\]]*\\]);$`, 'gm'),
    (m, pad, val) => { c++; return `${pad}$ocp_endpoint['${n}'] = ${val};`; });
  // "$x = $yStmt->fetchAll(...);"  /  "$x = f($pdo);"
  code = code.replace(new RegExp(`^(\\s*)\\$${esc} = (\\$[\\w$]+->fetch(?:All)?\\([^;]*?\\)|[a-zA-Z_]\\w*\\([^;]*?\\));$`, 'gm'),
    (m, pad, rhs) => { c++; return `${pad}$ocp_endpoint['${n}'] = ${rhs};`; });
  // "$x += ...;" inside a loop over the same data
  code = code.replace(new RegExp(`^(\\s*)\\$${esc} \\+= (.*);$`, 'gm'),
    (m, pad, rhs) => { c++; return `${pad}$ocp_endpoint['${n}'] += ${rhs};`; });
  // "foreach ($x as ...)" - the loop must read the entry, not a variable that is gone
  const loops = [...code.matchAll(new RegExp(`foreach \\(\\$${esc} as`, 'g'))].length;
  code = code.replace(new RegExp(`foreach \\(\\$${esc} as`, 'g'), `foreach ($ocp_endpoint['${n}'] as`);
  // "$x = $x + ..." style reads elsewhere in the block
  report.push(`  ${n.padEnd(24)} assignments ${c}, loops repointed ${loops}`);
}

const left = [...code.matchAll(new RegExp(`^\\s*\\$(${names.join('|')}) = `, 'gm'))];
if (left.length) {
  console.error('ABORT: these assignments were not converted:');
  for (const m of left) console.error(`  ${m[0].trim()}`);
  process.exit(1);
}
console.log(report.join('\n'));

const init = names.map(n => {
  const numeric = /\b(total|balance|count|amount|sum|year)\b/i.test(n);
  return `    '${n}' => ${numeric ? '0' : '[]'},`;
}).join('\n');

const out = `<?php
/**
 * api/${page.replace(/\.php$/, '')}-endpoint.php
 *
 * Every read for ${page} lives in this one file. The page pulls this in instead of
 * querying the database itself, so all of its fetching is in one place; it runs in
 * the page's scope and returns the variables the markup needs, which the page unpacks.
 * Nothing is printed here, so this file cannot disturb the page's output.
 *
 * The block is lifted verbatim from ${page}: the queries and the filters are unchanged.
 *
 * Returns
${names.map(n => ` *   ${n}`).join('\n')}
 */

$ocp_endpoint = [
${init}
];

// Standalone guard: the block runs in the page's scope, where the connection is already
// open. Requested on its own there is none, so it answers with the empty result.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

${code}

return $ocp_endpoint;
`;

console.log(`${names.length} variable(s); endpoint ${out.length} bytes`);
if (APPLY) {
  fs.writeFileSync(`${ROOT}\\api\\${page.replace(/\.php$/, '')}-endpoint.php`, out);
  console.log('APPLIED');
} else {
  console.log('DRY RUN');
}
