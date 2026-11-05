// Works in EdgeOne's Linux build without WordPress, PHP, databases or npm packages.
import assert from 'node:assert/strict';
import {readFile, readdir, lstat} from 'node:fs/promises';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {root, inside, fileFor, references} from './static-utils.mjs';

export async function checkStatic(directory = path.join(root, 'dist')) {
  const files = [];
  async function walk(dir) {
    for (const entry of await readdir(dir, {withFileTypes:true})) {
      assert.ok(!entry.isSymbolicLink(), 'No links allowed in public output');
      const file = path.join(dir, entry.name);
      if (entry.isDirectory()) await walk(file); else files.push(file);
    }
  }
  assert.ok(!(await lstat(directory)).isSymbolicLink(), 'Output must not be a link');
  await walk(directory);
  const manifest = JSON.parse(await readFile(inside(directory, 'static-manifest.json'), 'utf8'));
  assert.equal(manifest.version, 1);
  const names = new Set(files.map(f => path.relative(directory, f).split(path.sep).join('/')));
  for (const route of ['/', '/shop/', '/about-us/', '/contact/', '/hair-guide/', '/faq/', '/privacy/', ...manifest.routes, '/404.html']) assert.ok(names.has(fileFor(route)), `Missing page: ${route}`);
  for (const file of files) {
    const relative = path.relative(directory, file).split(path.sep).join('/');
    assert.match(relative, /\.(?:html|css|js|png|jpe?g|webp|gif|avif|svg|ico|woff2?|ttf|eot|txt|xml|json)$/i, 'Unexpected file type in public output');
    assert.ok(!/(?:^|\/)(?:key|backups|\.local|\.git|wp-admin|wp-json)(?:\/|$)/i.test(relative), 'Private/dynamic path in output');
    if (!/\.(?:html|css|js|svg|txt|xml|json)$/i.test(file)) continue;
    const text = await readFile(file, 'utf8');
    assert.ok(!/127\.0\.0\.1|localhost|https?%3A%2F%2F127|<\?php|DB_PASSWORD|BEGIN (?:RSA |OPENSSH )?PRIVATE KEY|AKIA[A-Z0-9]{16}/i.test(text), `Local URL or secret marker: ${relative}`);
    if (/\.html$/i.test(file)) {
      assert.match(text, /<html\b[^>]*lang=["']en/i, `English required: ${relative}`);
      assert.match(text, /<footer\b[\s\S]*href=["']\/privacy\/["'][^>]*>Privacy Policy<\/a>[\s\S]*<\/footer>/i, `Privacy footer missing: ${relative}`);
      assert.ok(!/<form\b|\/(?:wp-admin|wp-json|wp-login\.php)|wc-ajax|add-to-cart|woocommerce-Price-amount|single_add_to_cart_button|noindex, nofollow/i.test(text), `Dynamic entry in static page: ${relative}`);
      assert.ok(!/\[susmo_company_details\]/.test(text), 'Unrendered shortcode');
    }
    if (!/\.(?:html|css)$/i.test(file)) continue;
    const basePath = relative.endsWith('index.html') ? '/' + relative.slice(0, -10) : '/' + relative;
    for (const ref of references(text, /\.html$/i.test(file))) {
      if (/^(?:mailto:|tel:|data:)/i.test(ref)) continue;
      const url = new URL(ref, 'https://static.invalid' + basePath);
      if (url.origin !== 'https://static.invalid') {
        assert.ok(url.protocol === 'https:', 'Unsafe external link');
        continue;
      }
      assert.ok(!url.search || !/(?:wc-|add-to-cart|rest_route|s=)/i.test(url.search), 'Dynamic query not supported');
      const name = fileFor(url.pathname);
      assert.ok(names.has(name), `Broken link/resource: ${relative} -> ${url.pathname}`);
      if (url.hash && name.endsWith('.html')) {
        const target = await readFile(inside(directory, name), 'utf8');
        const id = decodeURIComponent(url.hash.slice(1));
        assert.ok(target.includes(`id="${id}"`) || target.includes(`id='${id}'`), `Missing anchor: ${relative} -> ${url.hash}`);
      }
    }
  }
  for (const asset of manifest.assets) assert.ok(names.has(fileFor(asset)), 'Missing exported asset');
  console.log(`Static check passed: ${manifest.routes.length} pages + 404; ${files.length} files; links, images, CSS dependencies, gallery data and privacy footers verified.`);
  return {pages:manifest.routes.length, files:files.length};
}
if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) await checkStatic();
