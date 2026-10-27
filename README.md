# Coahuila Automotive & Manufacturing Connect · sitio

Sitio del evento del 9 y 10 de junio de 2027 en el Expo Center Laguna, Torreón.
Lo organizan ANAPSA y ADN de la Manufactura.

- **Sitio oficial:** https://coahuilamfgconnect.com, con hosting cPanel (LiteSpeed). Aquí el registro es real.
- **Vista previa:** https://tennibel.github.io/coahuila-mfg-connect/, en GitHub Pages. El registro es solo de muestra y no tiene Analytics.

El historial de decisiones está en `../agente/bitacora.md` y lo que falta del cliente en `../agente/pendientes.md`.

## Cómo está hecho

| Parte | Tecnología | Dónde |
|---|---|---|
| Páginas | Astro 6 estático + Tailwind 4 | `src/pages/`, `src/layouts/Layout.astro` |
| Contenido del evento | TypeScript / YAML | `src/content/evento.ts`, `src/content/settings.yaml` |
| SEO, colores, textos legales | | `src/theme.config.ts` |
| Registro, QR, correos y panel | PHP 8 + MySQL | `servidor/` |
| Analítica | Google Analytics 4 (`G-SX7H6133Q4`) | `Layout.astro`, solo en el dominio |

## Las dos versiones

| | Vista previa (GitHub Pages) | Dominio |
|---|---|---|
| Se genera con | `npm run build`, o sola con cada push a `main` | `scripts/build-dominio.sh` → `dist-dominio/` |
| Ruta base | `/coahuila-mfg-connect/` | `/` |
| Formulario de registro | Vista previa: no guarda nada | Real: `PUBLIC_REGISTRO_API=/api/registro.php` |
| Google Analytics | No | Sí: `PUBLIC_GA_ID` |
| PHP del registro y `.htaccess` | No | Se copian desde `servidor/` y se generan en el build |

**Cada push a `main` publica la vista previa.**

## Publicar en el dominio

```bash
./scripts/publicar-dominio.sh
```

El script genera la versión del dominio y la sube completa por FTPS con `lftp`.

- **Servidor:** se conecta a `priva268.spindns.com` y no a `ftp.coahuilamfgconnect.com`, porque el certificado TLS está a nombre del servidor.
- **Usuario:** `admin@coahuilamfgconnect.com`.
- **Contraseña:** se lee del Llavero de macOS (servicio `coahuila-ftp`). La primera vez el script la pide y la guarda ahí.

> ⚠️ **Sube siempre el sitio completo** (`subir-ftp.sh` sin argumentos). Si cambias estilos, Astro genera un CSS con nombre nuevo (`_astro/index.XXXX.css`). Si solo subes `index.html`, el sitio se ve roto (pasó el 27-oct-2026). Después de publicar, revisa que todos los `/_astro/*` y `/media/*` del HTML respondan 200.

`subir-ftp.sh archivo1 archivo2` sube solo esos archivos. Úsalo solo para cambios de PHP o de imágenes que no tocan el HTML ni el CSS.

`.htaccess` lo escribe `build-dominio.sh`. Hace lo siguiente:
- redirige a HTTPS sin www;
- sirve la 404 propia y activa `DirectoryIndex index.html index.php`;
- define la caché: 30 días para medios, CSS y JS, y 5 minutos para el HTML;
- agrega las cabeceras de seguridad: HSTS, nosniff, X-Frame-Options, Referrer-Policy y Permissions-Policy.

## Registro de visitantes

Así funciona `servidor/`:

1. **Envío del formulario.** `/registro/` manda un `fetch` POST a `api/registro.php`. La API:
   - valida los campos y quita caracteres de control;
   - descarta bots con un campo trampa;
   - revisa que la petición venga del propio sitio;
   - limita a 8 envíos por hora por IP (guarda la IP como hash).
2. **Registro nuevo.**
   - Se guarda en MySQL con un folio.
   - El visitante recibe un correo de confirmación con el estilo del sitio y su QR.
   - Se asigna un responsable y se le manda un aviso.
3. **Correo ya registrado.** Solo se reenvía el QR a ese correo y la respuesta no trae folio ni QR, para que nadie vea el boleto de otra persona.
4. **QR.** `qr.php` dibuja el PNG solo si la firma es válida.
   - Contenido: `CMC2027:FOLIO:FIRMA`.
   - Firma: HMAC-SHA256 con la clave de la configuración (primeros 20 caracteres).
   - Librería: `chillerlan/php-qrcode` v5, en `_registro/vendor`.

### Configuración e instalación

- **Configuración.** `~/registro-config.php` está fuera de `public_html` y tiene la conexión a la base, la clave de firma y el remitente. No va en el repo.
- **Base de datos.** MySQL `coahuilamfgconne_registro` en cPanel. Tablas: `registros`, `usuarios`, `intentos`, `ajustes` y `responsables`.
- **Cambios de tablas.** `actualizar_esquema()` las actualiza sola al conectarse; la versión del esquema se guarda en `ajustes`.
- **Instalar desde cero** (ya hecho; solo sirve en un hosting nuevo):
  1. Corre `./scripts/build-dominio.sh --instalar`. Muestra una clave de un solo uso.
  2. Sube el sitio.
  3. Abre `/instalar.php` y captura la base de datos y el primer usuario del panel.
  4. El instalador borra su clave al terminar.
- **Carpeta privada.** `_registro/` tiene un `.htaccess` que niega el acceso desde la web.

### Panel `/admin/`

- **Registros:**
  - lista, filtros y contadores por tipo de empresa y por responsable;
  - columna «Lo atiende» para reasignar el registro, que avisa a la persona nueva;
  - botón **Descargar Excel**: un CSV UTF-8 con BOM que incluye a quién se asignó cada registro.
- **Responsables:** reparto por turnos.
  - Cada registro nuevo va al siguiente responsable activo, en orden de alta, y al final vuelve a empezar. El turno se guarda en `ajustes.ultimo_responsable`.
  - Se puede pausar, activar, quitar o agregar responsables. Si quitas a uno, sus registros quedan sin asignar.
  - El aviso llega al responsable con copia a `registro@coahuilamfgconnect.com`. Al contestarlo, la respuesta va al visitante.
  - Los cinco iniciales están en `RESPONSABLES_INICIALES`, en `_registro/lib.php`.
- **Usuarios:** altas y bajas de quienes entran al panel.
- **Sesión:**
  - cookie segura;
  - protección CSRF en los formularios;
  - vence tras 8 horas sin uso.

### Correo

- **Envío.** Usa `mail()` de PHP desde `registro@coahuilamfgconnect.com`.
- **DNS.** Ya están publicados:
  - SPF: `+a +mx +ip4 +include:relay.mailchannels.net`;
  - DKIM: `default._domainkey`;
  - DMARC: `p=none`.
- **Error en cPanel.** «Provide at least one "policy" argument» en Email Deliverability es una falla de esa pantalla, no del dominio. No uses «Repair».

### Probar en local

1. Usa PHP con SQLite.
2. Pon la configuración de prueba en `.pruebas-registro/`, que git ignora.
3. Apunta a ella con la variable `REGISTRO_CONFIG`.

## Analítica y privacidad

- **Dónde carga.** La etiqueta de Google va al inicio del `<head>`, y solo cuando existe `PUBLIC_GA_ID`, es decir, en el dominio.
- **`gtag` global.** Se escribe con `set:html` y no con `define:vars`, porque `define:vars` encierra el script en una función y `gtag` no quedaría global.
- **Aviso de privacidad.** Explica el uso de Analytics y cómo desactivarlo. No hay banner de consentimiento.
- **Fecha del aviso.** Está en `theme.legal.actualizado`; cámbiala cada vez que se modifique el aviso.

## Fechas a vigilar

- **1 de enero de 2027:** termina la etapa gratis (`etapas.finGratis` en `evento.ts`, `FIN_GRATIS` en `lib.php`).
  - El contador se apaga solo.
  - Hay que cambiar el texto de la banda «Gratis hasta el 31 de diciembre».
  - Hay que rehacer `public/og-portada.jpg`.

## Desarrollo

```bash
npm install
npm run dev                  # http://localhost:4321/coahuila-mfg-connect/
npm run build && npm run servir
npm run revisar -- http://localhost:4321/coahuila-mfg-connect/
```

Los originales de imágenes y videos (Higgsfield, Nano Banana y fotos de banco) están en `../agente/higgsfield/`. Las imágenes generadas con IA llevan `data-ia="higgsfield"` y aparecen en `/creditos`.
