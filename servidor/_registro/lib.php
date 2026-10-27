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

    $html = '<!doctype html><html lang="es-MX"><body style="margin:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#0b1b33">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:6px;overflow:hidden">'
        . '<tr><td style="background:#00112f;padding:24px 28px;color:#f4f7fb;font-size:20px;font-weight:bold">Coahuila Automotive &amp; Manufacturing Connect</td></tr>'
        . '<tr><td style="padding:28px">'
        . '<p style="font-size:16px;margin:0 0 12px">Hola ' . h($r['nombre']) . ':</p>'
        . '<p style="font-size:16px;line-height:1.5;margin:0 0 20px">Su registro como visitante quedó <strong>confirmado</strong>. Presente este código QR en el acceso.</p>'
        . '<p style="text-align:center;margin:0 0 8px"><img src="' . h($qr) . '" width="240" height="240" alt="Código QR de acceso, folio ' . h($r['folio']) . '" style="border:1px solid #d8e0ea"></p>'
        . '<p style="text-align:center;font-size:18px;font-weight:bold;letter-spacing:2px;margin:0 0 24px">' . h($r['folio']) . '</p>'
        . '<p style="font-size:15px;line-height:1.5;margin:0 0 6px"><strong>Fecha:</strong> 9 y 10 de junio de 2027</p>'
        . '<p style="font-size:15px;line-height:1.5;margin:0 0 20px"><strong>Lugar:</strong> Expo Center Laguna, Blvd. Independencia y Libramiento Periférico Raúl López Sánchez, C.P. 27018, Torreón, Coahuila</p>'
        . '<p style="font-size:14px;line-height:1.5;color:#4a5b70;margin:0">Si no ve el código, ábralo aquí: <a href="' . h($qr) . '">' . h($qr) . '</a><br>Dudas: <a href="mailto:info@coahuilamfgconnect.com">info@coahuilamfgconnect.com</a></p>'
        . '</td></tr></table></td></tr></table></body></html>';

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
        return true;
    }
    return mail($r['correo'], mb_encode_mimeheader($asunto, 'UTF-8'), $cuerpo, implode("\r\n", $cabeceras), '-f' . $de);
}

// ---------- Sesión del panel ----------

function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('cmc_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/admin/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
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
