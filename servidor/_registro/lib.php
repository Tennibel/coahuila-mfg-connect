<?php
/**
 * lib.php — núcleo del registro de visitantes de Coahuila MFG Connect.
 *
 * Vive en public_html/_registro/ (cerrado a la web por .htaccess). La
 * configuración con la contraseña de la base de datos se guarda FUERA de
 * public_html, en ~/registro-config.php, y la escribe instalar.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

// Los errores van al registro del servidor, nunca a la pantalla.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

const TIPOS_EMPRESA = ['Empresa compradora', 'Empresa proveedora', 'Empresa de servicios', 'Empresa patrocinadora'];
const AREAS = ['Compras', 'Desarrollo de proveedores', 'Ingeniería', 'Manufactura', 'Operaciones', 'Dirección de planta', 'Otra'];
const PAISES = ['México', 'Estados Unidos', 'Canadá', 'Otro'];
// Fin de la etapa gratis: medianoche del 1 de enero de 2027 en Torreón (UTC−6).
const FIN_GRATIS = '2027-01-01T06:00:00Z';

function ruta_config(): string
{
    $env = getenv('REGISTRO_CONFIG');
    if ($env) return $env;
    // public_html/_registro → dos niveles arriba es la carpeta del usuario del hosting.
    return dirname(__DIR__, 2) . '/registro-config.php';
}

function config(): array
{
    static $c = null;
    if ($c === null) {
        $ruta = ruta_config();
        if (!is_file($ruta)) {
            throw new RuntimeException('El registro no está instalado todavía.');
        }
        $c = require $ruta;
    }
    return $c;
}

function bd(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config()['db'];
        $pdo = new PDO($c['dsn'], $c['user'] ?? null, $c['pass'] ?? null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

/** Crea las tablas si no existen (MySQL en el hosting, SQLite para probar en local). */
function crear_tablas(PDO $pdo): void
{
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $sufijo = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $pdo->exec("CREATE TABLE IF NOT EXISTS registros (
        id $id,
        folio VARCHAR(16) NOT NULL UNIQUE,
        creado DATETIME NOT NULL,
        nombre VARCHAR(120) NOT NULL,
        apellidos VARCHAR(120) NOT NULL,
        correo VARCHAR(190) NOT NULL UNIQUE,
        telefono VARCHAR(30) NOT NULL,
        empresa VARCHAR(160) NOT NULL,
        cargo VARCHAR(120) NOT NULL,
        tipo_empresa VARCHAR(40) NOT NULL,
        area VARCHAR(60) NOT NULL,
        pais VARCHAR(40) NOT NULL,
        etapa VARCHAR(12) NOT NULL,
        acepto_privacidad DATETIME NOT NULL,
        correo_enviado TINYINT NOT NULL DEFAULT 0,
        asistio DATETIME NULL
    )$sufijo");
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id $id,
        correo VARCHAR(190) NOT NULL UNIQUE,
        nombre VARCHAR(120) NOT NULL,
        clave_hash VARCHAR(255) NOT NULL,
        creado DATETIME NOT NULL,
        ultimo_acceso DATETIME NULL
    )$sufijo");
    $pdo->exec("CREATE TABLE IF NOT EXISTS intentos (
        id $id,
        tipo VARCHAR(12) NOT NULL,
        ip_hash CHAR(64) NOT NULL,
        creado DATETIME NOT NULL
    )$sufijo");
}

function ahora(): string
{
    return gmdate('Y-m-d H:i:s');
}

function en_etapa_gratis(): bool
{
    return time() < strtotime(FIN_GRATIS);
}

/** La IP no se guarda tal cual: solo su huella, para limitar abusos. */
function ip_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return hash_hmac('sha256', $ip, config()['secreto']);
}

/** Cuenta intentos recientes y registra uno nuevo. Devuelve false si ya pasó el límite. */
function permitir(string $tipo, int $maximo, int $minutos): bool
{
    $pdo = bd();
    $desde = gmdate('Y-m-d H:i:s', time() - $minutos * 60);
    $pdo->prepare('DELETE FROM intentos WHERE creado < ?')->execute([gmdate('Y-m-d H:i:s', time() - 86400)]);
    $q = $pdo->prepare('SELECT COUNT(*) FROM intentos WHERE tipo = ? AND ip_hash = ? AND creado >= ?');
    $q->execute([$tipo, ip_hash(), $desde]);
    if ((int) $q->fetchColumn() >= $maximo) return false;
    $pdo->prepare('INSERT INTO intentos (tipo, ip_hash, creado) VALUES (?, ?, ?)')->execute([$tipo, ip_hash(), ahora()]);
    return true;
}

function nuevo_folio(): string
{
    $letras = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $f = '';
    for ($i = 0; $i < 6; $i++) $f .= $letras[random_int(0, strlen($letras) - 1)];
    return 'CMC-' . $f;
}

/** Firma del folio: el QR solo se puede pedir con ella (nadie adivina los de otros). */
function firma(string $folio): string
{
    return substr(hash_hmac('sha256', $folio, config()['secreto']), 0, 20);
}

function url_qr(string $folio): string
{
    return rtrim(config()['sitio'], '/') . '/qr.php?f=' . rawurlencode($folio) . '&t=' . firma($folio);
}

/** Lo que lleva el QR: el folio con su firma, para validarlo en el acceso. */
function contenido_qr(string $folio): string
{
    return 'CMC2027:' . $folio . ':' . firma($folio);
}

function png_qr(string $folio): string
{
    $opciones = new \chillerlan\QRCode\QROptions([
        'outputType' => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
        'outputBase64' => false,
        'scale' => 10,
        'quietzoneSize' => 2,
        'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
    ]);
    return (new \chillerlan\QRCode\QRCode($opciones))->render(contenido_qr($folio));
}

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Correo de confirmación (HTML + texto) con el folio y el QR. */
function enviar_confirmacion(array $r): bool
{
    $c = config();
    $de = $c['correo_de'];
    $nombreDe = $c['correo_nombre'] ?? 'Coahuila MFG Connect';
    $qr = url_qr($r['folio']);
    $sitio = rtrim($c['sitio'], '/');
    $asunto = 'Su registro a Coahuila MFG Connect · Folio ' . $r['folio'];

    $texto = "Hola {$r['nombre']}:\n\n"
        . "Su registro como visitante a Coahuila Automotive & Manufacturing Connect quedó confirmado.\n\n"
        . "Folio: {$r['folio']}\n"
        . "Fecha: 9 y 10 de junio de 2027\n"
        . "Lugar: Expo Center Laguna, Blvd. Independencia y Libramiento Periférico Raúl López Sánchez, C.P. 27018, Torreón, Coahuila\n\n"
        . "Presente su código QR en el acceso: $qr\n\n"
        . "Dudas: info@coahuilamfgconnect.com\n$sitio\n";

    $html = correo_html($r, $qr, $sitio);

    $limite = 'b' . bin2hex(random_bytes(8));
    $cabeceras = [
        'From: ' . mb_encode_mimeheader($nombreDe, 'UTF-8') . " <$de>",
        "Reply-To: info@coahuilamfgconnect.com",
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"$limite\"",
    ];
    $cuerpo = "--$limite\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($texto))
        . "--$limite\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html))
        . "--$limite--";

    if (!empty($c['correo_simulado'])) {
        // Pruebas en local: el correo se guarda en un archivo en vez de enviarse.
        file_put_contents($c['correo_simulado'], "Para: {$r['correo']}\nAsunto: $asunto\n\n$texto\n----\n", FILE_APPEND);
        file_put_contents($c['correo_simulado'] . '.html', $html);
        return true;
    }
    return mail($r['correo'], mb_encode_mimeheader($asunto, 'UTF-8'), $cuerpo, implode("\r\n", $cabeceras), '-f' . $de);
}

/**
 * HTML del correo con el estilo del sitio: fondo marino, acentos cian, logo
 * del evento y el QR en tarjeta blanca. Solo tablas y estilos en línea (lo que
 * Gmail, Outlook y Apple Mail respetan); el logo va en PNG porque los correos
 * no muestran SVG.
 */
function correo_html(array $r, string $qr, string $sitio): string
{
    $logo = $sitio . '/media/logos/coahuila-connect-correo.png';
    $fuente = "font-family:Arial,Helvetica,sans-serif";
    $rotulo = "$fuente;font-size:11px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#01f6fa";
    $dato = "$fuente;font-size:15px;line-height:22px;color:#f4f7fb";
    $fila = fn(string $etiqueta, string $valor) =>
        '<tr><td style="padding:14px 0;border-top:1px solid #1d3a6b">'
        . '<div style="' . $rotulo . '">' . $etiqueta . '</div>'
        . '<div style="' . $dato . ';padding-top:4px">' . $valor . '</div></td></tr>';

    return '<!doctype html><html lang="es-MX"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="dark light"><meta name="supported-color-schemes" content="dark light"><title>Registro confirmado</title></head>'
        . '<body style="margin:0;padding:0;background:#00112f">'
        . '<div style="display:none;max-height:0;overflow:hidden">Su folio es ' . h($r['folio']) . '. Presente su QR en el acceso del Expo Center Laguna.</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#00112f"><tr><td align="center" style="padding:28px 12px">'
        . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="width:100%;max-width:560px">'
        // Logo y filete cian
        . '<tr><td align="center" style="padding:8px 0 22px"><a href="' . h($sitio) . '"><img src="' . h($logo) . '" width="280" height="76" alt="Coahuila Automotive &amp; Manufacturing Connect" style="display:block;border:0;width:280px;height:auto"></a></td></tr>'
        . '<tr><td style="height:2px;line-height:2px;font-size:0;background:#01f6fa">&nbsp;</td></tr>'
        // Tarjeta principal
        . '<tr><td style="background:#00286a;padding:32px 28px 8px">'
        . '<div style="' . $rotulo . '">Registro confirmado</div>'
        . '<h1 style="' . $fuente . ';font-size:28px;line-height:32px;font-weight:900;color:#f4f7fb;margin:10px 0 14px">Hola, ' . h($r['nombre']) . '. Ya tiene su lugar.</h1>'
        . '<p style="' . $fuente . ';font-size:16px;line-height:24px;color:#a9bbd3;margin:0 0 24px">Su registro como visitante a <strong style="color:#f4f7fb">Coahuila Automotive &amp; Manufacturing Connect</strong> quedó confirmado. Presente este código QR en el acceso; puede mostrarlo en su celular o impreso.</p>'
        // QR en tarjeta blanca con borde cian
        . '<table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:0 auto 8px"><tr><td align="center" style="background:#ffffff;border:2px solid #01f6fa;border-radius:6px;padding:16px">'
        . '<img src="' . h($qr) . '" width="220" height="220" alt="Código QR de acceso, folio ' . h($r['folio']) . '" style="display:block;border:0;width:220px;height:220px">'
        . '</td></tr></table>'
        . '<p style="' . $fuente . ';text-align:center;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#a9bbd3;margin:14px 0 2px">Folio</p>'
        . '<p style="' . $fuente . ';text-align:center;font-size:24px;font-weight:bold;letter-spacing:3px;color:#01f6fa;margin:0 0 26px">' . h($r['folio']) . '</p>'
        // Datos del evento
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
        . $fila('Fecha', '9 y 10 de junio de 2027')
        . $fila('Lugar', 'Expo Center Laguna<br><span style="color:#a9bbd3">Blvd. Independencia y Libramiento Periférico Raúl López Sánchez, C.P. 27018, Torreón, Coahuila</span>')
        . $fila('Registrado', h($r['nombre'] . ' ' . $r['apellidos']) . '<br><span style="color:#a9bbd3">' . h($r['empresa']) . ' · ' . h($r['tipo_empresa']) . '</span>')
        . '</table>'
        // Botones
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0 28px"><tr>'
        . '<td style="background:#095ce4;border-radius:4px;border:1px solid #01f6fa"><a href="' . h($qr) . '" style="display:inline-block;padding:14px 22px;' . $fuente . ';font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none">Ver mi QR</a></td>'
        . '<td style="width:12px">&nbsp;</td>'
        . '<td style="border-radius:4px;border:1px solid #a9bbd3"><a href="' . h($sitio) . '" style="display:inline-block;padding:14px 22px;' . $fuente . ';font-size:15px;font-weight:bold;color:#f4f7fb;text-decoration:none">Ver el evento</a></td>'
        . '</tr></table>'
        . '</td></tr>'
        // Pie
        . '<tr><td style="padding:22px 28px 8px;' . $fuente . ';font-size:13px;line-height:20px;color:#a9bbd3;text-align:center">'
        . 'Dudas: <a href="mailto:info@coahuilamfgconnect.com" style="color:#01f6fa">info@coahuilamfgconnect.com</a><br>'
        . 'Recibe este correo porque se registró como visitante en <a href="' . h($sitio) . '" style="color:#01f6fa">coahuilamfgconnect.com</a>.'
        . '</td></tr></table></td></tr></table></body></html>';
}

// ---------- Sesión del panel ----------

function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('cmc_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/admin/',
        'secure' => !in_array($_SERVER['SERVER_NAME'] ?? '', ['127.0.0.1', 'localhost'], true),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
    // Sesión del panel: vence tras 8 horas sin uso.
    if (!empty($_SESSION['usuario']) && time() - ($_SESSION['visto'] ?? 0) > 8 * 3600) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['visto'] = time();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function usuario_actual(): ?array
{
    iniciar_sesion();
    return $_SESSION['usuario'] ?? null;
}

function revisar_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Sesión vencida. Recargue la página.');
    }
}

function cabeceras_seguras(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
}

// ---------- Utilidades del panel ----------

/** WHERE y parámetros para los filtros de la lista y de la descarga. */
function filtros_sql(array $f): array
{
    $w = [];
    $p = [];
    if ($f['q'] !== '') {
        $w[] = '(nombre LIKE ? OR apellidos LIKE ? OR empresa LIKE ? OR correo LIKE ? OR folio LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like, $like, $like);
    }
    if ($f['tipo'] !== '') { $w[] = 'tipo_empresa = ?'; $p[] = $f['tipo']; }
    if ($f['pais'] !== '') { $w[] = 'pais = ?'; $p[] = $f['pais']; }
    return [$w ? 'WHERE ' . implode(' AND ', $w) : '', $p];
}

/** Fecha guardada en UTC → hora de Torreón (UTC−6). */
function fecha_local(string $utc): string
{
    $d = new DateTime($utc, new DateTimeZone('UTC'));
    $d->setTimezone(new DateTimeZone('America/Monterrey'));
    return $d->format('d/m/Y H:i');
}
