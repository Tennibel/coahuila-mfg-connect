/**
 * settings.ts — Carga y valida los datos del evento (settings.yaml).
 * Si el YAML queda inválido, el build falla con un mensaje claro.
 */
import { z } from 'astro/zod';
import { parse } from 'yaml';
import raw from './settings.yaml?raw';

const settingsSchema = z.object({
  negocio: z.object({
    nombre: z.string(),
    nombreCorto: z.string(),
    descripcionCorta: z.string(),
  }),
  contacto: z.object({
    whatsappNumber: z.string().regex(/^(\d{8,15})?$/, 'Solo dígitos, sin + ni espacios'),
    whatsappDefaultMessage: z.string(),
    email: z.string().email(),
    phone: z.string(),
  }),
  sede: z.object({
    nombre: z.string(),
    ciudad: z.string(),
    estado: z.string(),
    calle: z.string(),
    cp: z.string(),
    mapsUrl: z.union([z.string().url(), z.literal('')]),
  }),
  redes: z.array(z.object({ red: z.string(), url: z.string().url() })),
});

export type Settings = z.infer<typeof settingsSchema>;

const parsed = settingsSchema.safeParse(parse(raw));
if (!parsed.success) {
  throw new Error(
    `[settings.yaml] Datos inválidos:\n${parsed.error.issues.map((i) => `  - ${i.path.join('.')}: ${i.message}`).join('\n')}`
  );
}

const settings: Settings = parsed.data;
export default settings;
