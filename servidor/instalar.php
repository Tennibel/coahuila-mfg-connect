<?php
/**
 * /instalar.php — instalación de un solo uso del registro.
 *
 * Pide los datos de la base MySQL creada en cPanel y el primer usuario del
 * panel, crea las tablas y guarda la configuración FUERA de public_html.
 * Exige la clave de instalación que generó scripts/build-dominio.sh. Una vez
 * instalado, esta página ya no hace nada.
 */
declare(strict_types=1);
require __DIR__ . '/_registro/lib.php';
require __DIR__ . '/_registro/panel.php';

$archivoToken = __DIR__ . '/_registro/instalar-token.php';
if (is_file(ruta_config()) || !is_file($archivoToken)) {
    http_response_code(403);
    exit('El registro ya está instalado.');
}
$hashToken = require $archivoToken;
$error = '';
$p = fn(string $k, string $def = ''): string => trim((string) ($_POST[$k] ?? $def));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($hashToken, hash('sha256', $p('token')))) {
        $error = 'La clave de instalación no es correcta.';
    } elseif (!filter_var($p('admin_correo'), FILTER_VALIDATE_EMAIL) || mb_strlen((string) ($_POST['admin_clave'] ?? '')) < 10) {
        $error = 'Revise el correo del administrador y que la contraseña tenga al menos 10 caracteres.';
    } elseif (!filter_var($p('correo_de'), FILTER_VALIDATE_EMAIL)) {
        $error = 'Revise el correo remitente.';
    } else {
        $config = [
            'db' => [
                'dsn' => 'mysql:host=' . $p('db_host', 'localhost') . ';dbname=' . $p('db_nombre') . ';charset=utf8mb4',
                'user' => $p('db_usuario'),
                'pass' => (string) ($_POST['db_clave'] ?? ''),
            ],
            'secreto' => bin2hex(random_bytes(32)),
            'sitio' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'coahuilamfgconnect.com'),
            'correo_de' => $p('correo_de'),
            'correo_nombre' => 'Coahuila MFG Connect',
        ];
        try {
            $pdo = new PDO($config['db']['dsn'], $config['db']['user'], $config['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            crear_tablas($pdo);
            $pdo->prepare('INSERT INTO usuarios (correo, nombre, clave_hash, creado) VALUES (?, ?, ?, ?)')
                ->execute([mb_strtolower($p('admin_correo')), $p('admin_nombre') ?: $p('admin_correo'), password_hash((string) $_POST['admin_clave'], PASSWORD_DEFAULT), ahora()]);
            $php = "<?php\n// Configuración del registro (generada por instalar.php el " . gmdate('Y-m-d H:i') . " UTC). No compartir.\nreturn " . var_export($config, true) . ";\n";
            if (file_put_contents(ruta_config(), $php, LOCK_EX) === false) throw new RuntimeException('No se pudo escribir la configuración.');
            @chmod(ruta_config(), 0600);
            @unlink($archivoToken);
            panel_inicio('Instalado');
            echo '<div class="caja"><h1>Registro instalado</h1><p class="ok">La base de datos quedó lista y su usuario ya puede entrar.</p>'
                . '<p><a class="boton" href="/admin/">Ir al panel</a></p></div>';
            panel_fin();
            exit;
        } catch (Throwable $e) {
            $error = 'No se pudo conectar a la base de datos. Revise nombre, usuario y contraseña (' . h($e->getMessage()) . ').';
        }
    }
}

panel_inicio('Instalar');
echo '<div class="caja"><h1>Instalar el registro</h1>';
if ($error) echo '<p class="aviso">' . $error . '</p>';
echo '<form method="post" autocomplete="off">'
    . '<label>Clave de instalación<input name="token" required></label>'
    . '<h2>Base de datos (cPanel → MySQL)</h2>'
    . '<label>Nombre de la base<input name="db_nombre" required value="' . h($p('db_nombre')) . '" placeholder="usuario_registro"></label>'
    . '<label>Usuario de la base<input name="db_usuario" required value="' . h($p('db_usuario')) . '"></label>'
    . '<label>Contraseña de la base<input type="password" name="db_clave" required></label>'
    . '<label>Servidor<input name="db_host" value="' . h($p('db_host', 'localhost')) . '"></label>'
    . '<h2>Correo que envía las confirmaciones</h2>'
    . '<label>Correo remitente<input type="email" name="correo_de" required value="' . h($p('correo_de', 'registro@coahuilamfgconnect.com')) . '"></label>'
    . '<h2>Primer usuario del panel</h2>'
    . '<label>Nombre<input name="admin_nombre" value="' . h($p('admin_nombre')) . '"></label>'
    . '<label>Correo<input type="email" name="admin_correo" required value="' . h($p('admin_correo')) . '"></label>'
    . '<label>Contraseña (10+ caracteres)<input type="password" name="admin_clave" required minlength="10" autocomplete="new-password"></label>'
    . '<button class="boton">Instalar</button></form></div>';
panel_fin();
