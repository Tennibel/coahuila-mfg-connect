"""
texto-a-trazos.py — convierte líneas de texto en trazos SVG con las fuentes del
sitio (Hubot Sans y Atkinson Hyperlegible Next, OFL), para la imagen de
compartir (og-image.svg), que se rasteriza sin acceso a fuentes web.

Entrada (stdin, JSON): [{"texto", "fuente": "hubot"|"atkinson", "tamano", "x", "y", "ejes": {...}}]
Salida (stdout, JSON): [{"d": "...", "ancho": n}]
"""
import json, sys
from fontTools.ttLib import TTFont
from fontTools.varLib.instancer import instantiateVariableFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen

FUENTES = {
    "hubot": "node_modules/@fontsource-variable/hubot-sans/files/hubot-sans-latin-standard-normal.woff2",
    "atkinson": "node_modules/@fontsource-variable/atkinson-hyperlegible-next/files/atkinson-hyperlegible-next-latin-wght-normal.woff2",
}
cache = {}

def fuente(nombre, ejes):
    clave = (nombre, tuple(sorted(ejes.items())))
    if clave not in cache:
        f = TTFont(FUENTES[nombre])
        cache[clave] = instantiateVariableFont(f, ejes)
    return cache[clave]

salida = []
for linea in json.load(sys.stdin):
    f = fuente(linea["fuente"], linea.get("ejes", {}))
    upm = f["head"].unitsPerEm
    escala = linea["tamano"] / upm
    cmap = f.getBestCmap()
    gs = f.getGlyphSet()
    hmtx = f["hmtx"]
    pen = SVGPathPen(gs)
    x = 0.0
    tracking = linea.get("tracking", 0) * upm
    for ch in linea["texto"]:
        nombre = cmap.get(ord(ch))
        if nombre is None:
            continue
        t = TransformPen(pen, (escala, 0, 0, -escala, linea["x"] + x * escala, linea["y"]))
        gs[nombre].draw(t)
        x += hmtx[nombre][0] + tracking
    salida.append({"d": pen.getCommands(), "ancho": round(x * escala, 1)})
json.dump(salida, sys.stdout)
