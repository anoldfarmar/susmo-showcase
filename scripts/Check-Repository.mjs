// Pre-commit check: never reads ignored local runtimes, backups or the key directory.
import {execFileSync} from 'node:child_process';
import {readFile, lstat} from 'node:fs/promises';
import path from 'node:path';
import {root, inside} from './static-utils.mjs';
const files = execFileSync('git', ['-c', `safe.directory=${root.replaceAll('\\','/')}`, 'ls-files', '--cached', '--others', '--exclude-standard', '-z'], {cwd:root, encoding:'utf8'}).split('\0').filter(Boolean);
const privatePath = /^(?:\.local|key|backups|\.git)\/|(?:^|\/)\.env(?:\.|$)|(?:^|\/)wp-config\.php$|\.(?:sql(?:\.gz)?|pem|key|pfx|p12|log)$/i;
let textFiles = 0;
for (const name of new Set(files)) {
  if (privatePath.test(name)) throw new Error(`Private file in publish candidate list: ${name}`);
  const file = inside(root, name);
  const info = await lstat(file);
  if (info.isSymbolicLink()) throw new Error(`Review link before commit: ${name}`);
  if (info.size > 95 * 1024 * 1024) throw new Error(`File too large for normal Git publishing: ${name}`);
  if (!/\.(?:php|ps1|mjs|js|css|html|md|json|ya?ml|txt|xml|svg)$/i.test(name)) continue;
  const text = await readFile(file, 'utf8');
  // Print only the filename, never the matched value.
  if (/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----|\bAKIA[A-Z0-9]{16}\b|\bgh[pousr]_[A-Za-z0-9]{30,}\b|\bgithub_pat_[A-Za-z0-9_]{40,}\b/.test(text)) throw new Error(`Possible secret; inspect locally before committing: ${name}`);
  textFiles++;
}
console.log(`Repository check passed: ${new Set(files).size} tracked/unignored candidate files; ${textFiles} text files checked for common secret markers. Ignored private directories were not read. This is not a complete secret audit.`);
