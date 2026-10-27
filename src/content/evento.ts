/**
 * evento.ts — textos del sitio. Todo sale de agente/hechos.json (con su
 * fuente). Por indicación de Cori (2026-10-26) el sitio no muestra pendientes:
 * lo que falta está en agente/pendientes.md y se agrega cuando llegue.
 *
 * Las preguntas con `pendiente` muestran la marca «[pendiente: …]» y NO entran
 * al JSON-LD de FAQPage (solo van las que tienen respuesta completa).
 */

/**
 * Boleto de visitante por etapas (Cori, 2026-10-26): registro gratis hasta el
 * 31 de diciembre de 2026; desde el 1 de enero de 2027 tiene costo. Por
 * indicación de Cori no se muestra el precio de la etapa 2, solo que tendrá costo. `finGratis` es la medianoche del 1 de enero en Torreón (UTC−6).
 */
export const etapas = {
  finGratis: Date.UTC(2027, 0, 1, 6),
  gratis: { rotulo: 'Etapa 1', fechas: 'Hasta el 31 de diciembre de 2026', precio: 'Gratis' },
  conCosto: { rotulo: 'Etapa 2', fechas: 'Desde el 1 de enero de 2027' },
};

export type Pregunta = {
  pregunta: string;
  /** Respuesta en HTML sencillo (párrafos y enlaces). */
  respuesta: string;
  pendiente?: string;
};

export const preguntas: Pregunta[] = [
  {
    pregunta: '¿Cuándo y dónde es el evento?',
    respuesta:
      'El 9 y 10 de junio de 2027 en el Expo Center Laguna, en Torreón, Coahuila. Las visitas industriales propuestas son el 8 de junio de 2027.',
  },
  {
    pregunta: '¿Cuánto cuesta el boleto de visitante?',
    respuesta:
      'Es por etapas. Si se registra a más tardar el 31 de diciembre de 2026, su registro es gratis. Desde el 1 de enero de 2027 el boleto tiene costo.',
  },
  {
    pregunta: '¿Cómo pago mi boleto?',
    respuesta:
      'Hasta el 31 de diciembre de 2026 no hay nada que pagar. Desde el 1 de enero de 2027 el boleto tendrá costo; daremos a conocer la forma de pago en este sitio.',
  },
  {
    pregunta: '¿Cuándo recibo mi QR de acceso?',
    respuesta:
      'Le llega por correo en cuanto se registra, uno por persona. También lo ve en pantalla al terminar su registro.',
  },
  {
    pregunta: '¿El boleto de visitante incluye citas B2B?',
    respuesta:
      'Las citas B2B son para expositores: para tener agenda de citas es indispensable exponer. Si le interesa, escríbanos a <a href="mailto:info@coahuilamfgconnect.com">info@coahuilamfgconnect.com</a>.',
  },
  {
    pregunta: '¿Puedo ir a las visitas industriales?',
    respuesta:
      'Las visitas son un beneficio exclusivo para expositores, con cupo limitado. Son propuestas y están sujetas a la autorización y disponibilidad de las plantas.',
  },
];
