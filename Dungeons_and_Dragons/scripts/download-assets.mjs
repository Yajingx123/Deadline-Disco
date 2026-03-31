import fs from "node:fs";
import path from "node:path";
import https from "node:https";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.join(__dirname, "..", "public", "assets", "game");

const files = [
  {
    name: "dude.png",
    url: "https://raw.githubusercontent.com/phaserjs/examples/master/public/assets/sprites/dude.png",
  },
  {
    name: "enemy_mummy.png",
    url: "https://raw.githubusercontent.com/phaserjs/examples/master/public/assets/sprites/metalslug_mummy37x45.png",
  },
  {
    name: "buch-dungeon-tileset.png",
    url: "https://raw.githubusercontent.com/phaserjs/examples/master/public/assets/tilemaps/tiles/buch-dungeon-tileset.png",
  },
  {
    name: "skullcandle.png",
    url: "https://raw.githubusercontent.com/phaserjs/examples/master/public/assets/sprites/skullcandle.png",
  },
];

function fetchFile(url) {
  return new Promise((resolve, reject) => {
    https
      .get(url, (res) => {
        if (res.statusCode === 302 || res.statusCode === 301) {
          const loc = res.headers.location;
          if (!loc) {
            reject(new Error("Redirect without location"));
            return;
          }
          fetchFile(loc).then(resolve).catch(reject);
          return;
        }
        if (res.statusCode !== 200) {
          reject(new Error(`HTTP ${res.statusCode} ${url}`));
          return;
        }
        const chunks = [];
        res.on("data", (c) => chunks.push(c));
        res.on("end", () => resolve(Buffer.concat(chunks)));
      })
      .on("error", reject);
  });
}

fs.mkdirSync(outDir, { recursive: true });

for (const f of files) {
  const dest = path.join(outDir, f.name);
  process.stdout.write(`Fetching ${f.name}… `);
  const buf = await fetchFile(f.url);
  fs.writeFileSync(dest, buf);
  console.log(`${buf.length} bytes`);
}

console.log("Done:", outDir);
