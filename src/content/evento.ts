/**
 * evento.ts — textos del sitio. Todo sale de agente/hechos.json (con su
 * fuente); lo que falta del cliente se marca como pendiente y se muestra.
 *
 * Las preguntas con `pendiente` muestran la marca «[pendiente: …]» y NO entran
 * al JSON-LD de FAQPage (solo van las que tienen respuesta completa).
 */

/**
 * Boleto de visitante por etapas (Cori, 2026-10-26): registro gratis hasta el
 * 31 de diciembre de 2026; desde el 1 de enero de 2027 tiene costo (precio
 * pendiente). `finGratis` es la medianoche del 1 de enero en Torreón (UTC−6).
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
      'El 9 y 10 de junio de 2027 en el Centro de Convenciones Torreón, en Torreón, Coahuila. Las visitas industriales propuestas son el 8 de junio de 2027.',
  },
  {
    pregunta: '¿Cuánto cuesta el boleto de visitante?',
    respuesta:
      'Es por etapas. Si se registra a más tardar el 31 de diciembre de 2026, su registro es gratis. Desde el 1 de enero de 2027 el boleto tiene costo.',
    pendiente: 'precio desde el 1 de enero de 2027 y si hay tarifa para grupos',
  },
  {
    pregunta: '¿Cómo pago mi boleto?',
    respuesta:
      'Hasta el 31 de diciembre de 2026 no hay nada que pagar. Desde el 1 de enero de 2027, en línea con Mercado Pago, con los medios activos en la cuenta del evento: tarjeta de crédito o débito, efectivo en tiendas de conveniencia, entre otros.',
  },
  {
    pregunta: '¿Cuándo recibo mi QR de acceso?',
    respuesta:
      'Le llega por correo, uno por persona: al registrarse, si es en la etapa gratis, o cuando el pago queda aprobado. Si paga en efectivo, recibe por correo las instrucciones de pago; si la ficha vence sin pagarse, el lugar se libera.',
  },
  {
    pregunta: '¿El boleto de visitante incluye citas B2B?',
    respuesta:
      'Las citas B2B son para expositores: para tener agenda de citas es indispensable exponer. Si le interesa, escríbanos a <a href="mailto:info@coahuilamfgconnect.com">info@coahuilamfgconnect.com</a>.',
    pendiente: 'confirmar qué incluye el boleto de visitante (exposición, foro, stage)',
  },
  {
    pregunta: '¿Puedo ir a las visitas industriales?',
    respuesta:
      'Las visitas son un beneficio exclusivo para expositores, con cupo limitado. Son propuestas y están sujetas a la autorización y disponibilidad de las plantas.',
  },
  {
    pregunta: '¿Puedo pedir factura?',
    respuesta: 'Publicaremos aquí cómo solicitarla.',
    pendiente: 'si se factura el boleto y cómo se pide',
  },
  {
    pregunta: '¿Puedo cancelar, cambiar o transferir mi boleto?',
    respuesta: 'Las condiciones estarán en los términos del boleto.',
    pendiente: 'política de cambios, cancelación, reembolso y transferencia',
  },
  {
    pregunta: '¿Hay requisitos para entrar?',
    respuesta: 'Los requisitos de acceso se publicarán aquí.',
    pendiente: 'edad mínima, perfil profesional, gafete e identificación',
  },
];
