<?php
declare(strict_types=1);
require __DIR__ . '/../_registro/lib.php';
iniciar_sesion();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    revisar_csrf();
    $_SESSION = [];
    session_destroy();
}
header('Location: ./');
