/**
 * iconos.ts - Genera en el build los íconos que un SVG no cubre.
 *
 * Fuente única: public/favicon.svg (el logo del cliente) y public/og-image.svg.
 * Así el favicon .ico, el apple-touch-icon, los íconos del manifest y la imagen
 * para compartir en WhatsApp/Facebook nunca se desfasan del logo.
 */
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';
import sharp from 'sharp';

const PUBLIC = join(process.cwd(), 'public');

export async function svgAPng(
  archivo: string,
  ancho: number,
  alto = ancho,
  opciones: { fondo?: string; margen?: number } = {}
): Promise<Buffer> {
  const svg = await readFile(join(PUBLIC, archivo));
  const margen = opciones.margen ?? 0;
  const interior = Math.round(ancho * (1 - margen * 2));
  const interiorAlto = Math.round(alto * (1 - margen * 2));
  let img = sharp(svg, { density: 384 }).resize(interior, interiorAlto, {
    fit: 'contain',
    background: { r: 0, g: 0, b: 0, alpha: 0 },
  });
  if (margen > 0 || opciones.fondo) {
    const base = await img.png().toBuffer();
    img = sharp({
      create: {
        width: ancho,
        height: alto,
        channels: 4,
        background: opciones.fondo ?? { r: 0, g: 0, b: 0, alpha: 0 },
      },
    }).composite([{ input: base, gravity: 'center' }]);
  }
  return img.png({ compressionLevel: 9 }).toBuffer();
}

/** Archivo .ico con PNG embebidos (16, 32 y 48 px). */
export async function crearIco(archivo: string): Promise<Buffer> {
  const tamanos = [16, 32, 48];
  const pngs = await Promise.all(tamanos.map((t) => svgAPng(archivo, t)));
  const cabecera = Buffer.alloc(6);
  cabecera.writeUInt16LE(0, 0); // reservado
  cabecera.writeUInt16LE(1, 2); // tipo: ícono
  cabecera.writeUInt16LE(pngs.length, 4);
  const entradas = Buffer.alloc(16 * pngs.length);
  let desplazamiento = 6 + entradas.length;
  pngs.forEach((png, i) => {
    const o = i * 16;
    entradas.writeUInt8(tamanos[i], o); // ancho
    entradas.writeUInt8(tamanos[i], o + 1); // alto
    entradas.writeUInt8(0, o + 2); // paleta
    entradas.writeUInt8(0, o + 3); // reservado
    entradas.writeUInt16LE(1, o + 4); // planos
    entradas.writeUInt16LE(32, o + 6); // bits por pixel
    entradas.writeUInt32LE(png.length, o + 8);
    entradas.writeUInt32LE(desplazamiento, o + 12);
    desplazamiento += png.length;
  });
  return Buffer.concat([cabecera, entradas, ...pngs]);
}

export function respuestaBinaria(cuerpo: Buffer, tipo: string): Response {
  return new Response(new Uint8Array(cuerpo), {
    headers: { 'Content-Type': tipo, 'Cache-Control': 'public, max-age=604800' },
  });
}
