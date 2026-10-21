/**
 * /apple-touch-icon.png — 180×180 sobre fondo marino (iOS rellena de negro lo
 * transparente y no acepta SVG aquí). Generado desde public/favicon.svg.
 */
import type { APIRoute } from 'astro';
import { svgAPng, respuestaBinaria } from '../lib/iconos';

export const prerender = true;

export const GET: APIRoute = async () =>
  respuestaBinaria(await svgAPng('favicon.svg', 180, 180, { fondo: '#00112F', margen: 0 }), 'image/png');
