import { spawn } from "node:child_process";
import fs from "node:fs";
const ROOT = "E:\\laragon\\www\\OCP";
const server = spawn("php", ["-S", "127.0.0.1:8227", "-t", "."], { cwd: ROOT, stdio: "ignore" });
await new Promise(r => setTimeout(r, 2300));
const files = fs.readdirSync(ROOT + "\\api").filter(f => f.endsWith("-endpoint.php")).sort();
let problems = 0;
for (const f of files) {
  const res = await fetch(`http://127.0.0.1:8227/api/${f}`);
  const text = await res.text();
  const err = /(Fatal error|Parse error|Uncaught|Warning|Notice|Undefined)/.test(text);
  const out = text.trim().length;
  if (res.status !== 200 || err || out !== 0) { problems++; console.log(`  X ${f}: HTTP ${res.status}, ${out} bytes of output, error=${err}`); }
}
console.log(`  ${files.length} endpoints checked, ${problems} problems`);
server.kill();
