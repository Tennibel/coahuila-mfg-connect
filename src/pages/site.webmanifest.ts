/** /site.webmanifest — nombre, colores e íconos (respeta la ruta base). */
import type { APIRoute } from 'astro';
import theme from '../theme.config';
import settings from '../content/settings';
import { ruta } from '../lib/rutas';

export const prerender = true;

export const GET: APIRoute = () =>
  new Response(
    JSON.stringify({
      name: settings.negocio.nombre,
      short_name: settings.negocio.nombreCorto,
      start_url: ruta(''),
      display: 'browser',
      background_color: '#F4F7FB',
      theme_color: theme.colorTema,
      icons: [
        { src: ruta('icono-192.png'), sizes: '192x192', type: 'image/png' },
        { src: ruta('icono-512.png'), sizes: '512x512', type: 'image/png' },
      ],
    }),
    { headers: { 'Content-Type': 'application/manifest+json' } }
  );
