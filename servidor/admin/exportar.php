<?php
/** /admin/exportar.php — descarga los registros (con los filtros activos) en CSV para Excel. */
declare(strict_types=1);
require __DIR__ . '/../_registro/lib.php';

if (!usuario_actual()) { header('Location: ./'); exit; }
$f = filtros_desde_get();
[$donde, $params] = filtros_sql($f);
$q = bd()->prepare(SQL_REGISTROS . " $donde ORDER BY r.creado ASC, r.id ASC");
$q->execute($params);

cabeceras_seguras();
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="registros-coahuila-mfg-' . gmdate('Y-m-d') . '.csv"');

// Celdas que empiezan con = + - @ se anteponen con ' para que Excel no las ejecute como fórmula.
$celda = fn($s) => preg_match('/^[=+\-@\t\r]/', (string) $s) ? "'" . $s : (string) $s;
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel abre bien los acentos
fputcsv($out, ['Fecha (hora de Torreón)', 'Folio', 'Nombre', 'Apellidos', 'Correo', 'Teléfono', 'Empresa', 'Cargo', 'Tipo de empresa', 'Área', 'País', 'Etapa', 'Correo de confirmación enviado', 'Lo atiende', 'Correo de quien lo atiende'], ',', '"', '');
while ($r = $q->fetch()) {
    fputcsv($out, array_map($celda, [
        fecha_local($r['creado']), $r['folio'], $r['nombre'], $r['apellidos'], $r['correo'], $r['telefono'], $r['empresa'],
        $r['cargo'], $r['tipo_empresa'], $r['area'], $r['pais'], $r['etapa'], $r['correo_enviado'] ? 'Sí' : 'No', $r['atiende'] ?? 'Sin asignar', $r['atiende_correo'] ?? '',
    ]), ',', '"', '');
}
fclose($out);
