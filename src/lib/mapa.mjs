/**
 * mapa.mjs — Norteamérica en proyección cónica conforme de Lambert, meridiano
 * central en la longitud de Torreón. Se ejecuta en el build (Astro) y produce
 * trazos SVG estáticos: en el navegador no corre nada de esto.
 *
 * Geometría: Natural Earth, Admin 0 – Countries, 1:50m (v4.1.0, vía el paquete
 * world-atlas 2.0.2). Dominio público.
 */
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { geoConicConformal, geoPath, geoCentroid, geoGraticule } from 'd3-geo';
import { feature } from 'topojson-client';
import { presimplify, simplify, quantile } from 'topojson-simplify';

const require = createRequire(import.meta.url);

/** INEGI, localidad 050350001 (ver agente/hechos.json). */
export const TORREON = { lat: 25.5417533, lon: -103.4530606 };
/** Origen del trazo de México: centro de la Ciudad de México (aprox.). */
const CDMX = [-99.1332, 19.4326];

const IDS = { CAN: '124', USA: '840', MEX: '484' };
// Centroides aproximados de Canadá y EE. UU. (México: CDMX, ver abajo). EE. UU.: el
// territorio continental contiguo (sin Alaska ni Hawái), para que el punto caiga
// dentro del país y no en el norte de Canadá.
const ANCHO = 1000;
const ALTO = 860;
/** Fracción del ancho del lienzo donde debe caer Torreón (ver Hero.astro). */
const FX = 0.515;
const Q = 0.15; // cuantil de simplificación (más bajo = más simple); 0.15 deja el SVG en ~30 KB
const AREA_MINIMA = 6; // px² en el lienzo de 1000 × 860

function sinHawai(geom) {
  if (geom.type !== 'MultiPolygon') return geom;
  return { ...geom, coordinates: geom.coordinates.filter((poly) => !(poly[0][0][0] < -150 && poly[0][0][1] < 30)) };
}
function contiguo(geom) {
  if (geom.type !== 'MultiPolygon') return geom;
  return { ...geom, coordinates: geom.coordinates.filter((poly) => poly[0][0][0] > -130 && poly[0][0][1] < 50) };
}

export function construirMapa() {
  const topo = JSON.parse(readFileSync(require.resolve('world-atlas/countries-50m.json'), 'utf8'));
  const pre = presimplify(topo);
  const simple = simplify(pre, quantile(pre, Q));
  const todos = feature(simple, simple.objects.countries).features;
  const paises = Object.fromEntries(
    Object.entries(IDS).map(([k, id]) => [k, todos.find((f) => String(f.id) === id)])
  );
  paises.USA = { ...paises.USA, geometry: sinHawai(paises.USA.geometry) };

  const proyeccion = geoConicConformal()
    .rotate([-TORREON.lon, 0])
    .parallels([20, 60])
    .center([0, 47])
    .scale(1)
    .translate([0, 0]);
  const coleccion = { type: 'FeatureCollection', features: Object.values(paises) };
  proyeccion.fitExtent([[10, 10], [ANCHO - 10, ALTO - 10]], coleccion);

  const ruta = geoPath(proyeccion).digits(1);
  // Fuera las islas diminutas: a esta escala son ruido y pesan.
  const area = geoPath(proyeccion).area;
  const limpiar = (f) => {
    const g = f.geometry;
    if (g.type !== 'MultiPolygon') return f;
    const coordinates = g.coordinates.filter((poly) => area({ type: 'Polygon', coordinates: poly }) > AREA_MINIMA);
    return { ...f, geometry: { ...g, coordinates } };
  };
  const contornos = Object.values(paises).map((f) => ruta(limpiar(f))).join('');

  // Paralelos de referencia cada 10°, solo dentro del encuadre.
  const reticula = ruta(geoGraticule().extentMajor([[-180, 10], [-40, 85]]).step([400, 10])());

  const [tx, ty] = proyeccion([TORREON.lon, TORREON.lat]);
  const origenes = {
    CAN: geoCentroid(paises.CAN),
    USA: geoCentroid({ type: 'Feature', geometry: contiguo(paises.USA.geometry) }),
    // México: el centroide caía a ~15 px de Torreón y el trazo no se leía.
    // Se usa el centro de la Ciudad de México (≈ 19.43° N, 99.13° O; ronda 1
    // de auditoría, ver agente/bitacora.md).
    MEX: CDMX,
  };
  const trazos = Object.entries(origenes).map(([pais, lonlat]) => {
    const [x, y] = proyeccion(lonlat);
    const dx = tx - x;
    const dy = ty - y;
    return {
      pais,
      x: +x.toFixed(1),
      y: +y.toFixed(1),
      largo: +Math.hypot(dx, dy).toFixed(1),
      angulo: +((Math.atan2(dy, dx) * 180) / Math.PI).toFixed(2),
    };
  });

  // El lienzo se ensancha a la derecha para que Torreón quede en la fracción
  // FX del ancho: así el punto cae justo en el meridiano de la retícula.
  const anchoVista = Math.max(ANCHO, tx / FX);

  return {
    ancho: +anchoVista.toFixed(1),
    fx: +(tx / anchoVista).toFixed(5),
    fy: +(ty / ALTO).toFixed(5),
    alto: ALTO,
    contornos,
    reticula,
    torreon: { x: +tx.toFixed(1), y: +ty.toFixed(1) },
    trazos,
  };
}
