import path from 'node:path';
import {fileURLToPath} from 'node:url';
export const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
export const localOrigin = 'http://127.0.0.1:8080';
export const assetPattern = /\.(?:css|js|png|jpe?g|webp|gif|avif|svg|ico|woff2?|ttf|eot)$/i;
export function inside(directory, relative) {
  const target = path.resolve(directory, relative);
  if (!target.startsWith(path.resolve(directory) + path.sep)) throw new Error('Unsafe output path');
  return target;
}
export function fileFor(urlPath) {
  const decoded = decodeURIComponent(urlPath);
  if (!decoded.startsWith('/') || /\\|\0/.test(decoded) || decoded.split('/').some(p => p === '..' || p.startsWith('.'))) throw new Error('Unsafe URL path');
  return decoded.endsWith('/') ? decoded.slice(1) + 'index.html' : decoded.slice(1);
}
export function references(text, html = true) {
  const result = [];
  if (html) {
    for (const m of text.matchAll(/\b(href|src|poster|data-image|srcset)\s*=\s*(["'])(.*?)\2/gis)) {
      if (m[1].toLowerCase() === 'srcset') {
        for (const item of m[3].split(',')) result.push(item.trim().split(/\s+/)[0]);
      } else result.push(m[3]);
    }
  }
  for (const m of text.matchAll(/url\(\s*["']?([^\s"')]+)["']?\s*\)/gi)) result.push(m[1]);
  for (const m of text.matchAll(/@import\s+["']([^"']+)["']/gi)) result.push(m[1]);
  return result.filter(Boolean).map(ref => ref.replace(/&amp;|&#0*38;/g, '&'));
}
