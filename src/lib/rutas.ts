/**
 * rutas.ts — enlaces internos que respetan la ruta base (GitHub Pages en
 * /coahuila-mfg-connect/ hoy, "/" en el dominio propio mañana).
 * Nunca escribir href="/algo" a mano: usar ruta('algo/').
 */
const BASE = import.meta.env.BASE_URL.endsWith('/') ? import.meta.env.BASE_URL : `${import.meta.env.BASE_URL}/`;

export function ruta(destino = ''): string {
  return BASE + destino.replace(/^\//, '');
}

/** URL absoluta (canonical, og:image, JSON-LD). */
export function absoluta(destino = '', site: URL | string | undefined): string {
  return new URL(ruta(destino), site ?? 'https://tennibel.github.io').href;
}
