#!/usr/bin/env node
/**
 * og-image.mjs — genera public/og-image.svg (1200 × 630) con el mapa del hero
 * y el texto en trazos (las fuentes del sitio). En el build, Astro lo pasa a
 * PNG (src/pages/og-image.png.ts). Correr de nuevo si cambia el texto:
 *   node scripts/og-image.mjs
 */
import { spawnSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { construirMapa } from '../src/lib/mapa.mjs';

const C = { navy: '#00112F', onNavy: '#F4F7FB', muted: '#A9BBD3', cian: '#01F6FA' };
const m = construirMapa();

const lineas = [
  { texto: 'Coahuila Automotive &', fuente: 'hubot', ejes: { wdth: 112, wght: 800 }, tamano: 64, x: 64, y: 150, tracking: -0.01 },
  { texto: 'Manufacturing Connect', fuente: 'hubot', ejes: { wdth: 112, wght: 800 }, tamano: 64, x: 64, y: 222, tracking: -0.01 },
  { texto: '9 y 10 de junio de 2027', fuente: 'atkinson', ejes: { wght: 600 }, tamano: 34, x: 64, y: 330 },
  { texto: 'Centro de Convenciones Torreón', fuente: 'atkinson', ejes: { wght: 400 }, tamano: 30, x: 64, y: 378 },
  { texto: 'Compradores y proveedores de México, Estados Unidos y Canadá', fuente: 'atkinson', ejes: { wght: 400 }, tamano: 24, x: 64, y: 560 },
];
const r = spawnSync('python3', ['scripts/texto-a-trazos.py'], { input: JSON.stringify(lineas), encoding: 'utf8' });
if (r.status !== 0) {
  console.error(r.stderr);
  process.exit(1);
}
const trazos = JSON.parse(r.stdout);
const colores = [C.onNavy, C.onNavy, C.cian, C.onNavy, C.muted];

// Mapa a la derecha: alto 600, Torreón en x = 1000.
const escala = 560 / m.alto;
const dx = 1000 - m.torreon.x * escala;
const dy = 40;
const tx = 1000;
const ty = dy + m.torreon.y * escala;

const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">
  <rect width="1200" height="630" fill="${C.navy}"/>
  <g transform="translate(${dx.toFixed(1)} ${dy}) scale(${escala.toFixed(4)})" fill="none" stroke="${C.muted}" stroke-opacity="0.55" stroke-width="${(1.2 / escala).toFixed(2)}" stroke-linejoin="round">
    <path d="${m.contornos}"/>
    ${m.trazos.map((t) => `<g transform="translate(${t.x} ${t.y}) rotate(${t.angulo})"><line x1="0" y1="0" x2="${t.largo}" y2="0" stroke="${C.onNavy}" stroke-opacity="1" stroke-width="${(2 / escala).toFixed(2)}"/></g>`).join('')}
  </g>
  <line x1="${tx}" y1="${ty.toFixed(1)}" x2="${tx}" y2="630" stroke="${C.cian}" stroke-width="2"/>
  <circle cx="${tx}" cy="${ty.toFixed(1)}" r="7" fill="${C.cian}"/>
  ${trazos.map((t, i) => `<path d="${t.d}" fill="${colores[i]}"/>`).join('\n  ')}
</svg>
`;
writeFileSync('public/og-image.svg', svg);
console.log(`public/og-image.svg (${(svg.length / 1024).toFixed(1)} KB)`);
