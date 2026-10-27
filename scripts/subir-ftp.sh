#!/bin/zsh
# Sube dist-dominio/ a public_html/ de coahuilamfgconnect.com por FTP con TLS
# (FTPS explícito, puerto 21) usando lftp. La contraseña se guarda en el
# Llavero de macOS, nunca en un archivo del proyecto.
set -e
cd "$(dirname "$0")/.."
# El certificado TLS del hosting está a nombre del servidor, no del dominio:
# se conecta por ese nombre (mismo servidor que ftp.coahuilamfgconnect.com).
HOST="priva268.spindns.com"
USUARIO="admin@coahuilamfgconnect.com"
[[ -d dist-dominio ]] || { echo "Falta dist-dominio/: corre primero scripts/build-dominio.sh"; exit 1; }
command -v lftp >/dev/null || { echo "Falta lftp: brew install lftp"; exit 1; }

# La contraseña vive en el Llavero de macOS (servicio «coahuila-ftp»). La primera
# vez se pide y se guarda ahí; las siguientes se lee del Llavero sin preguntar.
LLAVERO="coahuila-ftp"
if ! CLAVE=$(security find-generic-password -a "$USUARIO" -s "$LLAVERO" -w 2>/dev/null); then
  read -s "CLAVE?Contraseña FTP de $USUARIO (se guardará en el Llavero): "; echo
  security add-generic-password -U -a "$USUARIO" -s "$LLAVERO" -l "FTP coahuilamfgconnect.com" -w "$CLAVE"
  echo "Guardada en el Llavero. La próxima vez no se pedirá."
fi
export LFTP_PASSWORD="$CLAVE"
unset CLAVE

lftp -e "
set ftp:ssl-force true;
set ftp:ssl-protect-data true;
set ssl:verify-certificate true;
set ftp:passive-mode true;
set net:max-retries 3;
set net:timeout 30;
open --env-password -u '$USUARIO' ftp://$HOST;
mirror --reverse --verbose --parallel=3 --no-perms dist-dominio/ public_html/;
bye
"
echo "Listo. Revisa https://coahuilamfgconnect.com/"
