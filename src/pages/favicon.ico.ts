/** /favicon.ico — generado en el build desde public/favicon.svg (ver lib/iconos.ts). */
import type { APIRoute } from 'astro';
import { crearIco, respuestaBinaria } from '../lib/iconos';

export const prerender = true;

export const GET: APIRoute = async () => respuestaBinaria(await crearIco('favicon.svg'), 'image/x-icon');
