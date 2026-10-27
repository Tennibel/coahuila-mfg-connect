#!/bin/zsh
# Genera la versión del sitio para coahuilamfgconnect.com (raíz del dominio)
# en dist-dominio/, con el .htaccess del hosting (LiteSpeed/Apache).
# GitHub Pages sigue publicándose aparte desde main.
set -e
cd "$(dirname "$0")/.."
SITE_URL=https://coahuilamfgconnect.com BASE_PATH=/ PUBLIC_REGISTRO_API=/api/registro.php \
  npx astro build --outDir dist-dominio

# Registro (PHP + MySQL): API, QR, panel /admin/ e instalador.
rsync -a --exclude composer.json --exclude composer.lock --exclude 'instalar-token.php' servidor/ dist-dominio/

# Clave de instalación de un solo uso: solo con «--instalar». Se muestra aquí y
# en el servidor solo queda su huella; instalar.php la borra al terminar.
if [[ "$1" == "--instalar" ]]; then
  TOKEN=$(openssl rand -hex 6)
  print -r -- "<?php return '$(print -rn -- $TOKEN | shasum -a 256 | cut -d' ' -f1)';" > dist-dominio/_registro/instalar-token.php
  echo "Clave de instalación (úsala en https://coahuilamfgconnect.com/instalar.php): $TOKEN"
fi
cat > dist-dominio/.htaccess <<'HT'
# HTTPS y dominio sin www
RewriteEngine On
RewriteCond %{HTTPS} !=on [OR]
RewriteCond %{HTTP_HOST} ^www\. [NC]
RewriteRule ^ https://coahuilamfgconnect.com%{REQUEST_URI} [L,R=301]

ErrorDocument 404 /404.html
Options -Indexes
DirectoryIndex index.html index.php

AddType video/webm .webm
AddType image/avif .avif
AddType application/manifest+json .webmanifest

<IfModule mod_headers.c>
  Header always set X-Content-Type-Options "nosniff"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  Header always set Strict-Transport-Security "max-age=31536000"
  Header always set X-Frame-Options "SAMEORIGIN"
  Header always set Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=()"
  <FilesMatch "\.(avif|webp|png|jpg|svg|woff2|mp4|webm|css|js|ico)$">
    Header set Cache-Control "public, max-age=2592000"
  </FilesMatch>
  <FilesMatch "\.html$">
    Header set Cache-Control "public, max-age=300"
  </FilesMatch>
</IfModule>
HT
echo "Listo: dist-dominio/"
