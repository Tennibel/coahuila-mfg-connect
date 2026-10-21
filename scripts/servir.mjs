#!/usr/bin/env node
/**
 * servir.mjs — sirve un sitio estático (carpeta dist/) para auditarlo.
 *
 * Uso: node scripts/servir.mjs <carpeta-dist> [puerto] [--base /ruta/]   (puerto default 4500)
 *
 * --base: para sitios compilados con una ruta base (p. ej. GitHub Pages en
 * /mi-repo/): se acepta la ruta con y sin ese prefijo.
 *
 * Resuelve /ruta → /ruta/index.html o /ruta.html, y responde la 404.html
 * del sitio con estado 404 real (como lo haría el servidor de producción).
 */
import { createServer } from 'node:http';
import { readFile, stat } from 'node:fs/promises';
import { join, extname, normalize, resolve } from 'node:path';

const RAIZ = resolve(process.argv[2] ?? 'dist');
const PUERTO = Number(process.argv[3] && !process.argv[3].startsWith('--') ? process.argv[3] : 4500);
const iBase = process.argv.indexOf('--base');
const BASE = iBase >= 0 ? '/' + process.argv[iBase + 1].replace(/^\/|\/$/g, '') : '';

const TIPOS = {
  '.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'text/javascript', '.mjs': 'text/javascript',
  '.json': 'application/json', '.webmanifest': 'application/manifest+json', '.xml': 'application/xml',
  '.txt': 'text/plain; charset=utf-8', '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg', '.webp': 'image/webp', '.avif': 'image/avif', '.gif': 'image/gif', '.ico': 'image/x-icon',
  '.woff': 'font/woff', '.woff2': 'font/woff2', '.ttf': 'font/ttf', '.mp4': 'video/mp4', '.webm': 'video/webm',
  '.pdf': 'application/pdf',
};

async function existe(ruta) {
  try { return (await stat(ruta)).isFile(); } catch { return false; }
}

createServer(async (req, res) => {
  let ruta = decodeURIComponent(new URL(req.url, 'http://x').pathname);
  if (BASE && (ruta === BASE || ruta.startsWith(BASE + '/'))) ruta = ruta.slice(BASE.length) || '/';
  const segura = normalize(ruta).replace(/^(\.\.[/\\])+/, '');
  const candidatos = [join(RAIZ, segura), join(RAIZ, segura, 'index.html'), join(RAIZ, `${segura.replace(/\/$/, '')}.html`)];
  for (const c of candidatos) {
    if (c.startsWith(RAIZ) && (await existe(c))) {
      res.writeHead(200, { 'Content-Type': TIPOS[extname(c).toLowerCase()] ?? 'application/octet-stream' });
      return res.end(await readFile(c));
    }
  }
  const p404 = join(RAIZ, '404.html');
  res.writeHead(404, { 'Content-Type': 'text/html; charset=utf-8' });
  res.end((await existe(p404)) ? await readFile(p404) : 'No encontrado');
}).listen(PUERTO, '127.0.0.1', () => console.log(`Sirviendo ${RAIZ} en http://127.0.0.1:${PUERTO}`));
