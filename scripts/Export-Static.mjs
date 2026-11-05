// Fetch only public catalog pages and their referenced static assets. Never copies .local/www.
import {readFile, writeFile, mkdir, rename, lstat} from 'node:fs/promises';
import {existsSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
import path from 'node:path';
import {randomUUID} from 'node:crypto';
import {root, localOrigin, assetPattern, inside, fileFor, references} from './static-utils.mjs';
import {checkStatic} from './Check-Static.mjs';

let siteUrl = process.env.STATIC_SITE_URL || '';
const args = process.argv.slice(2);
if (args.length) {
  if (args.length !== 2 || args[0] !== '--site-url') throw new Error('Usage: node scripts/Export-Static.mjs [--site-url https://your-domain]');
  siteUrl = args[1];
}
if (siteUrl) {
  const url = new URL(siteUrl);
  if (url.protocol !== 'https:' || url.pathname !== '/' || url.search || url.hash || url.username || url.password || /localhost|127\.0\.0\.1/.test(url.hostname)) throw new Error('Site URL must be a public HTTPS origin without path or credentials');
  siteUrl = url.origin;
}
const runtime = JSON.parse(await readFile(path.join(root, '.local/runtime.json'), 'utf8'));
inside(path.join(root, '.local'), path.relative(path.join(root, '.local'), runtime.PhpExe));
const audit = () => JSON.parse(execFileSync(runtime.PhpExe, [path.join(root, 'scripts/catalog-audit.php')], {cwd:root, encoding:'utf8'}));
const before = audit();
const routes = JSON.parse(execFileSync(runtime.PhpExe, [path.join(root, 'scripts/static-routes.php')], {cwd:root, encoding:'utf8'}));
for (const required of ['/', '/shop/', '/about-us/', '/contact/', '/hair-guide/', '/faq/', '/privacy/']) {
  if (!routes.includes(required)) throw new Error(`Required published page missing: ${required}`);
}
for (const route of routes) {
  if (!route.endsWith('/') || /\?|#|\.(?:php)|^\/(?:wp-|cart|checkout|my-account)/i.test(route)) throw new Error(`Unsupported public route: ${route}`);
  fileFor(route);
}
const stage = inside(root, '.local/static-build-' + randomUUID());
await mkdir(stage);
const assets = new Map();
const save = async (name, data) => {
  const file = inside(stage, name);
  await mkdir(path.dirname(file), {recursive:true});
  await writeFile(file, data);
};
async function get(url, expected = 200) {
  const response = await fetch(url, {redirect:'manual', signal:AbortSignal.timeout(30000)});
  if (response.status !== expected) throw new Error(`HTTP ${response.status} fetching ${new URL(url).pathname}`);
  if (response.headers.getSetCookie().some(c => /woocommerce|sbjs_/i.test(c))) throw new Error('Unexpected visitor commerce cookie');
  return response;
}
function collect(text, base, html) {
  for (const ref of references(text, html)) {
    if (/^(?:#|mailto:|tel:|data:)/i.test(ref)) continue;
    const url = new URL(ref, base);
    if (!assetPattern.test(url.pathname)) continue;
    if (url.origin !== localOrigin) throw new Error(`External resource needs review: ${url.hostname}`);
    fileFor(url.pathname);
    assets.set(url.pathname, url.origin + url.pathname);
  }
}
function rewrite(text) {
  return text.replaceAll(localOrigin.replaceAll('/', '\\/'), '').replaceAll(localOrigin, '').replaceAll('//127.0.0.1:8080', '');
}
function cleanHtml(html, route) {
  // Dynamic WP metadata and scripts have no role in the standalone catalog.
  html = html.replace(/<link\b[^>]*>/gi, tag => /rel\s*=\s*["'](?:stylesheet|icon|apple-touch-icon)["']/i.test(tag) ? tag : '');
  html = html.replace(/<meta\b[^>]*(?:name\s*=\s*["'](?:robots|generator)["'])[^>]*>/gi, '');
  html = html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, tag => /\bsrc=["'][^"']*\/themes\/local-store\/assets\/site\.js(?:\?[^"']*)?["']/i.test(tag) ? tag : '');
  if (siteUrl && route !== '/404.html') html = html.replace('</head>', `<link rel="canonical" href="${siteUrl}${route}"></head>`);
  if (route === '/404.html') html = html.replace('</head>', '<meta name="robots" content="noindex"></head>');
  return html;
}
for (const route of [...routes, '/404.html']) {
  const sourcePath = route === '/404.html' ? '/susmo-static-missing-page/' : route;
  const response = await get(localOrigin + sourcePath, route === '/404.html' ? 404 : 200);
  let html = cleanHtml(await response.text(), route);
  collect(html, localOrigin + sourcePath, true);
  html = rewrite(html);
  await save(fileFor(route), html);
  console.log(`Exported ${route}`);
}
const done = new Set();
while (done.size < assets.size) {
  const [assetPath, source] = [...assets].find(([name]) => !done.has(name));
  const response = await get(source);
  let data = Buffer.from(await response.arrayBuffer());
  if (data.length > 15 * 1024 * 1024) throw new Error('Asset exceeds 15 MB limit');
  if (/\.(css|js|svg)$/i.test(assetPath)) {
    let text = data.toString('utf8');
    if (/\.css$/i.test(assetPath)) collect(text, source, false);
    text = rewrite(text);
    data = Buffer.from(text);
  }
  await save(fileFor(assetPath), data);
  done.add(assetPath);
}
await save('robots.txt', `User-agent: *\nAllow: /\n${siteUrl ? `Sitemap: ${siteUrl}/sitemap.xml\n` : ''}`);
if (siteUrl) await save('sitemap.xml', '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' + routes.map(route => `<url><loc>${siteUrl}${route.replaceAll('&', '&amp;')}</loc></url>`).join('') + '</urlset>\n');
await save('static-manifest.json', JSON.stringify({version:1, siteUrl, routes, assets:[...done]}, null, 2));
await checkStatic(stage);
const after = audit();
if (JSON.stringify(before) !== JSON.stringify(after)) throw new Error('Database counts changed during export');
// Validate exact resolved paths before moving directory trees. Retain previous output as a local backup.
const dist = inside(root, 'dist');
const backup = inside(root, '.local/static-previous-' + randomUUID());
if (existsSync(dist)) {
  if ((await lstat(dist)).isSymbolicLink() || !(await lstat(dist)).isDirectory()) throw new Error('dist must be a regular directory');
  await rename(dist, backup);
}
try { await rename(stage, dist); }
catch (error) { if (existsSync(backup)) await rename(backup, dist); throw error; }
console.log(`Static catalog ready: dist/ (${routes.length} pages + 404, ${done.size} assets). Database counts unchanged.`);
if (!siteUrl) console.log('Domain not set: portable links ready; canonical URLs and sitemap will be generated when --site-url is provided.');
