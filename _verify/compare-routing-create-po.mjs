import fs from "node:fs";
import path from "node:path";
const ROOT = "E:\\laragon\\www\\OCP";
const backup = fs.readFileSync(path.join(ROOT, "_restructure_backup", "pr_view_routing.php"), "utf8").replace(/\r\n/g, "\n").split("\n");
const current = fs.readFileSync(path.join(ROOT, "actions", "pr_view_routing-actions.php"), "utf8").replace(/\r\n/g, "\n").split("\n");
function caseBody(lines, name) {
  const start = lines.findIndex(l => new RegExp(`^\\s*case '${name}':`).test(l));
  if (start < 0) return null;
  for (let i = start + 1; i < lines.length; i++) {
    if (/^\s*case '/.test(lines[i]) || /^\s*\}\s*\/\/\s*end/.test(lines[i])) return lines.slice(start, i);
  }
  return lines.slice(start, start + 500);
}
const norm = b => (b || []).map(l => l.trim()).filter(l => l !== "" && !/^(\/\/|\*|\/\*)/.test(l)).map(l => l.replace(/\s+/g, " "));
for (const name of ["create_purchase_order", "receive_items"]) {
  const a = norm(caseBody(backup, name));
  const b = norm(caseBody(current, name));
  if (!a.length) { console.log(`  ${name}: NOT FOUND in the backup`); continue; }
  if (!b.length) { console.log(`  ${name}: NOT FOUND in the current file`); continue; }
  const setA = new Set(a), setB = new Set(b);
  const onlyB = a.filter(l => !setB.has(l));
  const onlyC = b.filter(l => !setA.has(l));
  if (!onlyB.length && !onlyC.length) { console.log(`  ${name.padEnd(24)} identical (${a.length} significant lines)`); continue; }
  console.log(`  ${name.padEnd(24)} DIFFERS - backup ${a.length}, current ${b.length}`);
  for (const l of onlyB.slice(0, 10)) console.log(`      only in the original: ${l.slice(0, 100)}`);
  for (const l of onlyC.slice(0, 10)) console.log(`      only in the current : ${l.slice(0, 100)}`);
}
