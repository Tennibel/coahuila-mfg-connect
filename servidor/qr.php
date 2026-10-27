<?php
/** GET /qr.php?f=FOLIO&t=FIRMA — imagen PNG del QR de acceso (solo con la firma correcta). */
declare(strict_types=1);
require __DIR__ . '/_registro/lib.php';

$folio = (string) ($_GET['f'] ?? '');
$t = (string) ($_GET['t'] ?? '');
if (!preg_match('/^CMC-[A-Z0-9]{6}$/', $folio) || !hash_equals(firma($folio), $t)) {
    http_response_code(404);
    exit;
}
header('Content-Type: image/png');
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
echo png_qr($folio);
