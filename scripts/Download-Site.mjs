import { mkdir, readFile, writeFile, rename } from 'node:fs/promises';
import { createWriteStream, createReadStream } from 'node:fs';
import { Readable } from 'node:stream';
import { pipeline } from 'node:stream/promises';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const local = path.join(root, '.local');
const downloads = path.join(local, 'downloads');
const manifestFile = path.join(local, 'site-packages.json');
await mkdir(downloads, { recursive: true });
async function json(url) {
  const response = await fetch(url, { signal: AbortSignal.timeout(30000) });
  if (!response.ok) throw new Error(`Official API returned HTTP ${response.status}`);
  return response.json();
}
async function hash(file) {
  const digest = createHash('sha256');
  for await (const chunk of createReadStream(file)) digest.update(chunk);
  return digest.digest('hex');
}
let packages;
try { packages = JSON.parse(await readFile(manifestFile, 'utf8')); }
catch (error) {
  if (error.code !== 'ENOENT') throw error;
  const [core, woo, theme] = await Promise.all([
    json('https://api.wordpress.org/core/version-check/1.7/'),
    json('https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=woocommerce'),
    json('https://api.wordpress.org/themes/info/1.2/?action=theme_information&request[slug]=storefront'),
  ]);
  const stable = core.offers.find(offer => offer.response === 'upgrade');
  if (!stable || !woo.version || !theme.version) throw new Error('Unexpected official package metadata');
  packages = [
    { name: 'wordpress', version: stable.version, url: stable.download },
    { name: 'woocommerce', version: woo.version, url: woo.download_link },
    { name: 'storefront', version: theme.version, url: theme.download_link },
  ];
}
for (const pkg of packages) {
  if (!['wordpress', 'woocommerce', 'storefront'].includes(pkg.name)) throw new Error('Unknown package name');
  const url = new URL(pkg.url);
  if (url.protocol !== 'https:' || url.hostname !== 'downloads.wordpress.org') throw new Error('Unexpected download source');
  const destination = path.join(downloads, `${pkg.name}.zip`);
  if (pkg.sha256) {
    try {
      if (await hash(destination) === pkg.sha256) { console.log(`${pkg.name} ${pkg.version}: cached`); continue; }
    } catch (error) { if (error.code !== 'ENOENT') throw error; }
    throw new Error(`Cached ${pkg.name} archive is missing or changed. Restore the recorded archive before retrying.`);
  }
  console.log(`Downloading ${pkg.name} ${pkg.version} from WordPress.org...`);
  const response = await fetch(pkg.url, { signal: AbortSignal.timeout(180000) });
  if (!response.ok) throw new Error(`Download failed: HTTP ${response.status}`);
  await pipeline(Readable.fromWeb(response.body), createWriteStream(`${destination}.part`));
  await rename(`${destination}.part`, destination);
  pkg.sha256 = await hash(destination);
}
await writeFile(manifestFile, JSON.stringify(packages, null, 2) + '\n');
console.log('Official packages downloaded; versions and SHA256 recorded in .local/site-packages.json.');
