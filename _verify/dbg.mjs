import { spawn } from "node:child_process";
const ROOT = "E:\\laragon\\www\\OCP";
const server = spawn("php", ["-S", "127.0.0.1:8160", "-t", "."], { cwd: ROOT, stdio: "ignore" });
await new Promise(r => setTimeout(r, 2300));
for (const sid of ["ocpverify0000000000000003", "ocpverify0000000000000007"]) {
  const r = await fetch("http://127.0.0.1:8160/dashboard.php", { headers: { Cookie: `PHPSESSID=${sid}` }, redirect: "manual" });
  const text = await r.text();
  const i = text.indexOf("OCP_PAGE_DASHBOARD");
  console.log(`\nsid=${sid} status=${r.status} bytes=${text.length}`);
  console.log("  island:", i < 0 ? "(absent)" : text.slice(i, text.indexOf("</script>", i)).slice(0, 300));
}
server.kill();
