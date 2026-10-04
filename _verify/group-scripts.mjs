// Groups the server-rendered scripts by the island keys they read, so the fixes can be
// made pattern by pattern instead of file by file.
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'E:\\laragon\\www\\OCP';
const groups = new Map();
for (const file of fs.readdirSync(path.join(ROOT, 'assets/js')).filter(f => f.endsWith('.js.php'))) {
  const src = fs.readFileSync(path.join(ROOT, 'assets/js', file), 'utf8');
  const keys = [...new Set([...src.matchAll(/ocp_island_get\(\$__ocp_data,\s*["']([^"']+)["']\)/g)].map(m => m[1]))];
  if (!keys.length) continue;
  const sig = keys.join(',');
  if (!groups.has(sig)) groups.set(sig, []);
  groups.get(sig).push(file);
}

const sorted = [...groups.entries()].sort((a, b) => b[1].length - a[1].length);
for (const [sig, files] of sorted) {
  console.log(`\n${files.length} file(s): ${files.join(', ')}`);
  console.log(`  keys: ${sig}`);
}
console.log(`\ntotal files with island reads: ${[...groups.values()].reduce((n, f) => n + f.length, 0)}`);
