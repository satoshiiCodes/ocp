// Prints the inline <script> blocks (without src) from a page in _restructure_backup/,
// which holds the pre-restructure originals.
//
//   node show-backup-scripts.mjs <page.php> [index]
import fs from 'node:fs';

const ROOT = 'E:\\laragon\\www\\OCP';
const [page, which] = process.argv.slice(2);
const src = fs.readFileSync(`${ROOT}\\_restructure_backup\\${page}`, 'utf8').replace(/\r\n/g, '\n');

const blocks = [];
const re = /<script(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script>/g;
let m;
while ((m = re.exec(src)) !== null) {
  const line = src.slice(0, m.index).split('\n').length;
  blocks.push({ line, body: m[1] });
}

console.log(`${blocks.length} inline script block(s) in ${page}:`);
blocks.forEach((b, i) => {
  const first = b.body.trim().split('\n')[0] || '';
  console.log(`  [${i}] line ${b.line}, ${b.body.split('\n').length} lines, starts: ${first.slice(0, 70)}`);
});

if (which !== undefined) {
  const b = blocks[Number(which)];
  if (!b) { console.error('no such block'); process.exit(1); }
  process.stdout.write(b.body);
}
