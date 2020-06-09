#!/bin/bash

# Aplica o ID do usuário real no www-data para não causar problemas de permissão nos volumes
if [[ "$RUN_GROUP_ID" && "$RUN_GROUP_ID" != "33" ]]; then
    groupmod --non-unique --gid "$RUN_GROUP_ID" www-data
fi

if [[ "$RUN_USER_ID" && "$RUN_USER_ID" != "33" ]]; then
    usermod --non-unique --uid "$RUN_USER_ID" www-data
fi

# Remove o cache do manifest
rm -f /opt/web/dev-project/.cache/*

# Limpa os arquivos temporários de uma sessão anterior
if [[ -d /opt/web/dev-project/tmp ]] && [[ "$(ls -A1 /opt/web/dev-project/tmp)" ]]; then
    ls -A1 /opt/web/dev-project/tmp | xargs -i -d '\n' rm -rf /opt/web/dev-project/tmp/{};
fi

# Cria os diretórios temporários do sistema e das mídias
mkdir -p /opt/web/dev-project/tmp
chown www-data:www-data /opt/web/dev-project/tmp
chmod 777 /opt/web/dev-project/tmp

# Inicia os Serviços principais
/etc/init.d/rsyslog start
/etc/init.d/nginx start
/etc/init.d/php7.3-fpm start

# Console de logs para a aplicação
blazar console &
