/**
 * theme.config.ts — Configuración técnica del sitio (la toca el desarrollador).
 *
 * El dominio y la ruta base salen de astro.config.mjs (SITE_URL y BASE_PATH):
 * aquí no se repiten. Lo que falta del cliente queda vacío y sale en las
 * páginas como «[pendiente: …]».
 */
import settings from './content/settings';

export type ThemeConfig = {
  microsite: string;
  /** Dominio definitivo del cliente (para los textos legales). */
  domain: string;
  colorTema: string;
  seo: { title: string; description: string; ogImage: string };
  tracking: { ga4: string; googleAdsId: string; metaPixelId: string };
  contact: typeof settings.contacto;
  legal: {
    responsable: string;
    domicilio: string;
    correoPrivacidad: string;
    actualizado: string;
  };
  /**
   * 'opt-in': la analítica espera a que el visitante acepte, en todas partes.
   * El público es de México, EE. UU. y Canadá; con opt-in no hay que distinguir.
   */
  consentimiento: 'regional' | 'opt-in';
  /** Botón flotante de WhatsApp: texto corto (2 a 4 palabras). Solo aparece cuando hay número. */
  whatsapp: { cta: string };
};

const theme: ThemeConfig = {
  microsite: 'coahuila-mfg-connect',
  domain: 'coahuilamfgconnect.com',
  colorTema: '#00112F',
  seo: {
    title: 'Coahuila MFG Connect · 9 y 10 de junio de 2027, Torreón',
    description:
      'Encuentro de manufactura entre compradores y proveedores de México, Estados Unidos y Canadá. 9 y 10 de junio de 2027, Centro de Convenciones Torreón.',
    // PNG generado en el build desde public/og-image.svg (WhatsApp y Facebook no muestran SVG).
    ogImage: 'og-image.png',
  },
  // Sin cuenta de GA4 todavía (pendiente del cliente). Mientras esté vacío no
  // se carga analítica ni aparece el banner de cookies.
  tracking: { ga4: '', googleAdsId: '', metaPixelId: '' },
  contact: settings.contacto,
  legal: {
    responsable: '', // pendiente: razón social del responsable (¿ANAPSA?)
    domicilio: '', // pendiente: domicilio del responsable
    correoPrivacidad: settings.contacto.email,
    actualizado: '2026-10-21',
  },
  consentimiento: 'opt-in',
  whatsapp: { cta: 'Pregunte por WhatsApp' },
};

export default theme;
