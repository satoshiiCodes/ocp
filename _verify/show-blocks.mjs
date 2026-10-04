import fs from 'node:fs';
const ROOT = 'E:\\laragon\\www\\OCP';

const show = (rel, from, to) => {
  const lines = fs.readFileSync(`${ROOT}\\${rel.replace(/\//g, '\\')}`, 'utf8').replace(/\r\n/g, '\n').split('\n');
  console.log(`--- ${rel} lines ${from}-${to}`);
  for (let i = from; i <= to; i++) console.log(i, JSON.stringify(lines[i - 1]));
  console.log('');
};

show('assets/js/issue_materials.js.php', 128, 137);
show('assets/js/issue_materials.js.php', 328, 337);
show('assets/js/purchase_request.js.php', 405, 410);
