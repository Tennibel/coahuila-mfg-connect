<?php
/** Piezas compartidas del panel de administración (cabecera, pie y estilos). */
declare(strict_types=1);

function panel_inicio(string $titulo, ?array $usuario = null): void
{
    cabeceras_seguras();
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es-MX"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>' . h($titulo) . ' · Registro Coahuila MFG Connect</title><style>' . PANEL_CSS . '</style></head><body>'
        . '<header class="barra"><div class="marco"><a class="marca" href="/admin/"><img src="/media/logos/coahuila-connect.svg" alt="Coahuila Automotive &amp; Manufacturing Connect" width="178" height="48"><span>Registro</span></a>';
    if ($usuario) {
        echo '<nav><a href="./">Registros</a><a href="usuarios.php">Usuarios</a>'
            . '<form method="post" action="salir.php"><input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '"><button>Salir</button></form></nav>';
    }
    echo '</div></header><main class="marco">';
}

function panel_fin(): void
{
    echo '</main></body></html>';
}

const PANEL_CSS = <<<'CSS'
:root{--marino:#00112f;--panel:#00286a;--azul:#095ce4;--cian:#01f6fa;--texto:#0b1b33;--gris:#4a5b70;--linea:#d8e0ea;--fondo:#f4f7fb}
*{box-sizing:border-box}body{margin:0;font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:var(--texto);background:var(--fondo)}
.marco{max-width:1200px;margin:0 auto;padding:0 16px}
.barra{background:var(--marino);color:#f4f7fb;border-bottom:2px solid var(--cian)}
.barra .marco{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;min-height:60px}
.barra nav{display:flex;gap:16px;align-items:center}.barra a{color:#f4f7fb}
.marca{display:flex;align-items:center;gap:14px;text-decoration:none}.marca img{height:44px;width:auto;display:block}
.marca span{font-size:.75rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--cian);border-left:1px solid #2a4a7d;padding-left:14px}
.barra .marco{min-height:72px}
.estado{font-size:.85rem;color:var(--gris);margin:0 0 8px}.estado b{color:var(--texto)}
.barra button{background:none;border:1px solid #a9bbd3;color:#f4f7fb;border-radius:4px;padding:6px 12px;cursor:pointer;font:inherit}
h1{font-size:1.6rem;margin:28px 0 8px}
.cifras{display:flex;flex-wrap:wrap;gap:12px;margin:16px 0 24px;padding:0;list-style:none}
.cifras li{background:#fff;border:1px solid var(--linea);border-radius:6px;padding:12px 16px;min-width:150px}
.cifras b{display:block;font-size:1.6rem;color:var(--panel)}
form.filtros{display:flex;flex-wrap:wrap;gap:8px;align-items:end;margin-bottom:16px}
label{display:grid;gap:4px;font-size:.875rem;font-weight:600}
input,select{font:inherit;padding:8px 10px;border:1px solid #a9bbd3;border-radius:4px;min-height:42px;background:#fff}
.boton{display:inline-flex;align-items:center;min-height:42px;padding:0 16px;border-radius:4px;border:0;background:var(--azul);color:#fff;font:inherit;font-weight:600;cursor:pointer;text-decoration:none}
.boton--sec{background:#fff;color:var(--azul);border:1px solid var(--azul)}
.tabla{overflow-x:auto;background:#fff;border:1px solid var(--linea);border-radius:6px}
table{border-collapse:collapse;width:100%;font-size:.9rem}th,td{padding:10px 12px;text-align:left;border-bottom:1px solid var(--linea);white-space:nowrap}
th{background:#eef3f9;font-size:.8rem;text-transform:uppercase;letter-spacing:.04em;color:var(--gris)}
.paginas{display:flex;gap:8px;margin:16px 0 40px;align-items:center}
.aviso{background:#fff4d6;border:1px solid #e6c46a;padding:12px 16px;border-radius:6px}
.ok{background:#e3f7ec;border:1px solid #7cc79b;padding:12px 16px;border-radius:6px}
.caja{background:#fff;border:1px solid var(--linea);border-radius:6px;padding:24px;max-width:420px;margin:48px auto}
.caja form{display:grid;gap:14px}
CSS;
