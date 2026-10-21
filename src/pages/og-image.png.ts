/**
 * /og-image.png — la imagen que aparece al compartir el sitio en WhatsApp,
 * Facebook o LinkedIn (1200×630). Esas plataformas no muestran SVG, así que se
 * genera en el build desde public/og-image.svg.
 */
import type { APIRoute } from 'astro';
import { svgAPng, respuestaBinaria } from '../lib/iconos';

export const prerender = true;

export const GET: APIRoute = async () =>
  respuestaBinaria(await svgAPng('og-image.svg', 1200, 630), 'image/png');
