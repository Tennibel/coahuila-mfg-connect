// @ts-check
/**
 * astro.config.mjs — Coahuila Automotive & Manufacturing Connect
 *
 * Sitio 100 % estático (sin servidor): hoy vive en GitHub Pages, en una
 * subcarpeta del repo. Para pasarlo al dominio propio basta con cambiar dos
 * variables al compilar, sin tocar código:
 *
 *   GitHub Pages (default):
 *     SITE_URL=https://tennibel.github.io  BASE_PATH=/coahuila-mfg-connect/
 *   Dominio propio:
 *     SITE_URL=https://coahuilamfgconnect.com  BASE_PATH=/
 *
 * BASE_PATH siempre con diagonal al final: sin ella se rompen el favicon y
 * og:image (trampa conocida, ver calibracion.md).
 */
import { defineConfig } from 'astro/config';
import tailwindcss from '@tailwindcss/vite';
import sitemap from '@astrojs/sitemap';

const SITE_URL = (process.env.SITE_URL || 'https://tennibel.github.io').replace(/\/$/, '');
let BASE_PATH = process.env.BASE_PATH || '/coahuila-mfg-connect/';
if (!BASE_PATH.startsWith('/')) BASE_PATH = `/${BASE_PATH}`;
if (!BASE_PATH.endsWith('/')) BASE_PATH = `${BASE_PATH}/`;

export default defineConfig({
  site: SITE_URL,
  base: BASE_PATH,
  trailingSlash: 'always',
  output: 'static',
  build: { format: 'directory' },
  integrations: [
    sitemap({
      // Fuera del sitemap: avisos legales y la vista previa del registro (noindex).
      filter: (page) => !/\/(aviso-de-privacidad|terminos|registro)\/?$/.test(page),
    }),
  ],
  vite: {
    plugins: [tailwindcss()],
  },
});
