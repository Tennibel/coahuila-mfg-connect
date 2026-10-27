/**
 * legal.ts — Datos compartidos por el aviso de privacidad y los términos.
 *
 * Los textos son una base para que el cliente los valide (no son asesoría
 * jurídica). Lo que falte sale como «[pendiente: …]» y `npm run revisar` lo marca.
 */
import theme from '../theme.config';
import settings from '../content/settings';

export const PENDIENTE = '[pendiente:';

export function dato(valor: string | undefined, etiqueta: string): string {
  const limpio = (valor ?? '').trim();
  return limpio || `${PENDIENTE} ${etiqueta}]`;
}

export function fechaActualizacion(): string {
  const valor = theme.legal.actualizado.trim();
  if (!/^\d{4}-\d{2}-\d{2}$/.test(valor)) return `${PENDIENTE} fecha de actualización]`;
  const [a, m, d] = valor.split('-').map(Number);
  return new Date(Date.UTC(a, m - 1, d)).toLocaleDateString('es-MX', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
  });
}

export const responsable = () => dato(theme.legal.responsable, 'razón social del responsable de los datos');
export const domicilio = () => dato(theme.legal.domicilio, 'domicilio del responsable');
export const correoPrivacidad = () => dato(theme.legal.correoPrivacidad, 'correo para datos personales');
export const nombreComercial = settings.negocio.nombre;

/**
 * Datos que pide el formulario de /registro. Si cambian los campos del
 * formulario, cambiar también esta lista (alimenta el aviso de privacidad).
 */
export const datosFormulario = [
  'nombre y apellidos',
  'correo electrónico',
  'teléfono',
  'empresa, cargo y tipo de empresa',
  'área en la que trabaja',
  'país',
];
