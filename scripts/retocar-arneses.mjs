#!/usr/bin/env node
/**
 * retocar-arneses.mjs — tapa los dos parches bordados de las camisas en
 * 06-arneses (imagen IA) para que no se lean como logo de una marca: copia tela
 * de la misma camisa justo debajo y la funde con un desenfoque suave.
 * Entrada: agente/higgsfield/06-arneses.png → salida: src/assets/ia/06-arneses.png
 */
import sharp from 'sharp';

const ORIGEN = '../agente/higgsfield/06-arneses.png';
const DESTINO = 'src/assets/ia/06-arneses.png';
// [x, y, ancho, alto, desplazamiento vertical de la tela que se copia]
const PARCHES = [
  [283, 398, 32, 24, 26],
  [580, 230, 20, 20, 22],
];

const base = sharp(ORIGEN);
const capas = [];
for (const [x, y, w, h, dy] of PARCHES) {
  const tela = await sharp(ORIGEN).extract({ left: x, top: y + dy, width: w, height: h }).blur(1.2).png().toBuffer();
  // Máscara con bordes suaves para que no se note el recorte.
  const mascara = Buffer.from(
    `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}"><defs><filter id="f"><feGaussianBlur stdDeviation="2.5"/></filter></defs><rect x="3" y="3" width="${w - 6}" height="${h - 6}" rx="4" fill="#fff" filter="url(#f)"/></svg>`
  );
  const conMascara = await sharp(tela).ensureAlpha().composite([{ input: mascara, blend: 'dest-in' }]).png().toBuffer();
  capas.push({ input: conMascara, left: x, top: y });
}
await base.composite(capas).png().toFile(DESTINO);
console.log('listo:', DESTINO);
