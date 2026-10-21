/**
 * evento.ts — textos del sitio. Todo sale de agente/hechos.json (con su
 * fuente); lo que falta del cliente se marca como pendiente y se muestra.
 *
 * Las preguntas con `pendiente` muestran la marca «[pendiente: …]» y NO entran
 * al JSON-LD de FAQPage (solo van las que tienen respuesta completa).
 */

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
    respuesta: 'El precio se publicará aquí en cuanto esté definido.',
    pendiente: 'precio del boleto y si hay preventa o tarifa para grupos',
  },
  {
    pregunta: '¿Cómo pago mi boleto?',
    respuesta:
      'En línea con Mercado Pago, con los medios activos en la cuenta del evento: tarjeta de crédito o débito, efectivo en tiendas de conveniencia, entre otros.',
  },
  {
    pregunta: '¿Cuándo recibo mi QR de acceso?',
    respuesta:
      'Le llega por correo, uno por persona, cuando el pago queda aprobado. Si paga en efectivo, recibe por correo las instrucciones de pago; si la ficha vence sin pagarse, el lugar se libera.',
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
