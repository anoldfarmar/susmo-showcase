// Catalog integration checks. Never creates carts, customers, messages or orders.
import assert from 'node:assert/strict';
import {readFileSync, writeFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import path from 'node:path';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const runtime = JSON.parse(readFileSync(path.join(root,'.local/runtime.json'),'utf8'));
assert.ok(path.resolve(runtime.PhpExe).toLowerCase().startsWith((path.join(root,'.local')+path.sep).toLowerCase()), 'PHP executable must belong to this project runtime');
const audit = () => JSON.parse(execFileSync(runtime.PhpExe,[path.join(root,'scripts/catalog-audit.php')],{cwd:root,encoding:'utf8'}).trim());
const before = audit();
assert.ok(before.catalog_active, 'Catalog restrictions must be active');
assert.equal(before.registration_enabled,false);
const base = 'http://127.0.0.1:8080';
const noCommerceCookies = response => {
  const cookies = response.headers.getSetCookie();
  assert.ok(!cookies.some(cookie => /(?:wp_woocommerce_session_|woocommerce_items_in_cart|woocommerce_cart_hash|sbjs_)/i.test(cookie)), 'Visitors must not receive commerce/tracking cookies');
  assert.equal(response.headers.get('Cart-Token'), null, 'Visitors must not receive cart tokens');
};
async function request(path, options = {}, expected = 200) {
  const response = await fetch(base + path, { redirect: 'manual', signal: AbortSignal.timeout(30000), ...options });
  assert.equal(response.status, expected, `${options.method || 'GET'} ${path}: HTTP ${response.status}`);
  noCommerceCookies(response);
  return response;
}
let productPath;
for (const path of ['/', '/shop/', '/about-us/', '/contact/', '/faq/', '/hair-guide/', '/privacy/']) {
  const html = await (await request(path)).text();
  assert.match(html, /susmo/i, `${path}: brand must be present`);
  assert.ok(html.includes('Shenzhen Dunlin E-Commerce Co., Ltd.'), `${path}: translated company name must be present`);
  assert.ok(html.includes('6B302B'), `${path}: company address must be present`);
  assert.ok(!/href=["'][^"']*\/(?:cart|checkout|my-account)\//i.test(html), `${path}: no transaction/account navigation`);
  assert.ok(!/(?:single_add_to_cart_button|add_to_cart_button|woocommerce-Price-amount|wc-order-attribution|sourcebuster)/i.test(html), `${path}: no purchase controls, prices or attribution scripts`);
  assert.ok(!/<(?:input|textarea)[^>]*name=["'](?:email|billing_[^"']+|shipping_[^"']+|comment|author|password|username)["']/i.test(html), `${path}: no customer data form`);
  if (path === '/shop/') {
    const match = html.match(/href=["'](?:http:\/\/127\.0\.0\.1:8080)?(\/product\/[^"'#?]+)["']/);
    assert.ok(match, 'Product catalog must contain a product detail link');
    productPath = match[1];
  }
  if (path === '/contact/') assert.ok(html.includes('mailto:2813039017@qq.com'), 'Company email must be a direct email link');
  console.log(`Catalog page OK: ${path}`);
}
const detail = await (await request(productPath)).text();
assert.ok(!/single_add_to_cart_button|woocommerce-Price-amount/.test(detail), 'Product details are read-only');
console.log(`Product detail OK: ${productPath}`);
await request('/wp-login.php'); // Administrator entry remains reachable.
for (const path of ['/cart/', '/checkout/', '/my-account/', '/?add-to-cart=1', '/?wc-ajax=add_to_cart', '/wp-login.php?action=register', '/wp-login.php?action=lostpassword']) {
  await request(path, {}, 410);
}
await request('/', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'add-to-cart=1&quantity=1' }, 410);
for (const path of ['/wp-json/wc/store/v1/cart', '/?rest_route=/wc/store/v1/cart']) await request(path, {}, 403);
for (const path of ['/wp-json/wc/store/v1/cart/add-item', '/wp-json/wc/v3/customers', '/wp-json/wc/v3/orders', '/wp-json/wp/v2/users', '/wp-json/wp/v2/comments']) {
  // Empty payload exercises rejection without submitting personal data.
  await request(path, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' }, 403);
}
console.log('Catalog guards OK: old URLs, GET/POST add-to-cart, wc-ajax, Store API, customer/order writes, registration and comments blocked. No commerce cookies or cart tokens returned.');
const after = audit();
assert.deepEqual(after,before,'Browsing and blocked requests must not create customers, orders, comments or sessions');
writeFileSync(path.join(root,'.local/catalog-verification.json'),JSON.stringify({verifiedAt:new Date().toISOString(),before,after,status:'passed'},null,2));
console.log('Database audit OK: no new customer records, orders, comments or visitor sessions. Historical records preserved.');
