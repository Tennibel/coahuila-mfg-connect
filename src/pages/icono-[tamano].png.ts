/** /icono-192.png y /icono-512.png — íconos del manifest, desde public/favicon.svg. */
import type { APIRoute, GetStaticPaths } from 'astro';
import { svgAPng, respuestaBinaria } from '../lib/iconos';

export const prerender = true;

export const getStaticPaths: GetStaticPaths = () => [
  { params: { tamano: '192' } },
  { params: { tamano: '512' } },
];

export const GET: APIRoute = async ({ params }) => {
  const t = Number(params.tamano);
  return respuestaBinaria(await svgAPng('favicon.svg', t, t, { fondo: '#00112F', margen: 0 }), 'image/png');
};
