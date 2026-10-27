<?php
/**
 * POST /api/registro.php — guarda un registro de visitante y envía el correo
 * de confirmación con su QR. Responde JSON.
 */
declare(strict_types=1);
require __DIR__ . '/../_registro/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') responder(405, ['ok' => false, 'error' => 'Método no permitido.']);

// Solo desde el propio sitio.
$origen = $_SERVER['HTTP_ORIGIN'] ?? '';
try {
    $sitio = rtrim(config()['sitio'], '/');
} catch (Throwable $e) {
    responder(503, ['ok' => false, 'error' => 'El registro todavía no está disponible.']);
}
if ($origen !== '' && $origen !== $sitio && empty(config()['permitir_origen_local'])) {
    responder(403, ['ok' => false, 'error' => 'Origen no permitido.']);
}

$entrada = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($entrada)) $entrada = $_POST;
$v = fn(string $k): string => trim((string) ($entrada[$k] ?? ''));

// Campo trampa: si un robot lo llena, se responde como si nada.
if ($v('sitio_web') !== '') responder(200, ['ok' => true, 'folio' => null]);

if (!en_etapa_gratis()) {
    responder(409, ['ok' => false, 'error' => 'El registro gratis cerró el 31 de diciembre de 2026. El registro con pago abrirá pronto.']);
}

$errores = [];
$campos = [
    'nombre' => [2, 120], 'apellidos' => [2, 120], 'empresa' => [2, 160], 'cargo' => [2, 120],
];
foreach ($campos as $k => [$min, $max]) {
    $len = mb_strlen($v($k));
    if ($len < $min || $len > $max) $errores[$k] = 'Revise este campo.';
}
$correo = mb_strtolower($v('correo'));
if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 190) $errores['correo'] = 'Escriba un correo válido.';
if (!preg_match('/^[0-9 +()\-]{8,20}$/', $v('telefono'))) $errores['telefono'] = 'Escriba un teléfono de al menos 8 dígitos.';
if (!in_array($v('tipo'), TIPOS_EMPRESA, true)) $errores['tipo'] = 'Elija el tipo de empresa.';
if (!in_array($v('area'), AREAS, true)) $errores['area'] = 'Elija su área.';
if (!in_array($v('pais'), PAISES, true)) $errores['pais'] = 'Elija su país.';
if (empty($entrada['acepto'])) $errores['acepto'] = 'Confirme que leyó el aviso de privacidad.';
if ($errores) responder(422, ['ok' => false, 'error' => 'Revise los campos marcados.', 'campos' => $errores]);

try {
    $pdo = bd();
    if (!permitir('registro', 8, 60)) {
        responder(429, ['ok' => false, 'error' => 'Demasiados intentos desde esta conexión. Intente más tarde.']);
    }

    // Mismo correo: no se duplica; se reenvía su QR.
    $q = $pdo->prepare('SELECT * FROM registros WHERE correo = ?');
    $q->execute([$correo]);
    if ($previo = $q->fetch()) {
        $enviado = enviar_confirmacion($previo);
        responder(200, ['ok' => true, 'repetido' => true, 'folio' => $previo['folio'], 'qr' => url_qr($previo['folio']), 'correo_enviado' => $enviado]);
    }

    $r = [
        'nombre' => $v('nombre'), 'apellidos' => $v('apellidos'), 'correo' => $correo, 'telefono' => $v('telefono'),
        'empresa' => $v('empresa'), 'cargo' => $v('cargo'), 'tipo_empresa' => $v('tipo'), 'area' => $v('area'), 'pais' => $v('pais'),
    ];
    $ins = $pdo->prepare('INSERT INTO registros (folio, creado, nombre, apellidos, correo, telefono, empresa, cargo, tipo_empresa, area, pais, etapa, acepto_privacidad)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    for ($i = 0; $i < 5; $i++) {
        $r['folio'] = nuevo_folio();
        try {
            $ins->execute([$r['folio'], ahora(), $r['nombre'], $r['apellidos'], $r['correo'], $r['telefono'], $r['empresa'], $r['cargo'], $r['tipo_empresa'], $r['area'], $r['pais'], 'gratis', ahora()]);
            break;
        } catch (PDOException $e) {
            if ($i === 4) throw $e; // folio repetido (rarísimo): se reintenta con otro
        }
    }
    $enviado = enviar_confirmacion($r);
    if ($enviado) $pdo->prepare('UPDATE registros SET correo_enviado = 1 WHERE folio = ?')->execute([$r['folio']]);
    responder(201, ['ok' => true, 'folio' => $r['folio'], 'qr' => url_qr($r['folio']), 'correo_enviado' => $enviado]);
} catch (Throwable $e) {
    error_log('registro: ' . $e->getMessage());
    responder(500, ['ok' => false, 'error' => 'No pudimos guardar su registro. Intente de nuevo en unos minutos.']);
}
