import http from 'node:http';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import './build.mjs';
const root = path.resolve('dist');
const types = {'.html':'text/html; charset=utf-8','.css':'text/css','.js':'text/javascript','.jpg':'image/jpeg','.png':'image/png'};
http.createServer(async (req,res) => {
  try {
    const pathname = decodeURIComponent(new URL(req.url,'http://localhost').pathname);
    // Never expose PHP source through the static preview server.
    if (pathname.startsWith('/api/') || /\.php(?:\/|$)/i.test(pathname)) {
      res.writeHead(503, {'Content-Type':'application/json','Cache-Control':'no-store'});
      return res.end(JSON.stringify({ok:false,message:'PHP backend is not running on this static preview.'}));
    }
    const file = path.resolve(root, '.' + (pathname.endsWith('/') ? pathname+'index.html' : pathname));
    if (!file.startsWith(root + path.sep)) { res.writeHead(403); return res.end('Forbidden'); }
    const data = await readFile(file); res.writeHead(200, {'Content-Type':types[path.extname(file)] || 'application/octet-stream'}); res.end(data);
  } catch { res.writeHead(404); res.end('Page not found'); }
}).listen(4173,'127.0.0.1',()=>console.log('Local: http://127.0.0.1:4173'));
