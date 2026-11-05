import {createServer} from 'node:http';
import {readFile, stat} from 'node:fs/promises';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {root, inside, fileFor} from './static-utils.mjs';
const types = {'.html':'text/html; charset=utf-8','.css':'text/css; charset=utf-8','.js':'text/javascript; charset=utf-8','.json':'application/json','.svg':'image/svg+xml','.png':'image/png','.webp':'image/webp','.jpg':'image/jpeg','.jpeg':'image/jpeg','.woff':'font/woff','.woff2':'font/woff2','.xml':'application/xml','.txt':'text/plain; charset=utf-8'};
export function staticServer(directory = path.join(root, 'dist')) {
  return createServer(async (req, res) => {
    if (!['GET','HEAD'].includes(req.method)) { res.writeHead(405); res.end(); return; }
    try {
      const url = new URL(req.url, 'http://127.0.0.1');
      let file = inside(directory, fileFor(url.pathname));
      let status = 200;
      try {
        if ((await stat(file)).isDirectory()) { res.writeHead(308, {Location:url.pathname + '/' + url.search}); res.end(); return; }
      } catch { file = inside(directory, '404.html'); status = 404; }
      const data = await readFile(file);
      res.writeHead(status, {'Content-Type':types[path.extname(file)] || 'application/octet-stream','Cache-Control':'no-store','X-Content-Type-Options':'nosniff'});
      res.end(req.method === 'HEAD' ? undefined : data);
    } catch { res.writeHead(400); res.end('Bad request'); }
  });
}
if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const server = staticServer();
  server.listen(8081, '127.0.0.1', () => console.log('Static preview: http://127.0.0.1:8081 (Ctrl+C to stop)'));
}
