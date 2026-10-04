import { spawn } from "node:child_process";
const ROOT = "E:\\laragon\\www\\OCP";
const server = spawn("php", ["-S", "127.0.0.1:8152", "-t", "."], { cwd: ROOT, stdio: "ignore" });
await new Promise(r => setTimeout(r, 2300));
const res = await fetch("http://127.0.0.1:8152/dashboard.php", { headers: { Cookie: "PHPSESSID=ocpverify0000000000000003" } });
const text = await res.text();
const i = text.indexOf("window.OCP_PAGE_DASHBOARD");
console.log(i < 0 ? "island NOT found" : text.slice(i, text.indexOf("</script>", i)).slice(0, 460));
server.kill();
