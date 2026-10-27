#!/bin/zsh
# Genera la versión del sitio para coahuilamfgconnect.com (raíz del dominio)
# en dist-dominio/, con el .htaccess del hosting (LiteSpeed/Apache).
# GitHub Pages sigue publicándose aparte desde main.
set -e
cd "$(dirname "$0")/.."
SITE_URL=https://coahuilamfgconnect.com BASE_PATH=/ npx astro build --outDir dist-dominio
cat > dist-dominio/.htaccess <<'HT'
# HTTPS y dominio sin www
RewriteEngine On
RewriteCond %{HTTPS} !=on [OR]
RewriteCond %{HTTP_HOST} ^www\. [NC]
RewriteRule ^ https://coahuilamfgconnect.com%{REQUEST_URI} [L,R=301]

ErrorDocument 404 /404.html
Options -Indexes
DirectoryIndex index.html

AddType video/webm .webm
AddType image/avif .avif
AddType application/manifest+json .webmanifest

<IfModule mod_headers.c>
  Header always set X-Content-Type-Options "nosniff"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  <FilesMatch "\.(avif|webp|png|jpg|svg|woff2|mp4|webm|css|js|ico)$">
    Header set Cache-Control "public, max-age=2592000"
  </FilesMatch>
  <FilesMatch "\.html$">
    Header set Cache-Control "public, max-age=300"
  </FilesMatch>
</IfModule>
HT
echo "Listo: dist-dominio/"
