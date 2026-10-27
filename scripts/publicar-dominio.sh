#!/bin/zsh
# Un solo paso: genera la versión del dominio y la sube por FTP.
set -e
cd "$(dirname "$0")"
./build-dominio.sh
./subir-ftp.sh
