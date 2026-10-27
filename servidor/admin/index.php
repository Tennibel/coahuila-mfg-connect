<?php
/** /admin/ — entrada con usuario y contraseña; lista de registros con filtros. */
declare(strict_types=1);
require __DIR__ . '/../_registro/lib.php';
require __DIR__ . '/../_registro/panel.php';

try { bd(); } catch (Throwable $e) { http_response_code(503); exit('El registro no está instalado todavía.'); }
iniciar_sesion();
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['entrar'])) {
    revisar_csrf();
    if (!permitir('login', 10, 15)) {
        $error = 'Demasiados intentos. Espere 15 minutos.';
    } else {
        $q = bd()->prepare('SELECT * FROM usuarios WHERE correo = ?');
        $q->execute([mb_strtolower(trim((string) $_POST['correo']))]);
        $u = $q->fetch();
        if ($u && password_verify((string) $_POST['clave'], $u['clave_hash'])) {
            session_regenerate_id(true);
            $_SESSION['usuario'] = ['id' => $u['id'], 'correo' => $u['correo'], 'nombre' => $u['nombre']];
            bd()->prepare('UPDATE usuarios SET ultimo_acceso = ? WHERE id = ?')->execute([ahora(), $u['id']]);
            header('Location: ./');
            exit;
        }
        $error = 'Correo o contraseña incorrectos.';
    }
}

$usuario = usuario_actual();
if (!$usuario) {
    panel_inicio('Entrar');
    echo '<div class="caja"><h1>Entrar al registro</h1>';
    if ($error) echo '<p class="aviso">' . h($error) . '</p>';
    echo '<form method="post"><input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">'
        . '<label>Correo<input type="email" name="correo" required autocomplete="username"></label>'
        . '<label>Contraseña<input type="password" name="clave" required autocomplete="current-password"></label>'
        . '<button class="boton" name="entrar" value="1">Entrar</button></form></div>';
    panel_fin();
    exit;
}

// Reasignar un registro a otra persona del equipo.
$pdo = bd();
$aviso = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['reasignar'])) {
    revisar_csrf();
    $folio = (string) ($_POST['folio'] ?? '');
    $nuevo = (string) ($_POST['responsable'] ?? '');
    $id = ctype_digit($nuevo) ? (int) $nuevo : null;
    $pdo->prepare('UPDATE registros SET responsable_id = ? WHERE folio = ?')->execute([$id, $folio]);
    if ($id && !empty($_POST['avisar'])) {
        $q = $pdo->prepare('SELECT * FROM registros WHERE folio = ?');
        $q->execute([$folio]);
        $s = $pdo->prepare('SELECT * FROM responsables WHERE id = ?');
        $s->execute([$id]);
        if (($r = $q->fetch()) && ($resp = $s->fetch())) enviar_aviso_responsable($r, $resp);
    }
    $aviso = "Registro $folio reasignado.";
}

$f = filtros_desde_get();
[$donde, $params] = filtros_sql($f);
$responsables = $pdo->query('SELECT * FROM responsables ORDER BY id')->fetchAll();
$total = (int) $pdo->query('SELECT COUNT(*) FROM registros')->fetchColumn();
$porTipo = $pdo->query('SELECT tipo_empresa, COUNT(*) n FROM registros GROUP BY tipo_empresa')->fetchAll(PDO::FETCH_KEY_PAIR);
$porResp = $pdo->query('SELECT responsable_id, COUNT(*) n FROM registros GROUP BY responsable_id')->fetchAll(PDO::FETCH_KEY_PAIR);
$hoy = $pdo->prepare('SELECT COUNT(*) FROM registros WHERE creado >= ?');
$hoy->execute([gmdate('Y-m-d H:i:s', time() - 86400)]);

$c = $pdo->prepare("SELECT COUNT(*) FROM registros r $donde");
$c->execute($params);
$filtrados = (int) $c->fetchColumn();
$porPagina = 50;
$pagina = max(1, (int) ($_GET['p'] ?? 1));
$paginas = max(1, (int) ceil($filtrados / $porPagina));
$pagina = min($pagina, $paginas);
$q = $pdo->prepare(SQL_REGISTROS . " $donde ORDER BY r.creado DESC, r.id DESC LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina));
$q->execute($params);
$filas = $q->fetchAll();
$turno = siguiente_responsable($pdo);

panel_inicio('Registros', $usuario);
echo '<h1>Registros de visitantes</h1>';
// Revisión rápida del servidor: el QR necesita GD y el correo, la función mail().
$gd = function_exists('imagecreatetruecolor');
$correo = function_exists('mail');
echo '<p class="estado">Generador de QR: <b>' . ($gd ? 'listo' : 'falta la extensión GD de PHP') . '</b> · Envío de correos: <b>' . ($correo ? 'listo' : 'no disponible') . '</b> · Remitente: <b>' . h(config()['correo_de']) . '</b> · Siguiente en turno: <b>' . h($turno['nombre'] ?? 'nadie activo') . '</b></p>';
if ($aviso) echo '<p class="ok">' . h($aviso) . '</p>';

echo '<ul class="cifras"><li><b>' . $total . '</b>registros en total</li><li><b>' . (int) $hoy->fetchColumn() . '</b>en las últimas 24 h</li>';
foreach (TIPOS_EMPRESA as $t) echo '<li><b>' . (int) ($porTipo[$t] ?? 0) . '</b>' . h($t) . '</li>';
echo '</ul>';
echo '<h2 class="subtitulo">Quién atiende</h2><ul class="cifras cifras--chicas">';
foreach ($responsables as $s) {
    echo '<li><a href="?atiende=' . (int) $s['id'] . '"><b>' . (int) ($porResp[$s['id']] ?? 0) . '</b>' . h($s['nombre']) . ($s['activo'] ? '' : ' (pausa)') . '</a></li>';
}
if (!empty($porResp[''])) echo '<li><a href="?atiende=ninguno"><b>' . (int) $porResp[''] . '</b>Sin asignar</a></li>';
echo '</ul>';

$qs = http_build_query(array_filter($f));
echo '<form class="filtros" method="get">'
    . '<label>Buscar<input type="search" name="q" value="' . h($f['q']) . '" placeholder="Nombre, empresa, correo o folio"></label>'
    . '<label>Tipo de empresa<select name="tipo"><option value="">Todos</option>';
foreach (TIPOS_EMPRESA as $t) echo '<option' . ($f['tipo'] === $t ? ' selected' : '') . '>' . h($t) . '</option>';
echo '</select></label><label>País<select name="pais"><option value="">Todos</option>';
foreach (PAISES as $p) echo '<option' . ($f['pais'] === $p ? ' selected' : '') . '>' . h($p) . '</option>';
echo '</select></label><label>Lo atiende<select name="atiende"><option value="">Todos</option>';
foreach ($responsables as $s) echo '<option value="' . (int) $s['id'] . '"' . ($f['atiende'] === (string) $s['id'] ? ' selected' : '') . '>' . h($s['nombre']) . '</option>';
echo '<option value="ninguno"' . ($f['atiende'] === 'ninguno' ? ' selected' : '') . '>Sin asignar</option>';
echo '</select></label><button class="boton boton--sec">Filtrar</button>'
    . '<a class="boton" href="exportar.php' . ($qs ? '?' . h($qs) : '') . '">Descargar Excel (' . $filtrados . ')</a></form>';

$csrf = '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
echo '<div class="tabla"><table><thead><tr><th>Fecha</th><th>Folio</th><th>Nombre</th><th>Lo atiende</th><th>Correo</th><th>Teléfono</th><th>Empresa</th><th>Cargo</th><th>Tipo</th><th>Área</th><th>País</th></tr></thead><tbody>';
if (!$filas) echo '<tr><td colspan="11">Sin registros con esos filtros.</td></tr>';
foreach ($filas as $r) {
    $opciones = '<option value="">Sin asignar</option>';
    foreach ($responsables as $s) {
        $opciones .= '<option value="' . (int) $s['id'] . '"' . ((int) $r['responsable_id'] === (int) $s['id'] ? ' selected' : '') . '>' . h($s['nombre']) . '</option>';
    }
    echo '<tr><td>' . h(fecha_local($r['creado'])) . '</td><td>' . h($r['folio']) . '</td><td>' . h($r['nombre'] . ' ' . $r['apellidos']) . '</td>'
        . '<td><form method="post" class="reasignar">' . $csrf . '<input type="hidden" name="folio" value="' . h($r['folio']) . '"><input type="hidden" name="reasignar" value="1"><input type="hidden" name="avisar" value="1">'
        . '<select name="responsable" aria-label="Quién atiende ' . h($r['folio']) . '">' . $opciones . '</select></form></td>'
        . '<td><a href="mailto:' . h($r['correo']) . '">' . h($r['correo']) . '</a></td><td>' . h($r['telefono']) . '</td><td>' . h($r['empresa']) . '</td><td>' . h($r['cargo'])
        . '</td><td>' . h($r['tipo_empresa']) . '</td><td>' . h($r['area']) . '</td><td>' . h($r['pais']) . '</td></tr>';
}
echo '</tbody></table></div>';
// Al cambiar quién atiende, se guarda y se le avisa por correo a la nueva persona.
echo '<script>document.querySelectorAll("form.reasignar select").forEach(s=>s.addEventListener("change",()=>{if(confirm("¿Reasignar este registro? Se le avisará por correo a quien lo atienda."))s.form.submit();else s.form.reset();}));</script>';
if ($paginas > 1) {
    echo '<p class="paginas">';
    if ($pagina > 1) echo '<a class="boton boton--sec" href="?' . h(http_build_query(array_filter($f) + ['p' => $pagina - 1])) . '">Anterior</a>';
    echo "Página $pagina de $paginas";
    if ($pagina < $paginas) echo '<a class="boton boton--sec" href="?' . h(http_build_query(array_filter($f) + ['p' => $pagina + 1])) . '">Siguiente</a>';
    echo '</p>';
}
panel_fin();
