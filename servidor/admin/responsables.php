<?php
/**
 * /admin/responsables.php — equipo que atiende los registros.
 * Cada registro nuevo se asigna al siguiente activo, en orden, y al llegar al
 * último vuelve al primero. Pausar a alguien lo saca de la rotación sin perder
 * sus registros.
 */
declare(strict_types=1);
require __DIR__ . '/../_registro/lib.php';
require __DIR__ . '/../_registro/panel.php';

$usuario = usuario_actual();
if (!$usuario) { header('Location: ./'); exit; }
$pdo = bd();
$mensaje = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    revisar_csrf();
    $accion = (string) ($_POST['accion'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($accion === 'agregar') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $correo = mb_strtolower(trim((string) ($_POST['correo'] ?? '')));
        if ($nombre === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) $error = 'Escriba nombre y un correo válido.';
        else {
            try {
                $pdo->prepare('INSERT INTO responsables (nombre, correo, activo, creado) VALUES (?, ?, 1, ?)')->execute([$nombre, $correo, ahora()]);
                $mensaje = "$nombre entró a la rotación.";
            } catch (PDOException $e) {
                $error = 'Ese correo ya está en la lista.';
            }
        }
    } elseif ($accion === 'pausar' || $accion === 'activar') {
        $pdo->prepare('UPDATE responsables SET activo = ? WHERE id = ?')->execute([$accion === 'activar' ? 1 : 0, $id]);
        $mensaje = $accion === 'activar' ? 'Volvió a la rotación.' : 'En pausa: no recibirá registros nuevos.';
    } elseif ($accion === 'quitar') {
        $pdo->prepare('UPDATE registros SET responsable_id = NULL WHERE responsable_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM responsables WHERE id = ?')->execute([$id]);
        $mensaje = 'Se quitó de la lista; sus registros quedaron «Sin asignar».';
    }
}

$lista = $pdo->query('SELECT s.*, (SELECT COUNT(*) FROM registros r WHERE r.responsable_id = s.id) AS asignados FROM responsables s ORDER BY s.id')->fetchAll();
$turno = siguiente_responsable($pdo);
$csrf = '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
$boton = fn(int $id, string $accion, string $texto, string $clase = 'boton--sec') =>
    '<form method="post" class="' . ($accion === 'quitar' ? 'quitar' : '') . '">' . $csrf . '<input type="hidden" name="id" value="' . $id . '">'
    . '<button class="boton ' . $clase . '" name="accion" value="' . $accion . '">' . $texto . '</button></form>';

panel_inicio('Responsables', $usuario);
echo '<h1>Quién atiende los registros</h1>';
echo '<p class="estado">Cada registro nuevo se asigna al siguiente de la lista, en orden; al llegar al último vuelve al primero. '
    . 'A esa persona le llega un aviso con los datos y el correo <b>' . h(config()['correo_de']) . '</b> recibe copia de todos.</p>';
if ($mensaje) echo '<p class="ok">' . h($mensaje) . '</p>';
if ($error) echo '<p class="aviso">' . h($error) . '</p>';
echo '<div class="tabla"><table><thead><tr><th>Orden</th><th>Nombre</th><th>Correo</th><th>Registros asignados</th><th>Estado</th><th></th></tr></thead><tbody>';
$n = 0;
foreach ($lista as $s) {
    $n++;
    $esTurno = $turno && (int) $turno['id'] === (int) $s['id'];
    echo '<tr><td>' . $n . '</td><td>' . h($s['nombre']) . ($esTurno ? ' <b class="turno">siguiente en turno</b>' : '') . '</td><td>' . h($s['correo']) . '</td>'
        . '<td><a href="./?atiende=' . (int) $s['id'] . '">' . (int) $s['asignados'] . '</a></td>'
        . '<td>' . ($s['activo'] ? 'En rotación' : 'En pausa') . '</td><td class="acciones">'
        . ($s['activo'] ? $boton((int) $s['id'], 'pausar', 'Pausar') : $boton((int) $s['id'], 'activar', 'Activar'))
        . $boton((int) $s['id'], 'quitar', 'Quitar') . '</td></tr>';
}
echo '</tbody></table></div>';
echo '<h2>Agregar a la rotación</h2><form class="filtros" method="post">' . $csrf
    . '<label>Nombre<input name="nombre" required></label><label>Correo<input type="email" name="correo" required></label>'
    . '<button class="boton" name="accion" value="agregar">Agregar</button></form>';
echo '<script>document.querySelectorAll("form.quitar").forEach(f=>f.addEventListener("submit",e=>{if(!confirm("¿Quitar a esta persona? Sus registros quedarán sin asignar."))e.preventDefault();}));</script>';
panel_fin();
