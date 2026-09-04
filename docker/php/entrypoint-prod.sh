#!/bin/sh
# Entrypoint de producao.
#
# Os caches sao gerados aqui e nao no build de proposito: config:cache congela
# os valores de .env, e o .env so existe em runtime. Gerar no build assaria a
# configuracao de quem buildou dentro da imagem.
set -e

php artisan config:cache
php artisan route:cache
php artisan event:cache

exec "$@"
