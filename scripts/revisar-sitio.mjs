#!/usr/bin/env node
/**
 * revisar-sitio.mjs — revisa los requisitos verificables de un sitio en marcha.
 *
 * Uso:
 *   npm run build && node dist/server/entry.mjs      (en otra terminal)
 *   node scripts/revisar-sitio.mjs [url-base] [--sin-externos] [--https dominio.com]
 *
 * url-base: default http://localhost:4321
 * --sin-externos: no revisa enlaces a otros sitios (más rápido, sin red)
 * --https dominio: comprueba en producción que http redirige a https y que hay HSTS
 *
 * Revisa: páginas obligatorias (avisos, robots, sitemap, favicons, manifest,
 * og-image), 404 real, título/descripción/canonical por página (presencia,
 * largo y repetidos), un solo h1, alt e dimensiones en imágenes, datos
 * "[pendiente: …]" sin llenar, JSON-LD válido, botón de WhatsApp, campo
 * trampa en formularios y enlaces rotos (internos y externos).
 *
 * Lo que NO revisa (se hace aparte): velocidad (PageSpeed/Lighthouse),
 * contraste (axe), consentimiento de cookies (Playwright en zona Europe/Madrid
 * + Chrome real) y la revisión visual anti-IA.
 *
 * Sale con código 1 si hay errores.
 */
const args = process.argv.slice(2);
const BASE = (args.find((a) => /^https?:\/\//.test(a)) ?? 'http://localhost:4321').replace(/\/$/, '');
const SIN_EXTERNOS = args.includes('--sin-externos');
const iHttps = args.indexOf('--https');
const DOMINIO_HTTPS = iHttps >= 0 ? args[iHttps + 1] : null;

const errores = [];
const avisos = [];
const error = (m) => errores.push(m);
const aviso = (m) => avisos.push(m);

async function pedir(url, opciones = {}) {
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), opciones.timeout ?? 10000);
  try {
    return await fetch(url, { redirect: opciones.redirect ?? 'follow', method: opciones.method ?? 'GET', signal: ctrl.signal, headers: { 'User-Agent': 'pz-revisar-sitio/1.0' } });
  } finally {
    clearTimeout(t);
  }
}

const decodificar = (s) => s.replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>');
const atributo = (tag, nombre) => {
  const m = tag.match(new RegExp(`\\s${nombre}\\s*=\\s*("([^"]*)"|'([^']*)'|([^\\s>]+))`, 'i'));
  if (m) return decodificar(m[2] ?? m[3] ?? m[4] ?? '');
  // Atributo sin valor (<img alt>) equivale a vacío.
  return new RegExp(`\\s${nombre}(?=[\\s/>])`, 'i').test(tag) ? '' : null;
};
const textoVisible = (html) =>
  html.replace(/<script[\s\S]*?<\/script>/gi, ' ').replace(/<style[\s\S]*?<\/style>/gi, ' ').replace(/<[^>]+>/g, ' ');

// ---------- 1. Páginas y archivos obligatorios ----------
// Genérico: funciona con sitios del kit y con sitios a la medida. Las páginas
// legales se buscan por sus enlaces (no por un nombre fijo) y los íconos por
// las etiquetas <link> de la portada. BASE puede incluir una ruta base
// (p. ej. GitHub Pages: http://host/mi-repo).
const RAIZ_SITIO = BASE + '/';
let portada = '';
try {
  portada = (await (await pedir(RAIZ_SITIO)).text()).replace(/<!--[\s\S]*?-->/g, '');
} catch (e) {
  error(`portada: no respondió (${e.message})`);
}

async function exigir(url, etiqueta, { tipo = '', grave = true } = {}) {
  try {
    const r = await pedir(url);
    const ct = r.headers.get('content-type') ?? '';
    if (r.status !== 200) (grave ? error : aviso)(`${etiqueta}: ${url.replace(origenDe(url), '')} responde ${r.status}`);
    else if (tipo && !ct.includes(tipo)) aviso(`${etiqueta}: content-type "${ct}" (se esperaba ${tipo})`);
    return r;
  } catch (e) {
    (grave ? error : aviso)(`${etiqueta}: no respondió (${e.message})`);
    return null;
  }
}
function origenDe(u) { try { return new URL(u).origin; } catch { return ''; } }
const absoluta = (href) => new URL(href, RAIZ_SITIO).toString();
const enlacesPortada = [...portada.matchAll(/<a\b[^>]*href=["']([^"']+)["'][^>]*>([\s\S]*?)<\/a>/gi)].map((m) => ({ href: m[1], texto: m[2].replace(/<[^>]+>/g, ' ').toLowerCase() }));
const buscarLegal = (re) => enlacesPortada.find((e) => re.test(e.href) || re.test(e.texto));
const hayAnalitica = /googletagmanager|gtag\(|fbq\(|clarity\.ms/.test(portada);

const legales = [
  ['Aviso de privacidad', /privacidad|privacy/i, true],
  ['Aviso legal', /aviso-legal|aviso legal|t[ée]rminos|terminos|legal/i, true],
  ['Aviso de cookies', /cookies/i, hayAnalitica],
];
for (const [nombre, re, obligatorio] of legales) {
  const e = buscarLegal(re);
  if (!e) (obligatorio ? error : aviso)(`${nombre}: no hay enlace en la portada${obligatorio ? '' : ' (el sitio no parece usar analítica)'}`);
  else await exigir(absoluta(e.href), nombre);
}

// robots y sitemap
const robots = await exigir(BASE + '/robots.txt', 'robots.txt', { tipo: 'text/plain' });
if (robots?.ok) {
  const txt = await robots.text();
  if (!/^Sitemap:\s*https?:\/\//im.test(txt)) error('robots.txt no declara la URL del sitemap');
  if (/example\.com|ejemplo\.com/.test(txt)) aviso('robots.txt apunta a un dominio de ejemplo: falta SITE_URL');
}
const sm = await pedir(BASE + '/sitemap-index.xml').catch(() => null);
if (!sm || sm.status !== 200) await exigir(BASE + '/sitemap.xml', 'sitemap (sitemap-index.xml ni sitemap.xml)');

// Íconos, manifest e imagen para compartir, leídos de la portada
const linkHref = (re) => {
  const tag = portada.match(new RegExp(`<link\\b[^>]*rel=["'][^"']*${re}[^"']*["'][^>]*>`, 'i'))?.[0];
  return tag ? atributo(tag, 'href') : null;
};
const icono = linkHref('icon');
if (!icono) error('favicon: no hay <link rel="icon">');
else await exigir(absoluta(icono), 'favicon');
const apple = linkHref('apple-touch-icon');
if (!apple) aviso('sin apple-touch-icon (ícono al agregar a la pantalla de inicio del iPhone)');
else await exigir(absoluta(apple), 'apple-touch-icon', { grave: false });
await exigir(BASE + '/favicon.ico', 'favicon.ico (lo piden navegadores y Google)', { grave: false });
const manifest = linkHref('manifest');
if (!manifest) aviso('sin web manifest');
const ogTag = portada.match(/<meta\b[^>]*property=["']og:image["'][^>]*>/i)?.[0];
const og = ogTag ? atributo(ogTag, 'content') : null;
if (!og) error('sin og:image (vista previa al compartir en WhatsApp/Facebook)');
else {
  // Si apunta al dominio de producción y aún no responde, no se puede comprobar desde aquí.
  const externa = origenDe(absoluta(og)) !== new URL(BASE).origin;
  const r = await exigir(absoluta(og), externa ? 'og:image (dominio de producción, revisar al publicar)' : 'og:image', { tipo: 'image/', grave: !externa });
  if (r?.ok && /svg/.test(r.headers.get('content-type') ?? '')) error('og:image es SVG: WhatsApp y Facebook no lo muestran');
}

// ---------- 2. 404 real ----------
try {
  const r = await pedir(`${BASE}/esta-pagina-no-existe-${Date.now()}`);
  if (r.status !== 404) error(`Una ruta inexistente responde ${r.status}, no 404`);
  else if (!(await r.text()).includes('<nav')) aviso('La 404 no parece tener navegación del sitio');
} catch (e) {
  error(`404: no respondió (${e.message})`);
}

// ---------- 3. Recorrido de páginas ----------
const visitadas = new Map(); // ruta -> { titulo, descripcion }
const enlaces = new Map(); // url absoluta -> Set(páginas donde aparece)
const origen = new URL(BASE).origin;
const pendientes = [new URL(RAIZ_SITIO).pathname, ...legales.map(([, re]) => buscarLegal(re)).filter(Boolean).map((e) => new URL(absoluta(e.href)).pathname)];

while (pendientes.length && visitadas.size < 200) {
  const ruta = pendientes.shift();
  // /pagina y /pagina/ son la misma página.
  if (visitadas.has(ruta) || visitadas.has(ruta.replace(/\/$/, '')) || visitadas.has(ruta + '/')) continue;
  let html = '';
  try {
    const r = await pedir(origen + ruta);
    if (!(r.headers.get('content-type') ?? '').includes('text/html')) continue;
    if (r.status >= 400) {
      error(`${ruta}: responde ${r.status}`);
      visitadas.set(ruta, {});
      continue;
    }
    // Sin comentarios HTML: lo comentado no se ve ni cuenta.
    html = (await r.text()).replace(/<!--[\s\S]*?-->/g, '');
  } catch (e) {
    error(`${ruta}: no respondió (${e.message})`);
    continue;
  }

  const titulo = decodificar((html.match(/<title>([\s\S]*?)<\/title>/i)?.[1] ?? '').trim());
  const metaDesc = html.match(/<meta[^>]+name=["']description["'][^>]*>/i)?.[0];
  const descripcion = metaDesc ? atributo(metaDesc, 'content')?.trim() ?? '' : '';
  const noindex = /<meta[^>]+name=["']robots["'][^>]+noindex/i.test(html);
  visitadas.set(ruta, { titulo, descripcion, noindex });

  if (!titulo) error(`${ruta}: sin <title>`);
  else if (titulo.length > 60) aviso(`${ruta}: título de ${titulo.length} caracteres (máx. 60): "${titulo}"`);
  if (!descripcion) error(`${ruta}: sin meta description`);
  else if (descripcion.length > 160) aviso(`${ruta}: descripción de ${descripcion.length} caracteres (máx. 155-160)`);
  if (!/<link[^>]+rel=["']canonical["']/i.test(html)) error(`${ruta}: sin canonical`);
  if (!/<html[^>]+lang=/i.test(html)) error(`${ruta}: <html> sin lang`);
  if (!/<meta[^>]+name=["']viewport["']/i.test(html)) error(`${ruta}: sin meta viewport`);

  const h1s = html.match(/<h1[\s>]/gi)?.length ?? 0;
  if (h1s !== 1) error(`${ruta}: ${h1s} elementos h1 (debe haber exactamente 1)`);

  for (const img of html.match(/<img\b[^>]*>/gi) ?? []) {
    const src = atributo(img, 'src') ?? '?';
    if (atributo(img, 'alt') === null) error(`${ruta}: imagen sin alt (${src})`);
    else if (atributo(img, 'alt') === '' && /fetchpriority=["']?high|loading=["']?eager/i.test(img)) aviso(`${ruta}: la imagen principal tiene alt vacío; solo vale si es decorativa (${src})`);
    else if (/^(imagen|image|foto|photo|img)\s*\d*$/i.test(atributo(img, 'alt'))) aviso(`${ruta}: alt genérico "${atributo(img, 'alt')}" (${src})`);
    if (atributo(img, 'width') === null || atributo(img, 'height') === null) aviso(`${ruta}: imagen sin width/height (${src})`);
  }

  const visible = textoVisible(html);
  const pend = visible.match(/\[pendiente:[^\]]*\]/g);
  if (pend) error(`${ruta}: ${pend.length} dato(s) sin llenar: ${[...new Set(pend)].join(', ')}`);

  for (const bloque of html.match(/<script[^>]+application\/ld\+json[^>]*>([\s\S]*?)<\/script>/gi) ?? []) {
    try {
      JSON.parse(bloque.replace(/^<script[^>]*>/i, '').replace(/<\/script>$/i, ''));
    } catch {
      error(`${ruta}: JSON-LD inválido`);
    }
  }

  if (!/href=["']https:\/\/(wa\.me|api\.whatsapp\.com)\//i.test(html)) error(`${ruta}: sin enlace de WhatsApp`);

  for (const m of html.matchAll(/<form\b[\s\S]*?<\/form>/gi)) {
    const form = m[0];
    // Antispam: campo trampa (cualquier nombre habitual o tabindex -1) o captcha.
    const trampa = /<input\b[^>]*(tabindex=["']-1["']|name=["'](sitio_web|website|url|honeypot|trampa|hp_[\w-]*)["'])/i.test(form)
      || /cf-turnstile|g-recaptcha|h-captcha/i.test(form);
    if (!trampa) error(`${ruta}: formulario sin campo trampa ni captcha antispam`);
    // El aviso de privacidad debe estar junto al formulario: dentro o justo después.
    const alrededor = form + html.slice(m.index + form.length, m.index + form.length + 1500);
    if (!/privacidad|privacy/i.test(alrededor)) error(`${ruta}: formulario sin enlace al aviso de privacidad cerca`);
  }

  for (const a of html.match(/<a\b[^>]*>/gi) ?? []) {
    const href = atributo(a, 'href');
    if (!href || href.startsWith('#') || /^(mailto:|tel:|javascript:)/i.test(href)) continue;
    let url;
    try {
      url = new URL(href, origen + ruta);
    } catch {
      error(`${ruta}: enlace mal formado "${href}"`);
      continue;
    }
    url.hash = '';
    const clave = url.toString();
    if (!enlaces.has(clave)) enlaces.set(clave, new Set());
    enlaces.get(clave).add(ruta);
    if (url.origin === origen && !visitadas.has(url.pathname) && !url.pathname.startsWith('/api/')) {
      pendientes.push(url.pathname);
    }
  }
}

// Títulos y descripciones repetidos (solo páginas indexables)
const indexables = [...visitadas.entries()].filter(([, v]) => v.titulo && !v.noindex);
for (const campo of ['titulo', 'descripcion']) {
  const vistos = new Map();
  for (const [ruta, v] of indexables) {
    if (!v[campo]) continue;
    if (!vistos.has(v[campo])) vistos.set(v[campo], []);
    vistos.get(v[campo]).push(ruta);
  }
  for (const [valor, rutas] of vistos) {
    if (rutas.length > 1) aviso(`${campo} repetido en ${rutas.length} páginas (${rutas.slice(0, 4).join(', ')}): "${valor.slice(0, 70)}"`);
  }
}

// ---------- 4. Enlaces rotos ----------
const listaEnlaces = [...enlaces.keys()].filter((u) => !SIN_EXTERNOS || u.startsWith(origen));
const cola = [...listaEnlaces];
async function trabajador() {
  while (cola.length) {
    const url = cola.shift();
    const interno = url.startsWith(origen);
    try {
      let r = await pedir(url, { method: interno ? 'GET' : 'HEAD', timeout: 10000 });
      if (!interno && (r.status === 405 || r.status === 403)) r = await pedir(url, { timeout: 10000 });
      if (r.status >= 400) {
        const donde = [...enlaces.get(url)].slice(0, 3).join(', ');
        const msg = `enlace roto ${r.status}: ${url} (en ${donde})`;
        if (interno) error(msg);
        else if (r.status === 403 || r.status === 429 || r.status === 999) aviso(`${msg} — puede ser bloqueo a robots; revisar a mano`);
        else error(msg);
      }
    } catch (e) {
      (interno ? error : aviso)(`enlace sin respuesta: ${url} (${e.name === 'AbortError' ? 'tiempo agotado' : e.message})`);
    }
  }
}
await Promise.all(Array.from({ length: 6 }, trabajador));

// ---------- 5. HTTPS en producción ----------
if (DOMINIO_HTTPS) {
  try {
    const r = await pedir(`http://${DOMINIO_HTTPS}/`, { redirect: 'manual' });
    const destino = r.headers.get('location') ?? '';
    if (![301, 308].includes(r.status) || !destino.startsWith('https://')) {
      error(`http://${DOMINIO_HTTPS} responde ${r.status} → "${destino}" (debe ser 301 a https)`);
    }
    const s = await pedir(`https://${DOMINIO_HTTPS}/`);
    if (!s.headers.get('strict-transport-security')) aviso(`https://${DOMINIO_HTTPS} sin encabezado HSTS`);
  } catch (e) {
    error(`HTTPS: no se pudo comprobar ${DOMINIO_HTTPS} (${e.message})`);
  }
}

// ---------- Resultado ----------
console.log(`\nRevisión de ${BASE} — ${visitadas.size} páginas, ${listaEnlaces.length} enlaces${SIN_EXTERNOS ? ' (sin externos)' : ''}\n`);
for (const m of errores) console.log(`  ✗ ${m}`);
for (const m of avisos) console.log(`  ⚠ ${m}`);
if (!errores.length && !avisos.length) console.log('  ✓ Todo en orden');
console.log(`\n${errores.length} errores, ${avisos.length} avisos.`);
console.log('Falta aparte: PageSpeed móvil ≥ 90, contraste (axe), consentimiento en Chrome real, revisión visual anti-IA.\n');
process.exit(errores.length ? 1 : 0);
