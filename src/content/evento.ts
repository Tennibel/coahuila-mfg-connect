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

/**
 * Hoteles sede (Cori, 2026-11-25), de la cotización de MileniuM Grupo Hotelero
 * Mexicano del 18-nov-2026 a Fernando Díaz: bloqueo abierto del 8 al 10 de
 * junio. Por indicación de Cori el sitio no muestra los precios, solo que hay
 * tarifas exclusivas. Direcciones de las fichas de IHG.
 */
export const hoteles = {
  fechas: 'Del 8 al 10 de junio de 2027',
  nota: 'Tarifas sujetas a disponibilidad. Para conocerlas y reservar, contacte al hotel:',
  reservaciones: {
    empresa: 'MileniuM Grupo Hotelero Mexicano',
    nombre: 'Ana Ramírez',
    telefono: '871 729 9600',
    telefonoHref: '+528717299600',
    correo: 'ana.ramirez@hotelesmilenium.com',
  },
  lista: [
    {
      nombre: 'Holiday Inn Express Torreón',
      direccion: 'Blvd. Independencia 1133, Col. Granjas San Isidro, C.P. 27000, Torreón, Coahuila',
      mapsUrl: 'https://www.google.com/maps/search/?api=1&query=Holiday+Inn+Express+Torre%C3%B3n+Blvd.+Independencia+1133',
      incluye: 'Incluye Barra de Desayuno Express en cortesía, internet y lavandería de autoservicio.',
    },
    {
      nombre: 'Crowne Plaza Torreón',
      direccion: 'Blvd. Torreón-Matamoros 4050, Col. Ex Hacienda Antigua Los Ángeles, C.P. 27260, Torreón, Coahuila',
      mapsUrl: 'https://www.google.com/maps/search/?api=1&query=Crowne+Plaza+Torre%C3%B3n+Blvd.+Torre%C3%B3n-Matamoros+4050',
      incluye: 'Plan europeo: sin desayuno.',
    },
  ],
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
