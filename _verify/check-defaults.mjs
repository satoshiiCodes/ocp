import fs from "node:fs";
const src = fs.readFileSync("E:\\laragon\\www\\OCP\\api\\inventory-endpoint.php", "utf8");
const inits = [...src.matchAll(/^    '([a-z_]+)' => (.+),$/gm)].map(m => [m[1], m[2]]);
let bad = 0;
for (const [name, def] of inits) {
  const assign = new RegExp(`\\$ocp_endpoint\\['${name}'\\] = ([^;]+);`).exec(src);
  if (!assign) continue;
  const rhs = assign[1].trim();
  const looksNumeric = /\+=|'0'|"0"|^\d+$/.test(rhs) || /\bdate\(|\bcount\(/.test(rhs) === false && /^\$ocp_endpoint\['[a-z_]+'\]\['total'\]$/.test(rhs);
  const isFetchAll = /->fetchAll\(/.test(rhs);
  const isFetchOne = /->fetch\(/.test(rhs);
  if (isFetchAll && def !== "[]") { console.log(`  X ${name}: defaults to ${def} but fetchAll returns an array`); bad++; }
  if (isFetchOne && def !== "[]") { console.log(`  ? ${name}: defaults to ${def} but fetch() returns an array or false`); }
}
console.log(bad ? `  ${bad} mismatch(es)` : "  no array/non-array mismatches");
