/** /robots.txt — con la URL del sitemap del sitio (respeta la ruta base). */
import type { APIRoute } from 'astro';
import { absoluta } from '../lib/rutas';

export const prerender = true;

export const GET: APIRoute = ({ site }) =>
  new Response(`User-agent: *\nAllow: /\n\nSitemap: ${absoluta('sitemap-index.xml', site)}\n`, {
    headers: { 'Content-Type': 'text/plain; charset=utf-8' },
  });
