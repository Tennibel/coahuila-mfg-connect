<?php
/** /admin/usuarios.php — quién puede entrar al panel: agregar, cambiar contraseña o quitar. */
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
    if ($accion === 'agregar' || $accion === 'clave') {
        $correo = mb_strtolower(trim((string) ($_POST['correo'] ?? '')));
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $clave = (string) ($_POST['clave'] ?? '');
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $error = 'Escriba un correo válido.';
        elseif (mb_strlen($clave) < 10) $error = 'La contraseña debe tener al menos 10 caracteres.';
        elseif ($accion === 'agregar') {
            try {
                $pdo->prepare('INSERT INTO usuarios (correo, nombre, clave_hash, creado) VALUES (?, ?, ?, ?)')
                    ->execute([$correo, $nombre ?: $correo, password_hash($clave, PASSWORD_DEFAULT), ahora()]);
                $mensaje = "Listo: $correo ya puede entrar.";
            } catch (PDOException $e) {
                $error = 'Ese correo ya tiene acceso.';
            }
        } else {
            $pdo->prepare('UPDATE usuarios SET clave_hash = ? WHERE correo = ?')->execute([password_hash($clave, PASSWORD_DEFAULT), $correo]);
            $mensaje = "Contraseña actualizada para $correo.";
        }
    } elseif ($accion === 'quitar') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $usuario['id']) $error = 'No puede quitarse a sí mismo.';
        else { $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]); $mensaje = 'Acceso quitado.'; }
    }
}

$lista = $pdo->query('SELECT id, correo, nombre, ultimo_acceso FROM usuarios ORDER BY correo')->fetchAll();
$csrf = '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
panel_inicio('Usuarios', $usuario);
echo '<h1>Usuarios del panel</h1>';
if ($mensaje) echo '<p class="ok">' . h($mensaje) . '</p>';
if ($error) echo '<p class="aviso">' . h($error) . '</p>';
echo '<div class="tabla"><table><thead><tr><th>Nombre</th><th>Correo</th><th>Último acceso</th><th></th></tr></thead><tbody>';
foreach ($lista as $u) {
    echo '<tr><td>' . h($u['nombre']) . '</td><td>' . h($u['correo']) . '</td><td>' . ($u['ultimo_acceso'] ? h(fecha_local($u['ultimo_acceso'])) : '—') . '</td><td>';
    if ((int) $u['id'] !== (int) $usuario['id']) {
        echo '<form method="post" class="quitar">' . $csrf
            . '<input type="hidden" name="accion" value="quitar"><input type="hidden" name="id" value="' . (int) $u['id'] . '"><button class="boton boton--sec">Quitar</button></form>';
    }
    echo '</td></tr>';
}
echo '</tbody></table></div>';
echo '<h2>Agregar usuario o cambiar contraseña</h2><form class="filtros" method="post">' . $csrf
    . '<label>Nombre<input name="nombre"></label><label>Correo<input type="email" name="correo" required></label>'
    . '<label>Contraseña (10+ caracteres)<input type="password" name="clave" required minlength="10" autocomplete="new-password"></label>'
    . '<button class="boton" name="accion" value="agregar">Agregar</button><button class="boton boton--sec" name="accion" value="clave">Cambiar contraseña</button></form>';
echo '<script>document.querySelectorAll("form.quitar").forEach(f=>f.addEventListener("submit",e=>{if(!confirm("¿Quitar el acceso a este usuario?"))e.preventDefault();}));</script>';
panel_fin();
