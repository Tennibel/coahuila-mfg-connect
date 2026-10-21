# pz-sites-base

Base común de las **Plantillas Z**: sitios Astro para clientes locales con contenido
editable por el cliente (via [Pages CMS](https://pagescms.org)) y blog integrado.

Cosechada de los micrositios (`04-micrositios/disenowebcdmx` + `desarrolloweb` + `_template`).
De aquí nacen las plantillas por vertical: `pz-restaurante`, `pz-hotel`, `pz-doctor`.

## La regla de oro

**Todo lo que el cliente puede editar vive en YAML/Markdown** dentro de `src/content/`.
**Todo lo técnico vive en `theme.config.ts`** (dominio, colores, tracking) y solo lo toca el desarrollador.

| Archivo | Quién lo edita | Qué contiene |
|---|---|---|
| `src/content/settings.yaml` | Cliente | Nombre, contacto, dirección, horarios, redes |
| `src/content/site.yaml` | Cliente | Textos de cada sección (hero, nosotros, FAQ, contacto...) |
| `src/content/blog/*.md` | Cliente | Posts del blog (uno por archivo) |
| `src/theme.config.ts` | Desarrollador | Dominio, colores, GA4/Pixel, SEO base |
| `.pages.yml` | Desarrollador | Qué campos ve el cliente en el panel |

Los YAML se validan con Zod en cada build (`src/content/site.ts` y `settings.ts`):
si el cliente rompe el formato, el build falla con mensaje claro y **no se publica un sitio roto**.

## Qué incluye

- Landing completa: Hero, Services, WhyUs, Projects, Testimonials, Plans, FAQ, ContactForm
- Sidebar del blog (categorías reales, horarios y CTA a WhatsApp)
- **Blog** desde Markdown local: `/blog`, `/blog/[slug]`, RSS (`/rss.xml`), sitemap, Schema.org Article
- Schema.org LocalBusiness desde settings (el prop `schemaType` del Layout permite Restaurant/Hotel/Physician)
- Formulario de leads (`/api/lead`): correo (SMTP) + webhook opcional, límite por IP, antispam
- Tracking: GA4 + Meta Pixel con consentimiento + eventos (cta_click, whatsapp_click, phone_click, generate_lead, scroll_75)
- Tailwind v4, accent color por sitio inyectado desde `theme.config.ts`
- **Kit de requisitos obligatorios** (ver abajo)

## Kit de requisitos (2026-10-11)

Lo que pide `10-agente-sitios/REQUISITOS.md` y ya viene resuelto en la base:

| Requisito | Dónde |
|---|---|
| Aviso legal, aviso de privacidad, aviso de cookies | `src/pages/aviso-*.astro` + `src/lib/legal.ts` (noindex, fuera del sitemap) |
| Banner de cookies con Aceptar / Rechazar | `src/components/CookieConsent.astro`; solo aparece si hay GA4, Ads o Pixel |
| Consentimiento antes de la analítica | `Layout.astro` (Consent Mode de Google + `fbq('consent')`) |
| HTTPS y encabezados de seguridad | `src/middleware.ts` (red de seguridad; lo principal es Force SSL en NPM) |
| Favicon .ico, apple-touch-icon, manifest, og-image PNG | `src/pages/*.ts` + `src/lib/iconos.ts`, generados desde `public/favicon.svg` y `public/og-image.svg` |
| Imágenes comprimidas | `scripts/optimizar-imagenes.mjs`, corre en el Dockerfile |
| Antispam en formularios | campo trampa + tiempo mínimo en `ContactForm.astro` y `api/lead.ts` |
| Botón de WhatsApp con CTA | `WhatsAppWidget.astro`; texto en `theme.whatsapp.cta` |
| Foco visible, movimiento reducido, textos que no se cortan | `src/styles/global.css` |
| Revisión automática | `npm run revisar` (ver abajo) |

**Al crear el sitio de un cliente, llenar en `theme.config.ts`:** `legal.responsable`,
`legal.domicilio`, `legal.actualizado` (AAAA-MM-DD) y, si aplica, `legal.correoPrivacidad`,
`whatsapp.cta` y `consentimiento`. Lo que falte sale como «[pendiente: …]» en los avisos y
`npm run revisar` lo marca como error. Si la vertical cambia los campos del formulario,
actualizar `datosFormulario` en `src/lib/legal.ts`.

**Fuera del código (manual):** Force SSL + HSTS en el Proxy Host de Nginx Proxy Manager,
la ficha de Google (la crea o verifica el cliente) y la cuenta de GA4.

### Revisar un sitio

```bash
SITE_URL=https://dominio.com npm run build
node dist/server/entry.mjs                     # en otra terminal
npm run revisar -- http://localhost:4321       # --sin-externos para no revisar enlaces externos
npm run revisar -- https://dominio.com --https dominio.com   # ya publicado: también http→https
```

Revisa páginas obligatorias, 404 real, títulos/descripciones (falta, largo, repetidos), un solo
h1, alt y dimensiones de imágenes, datos pendientes, JSON-LD, WhatsApp, antispam y enlaces
rotos. **No** revisa velocidad (PageSpeed), contraste (axe) ni el consentimiento en Chrome real.

### Trampas que este kit corrige (no repetir)

- **`define:vars` encierra el script en una función.** Un `function gtag(){}` declarado ahí
  no queda en `window`: GA4 recibía las visitas, pero `generate_lead`, `cta_click` y
  `scroll_75` nunca salían. Ahora se asigna `window.gtag` explícitamente.
- **Con `<ClientRouter />`, `astro:page-load` también se dispara en la carga inicial.** Llamar
  además las funciones al cargar el módulo duplicaba los listeners y cada clic contaba doble.
- **`TRUST_PROXY_HEADERS` no estaba en ningún sitio:** todos los visitantes compartían un solo
  contador de 5 envíos cada 10 minutos. Ahora se confía en el proxy por defecto.
- **En Alpine 3.21+ el paquete de fuentes es `font-dejavu`**, no `ttf-dejavu`.

## Desarrollo

```bash
npm install
npm run dev        # http://localhost:4321
SITE_URL=https://midominio.com npm run build
node dist/server/entry.mjs   # probar el build (PORT=4321 por default)
```

## Crear una nueva plantilla vertical

1. Copia esta carpeta (`cp -R pz-sites-base/ pz-vertical/`)
2. Ajusta `src/content/site.yaml` y `settings.yaml` con contenido demo del vertical
3. Agrega colecciones propias en `src/content.config.ts` (ej. `menu` en pz-restaurante)
4. Ajusta `Navbar`/`Footer` (enlaces) y `theme.config.ts` (accent, SEO)
5. Refleja los campos nuevos en `.pages.yml`

## Deploy

Igual que los micrositios: Docker en el VPS detrás de Nginx Proxy Manager
(ver `Dockerfile`, `docker-compose.prod.yml`, `deploy.sh`). El plan a futuro:
GitHub Actions construye la imagen en cada push y un webhook en el VPS la actualiza,
para que las ediciones del cliente en Pages CMS se publiquen solas (~3 min).
