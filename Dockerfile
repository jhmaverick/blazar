FROM debian:stretch

ENV DEBIAN_FRONTEND=noninteractive \
    APT_KEY_DONT_WARN_ON_DANGEROUS_USAGE=1 \
    RUN_IN_CONTAINER="True" \
    container=docker

### Prepara o ambiente para a instalação ###############################################################################

# Atualização dos pacotes e instalação de dependências
RUN apt-get -y update \
    && apt-get -y upgrade --no-install-recommends \
    && apt-get -y install --no-install-recommends sudo apt-utils build-essential libpam-pwdfile libwww-perl liblwp-protocol-https-perl rsyslog \
        software-properties-common iptables iproute2 dnsutils iputils-ping net-tools strace lsof dsniff runit-systemd cron incron rsync file jq \
        openssl openvpn git vim curl wget zip unzip htop geoip-database dirmngr gnupg zlib1g-dev locales lsb-release apt-transport-https ca-certificates

# Habilita idiomas pt_BR e en_US
RUN sed -ie 's/# en_US.UTF-8 UTF-8/en_US.UTF-8 UTF-8/' /etc/locale.gen \
    && sed -ie 's/# pt_BR.UTF-8 UTF-8/pt_BR.UTF-8 UTF-8/' /etc/locale.gen \
# Aplica o português como idioma principal
    && locale-gen pt_BR.UTF-8 \
    && dpkg-reconfigure locales \
    && update-locale LANG=pt_BR.UTF-8 LANGUAGE=pt_BR.UTF-8 LC_ALL=pt_BR.UTF-8 LC_CTYPE=pt_BR.UTF-8

# Habilita cedilha
ENV GTK_IM_MODULE=cedilla QT_IM_MODULE=cedilla \
# Aplica o português nas constantes de idioma
    LANG=pt_BR.UTF-8 LC_ALL=pt_BR.UTF-8 LC_CTYPE=pt_BR.UTF-8 LANGUAGE=pt_BR.UTF-8 \
# Altera o fuso horario do servidor
    TZ=America/Sao_Paulo


### Instala e configura as Aplicações ##################################################################################

# Instala do Nginx
RUN curl -fs http://nginx.org/keys/nginx_signing.key | apt-key add - \
    && echo "deb http://nginx.org/packages/mainline/debian/ stretch nginx" > /etc/apt/sources.list.d/nginx.list \
    && echo "deb-src http://nginx.org/packages/mainline/debian/ stretch nginx" >> /etc/apt/sources.list.d/nginx.list \
    && apt-get -y update \
    && apt-get -y install --no-install-recommends nginx

# Cliente MariaDB 10.3
RUN apt-key adv --recv-keys --keyserver keyserver.ubuntu.com 0xF1656F24C74CD1D8 \
    && echo 'deb [arch=amd64,i386,ppc64el] http://mirror.zol.co.zw/mariadb/repo/10.3/debian stretch main' > /etc/apt/sources.list.d/mariadb.list \
    && apt-get -y update \
    && apt-get -y install --no-install-recommends mariadb-client

# Repositório com multiplas versões do PHP
RUN wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg \
    && echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list \
    && apt-get -y update

# Instala PHP 7.3
RUN PHP_VERSION=7.3; apt-get -yq install --no-install-recommends php${PHP_VERSION}-mbstring php${PHP_VERSION}-cgi php${PHP_VERSION}-cli php${PHP_VERSION}-dev php${PHP_VERSION}-geoip \
        php${PHP_VERSION}-common php${PHP_VERSION}-xml php${PHP_VERSION}-xmlrpc php${PHP_VERSION}-sybase php${PHP_VERSION}-curl php${PHP_VERSION}-enchant php${PHP_VERSION}-imap php${PHP_VERSION}-xsl \
        php${PHP_VERSION}-mysql php${PHP_VERSION}-mysqli php${PHP_VERSION}-mysqlnd php${PHP_VERSION}-pspell php${PHP_VERSION}-gd php${PHP_VERSION}-zip php${PHP_VERSION}-tidy php${PHP_VERSION}-opcache \
        php${PHP_VERSION}-json php${PHP_VERSION}-bz2 php${PHP_VERSION}-pgsql php${PHP_VERSION}-readline php${PHP_VERSION}-imagick php${PHP_VERSION}-phar php${PHP_VERSION}-intl php${PHP_VERSION}-sqlite3 \
        php${PHP_VERSION}-ldap php${PHP_VERSION}-redis php${PHP_VERSION}-fpm php${PHP_VERSION}-soap php${PHP_VERSION}-bcmath php${PHP_VERSION}-fileinfo php${PHP_VERSION}-xdebug php${PHP_VERSION}-exif \
        php${PHP_VERSION}-tokenizer php-pear \
# Remove os diretórios que foram gerados para outras versões do PHP
    && ls -A1 /etc/php | grep -v ${PHP_VERSION} | xargs -i -d '\n' rm -rf /etc/php/{} \
# Coloca php-fpm da versão como serviço principal
    && ln -sf /etc/init.d/php${PHP_VERSION}-fpm /etc/init.d/php-fpm \
# Define caminhos do php para o pecl
    && pecl config-set ext_dir /usr/lib/php/${PHP_VERSION} \
    && pecl config-set php_ini /etc/php/${PHP_VERSION}/cli/php.ini \
    && pecl config-set php_bin /usr/bin/php${PHP_VERSION} \
    && pecl config-set php_suffix ${PHP_VERSION} \
# Composer
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# NodeJS 12
RUN curl -sL https://deb.nodesource.com/setup_12.x | bash - \
    && apt-get -y install --no-install-recommends nodejs \
# Yarn
    && curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add - \
    && echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list \
    && apt-get -y update \
    && apt-get -y install --no-install-recommends yarn

# Outras aplicações
RUN apt-get -y install --no-install-recommends python-pip python3-pip \
# Atualização e limpeza
    && apt-get -y update \
    && apt-get -y upgrade --no-install-recommends \
    && apt-get -yf autoremove \
    && apt-get clean

# Aplicações globais Node
RUN npm install -g blazar-cli webpack webpack-cli

# Diretório dos scripts de inicialização
RUN mkdir /etc/my_init.d \
# Ajustes NGINX
    && mkdir -p /var/cache/nginx \
    && mkdir -p /etc/nginx/sites-available \
    && mkdir -p /etc/nginx/sites-enabled \
    && mkdir -p /etc/nginx/snippets \
    && rm -f /etc/nginx/conf.d/default.conf \
# Ajustes PHP
    && mkdir -p /var/log/php \
    && find /etc/php/ -name php.ini -exec sed -Ei "s/^(upload_max_filesize *=).*/\1 64M/" {} \; \
    && find /etc/php/ -name php.ini -exec sed -Ei "s/^(post_max_size *=).*/\1 128M/" {} \; \
    && find /etc/php/ -name php.ini -exec sed -Ei "s/^(max_input_time *=).*/\1 -1/" {} \; \
    && find /etc/php/ -name php.ini -exec sed -Ei "s/^(max_execution_time *=).*/\1 60/" {} \; \
    && find /etc/php/ -name php.ini -exec sed -Ei "s/^[; ]*(max_input_vars *=).*/\1 5000/" {} \; \
# Diretório para as aplicações web
    && mkdir -p /opt/web \
    && chown www-data:www-data -R /opt/web \
# Altera a home do www-data para o diretório web
    && usermod -d /opt/web www-data


### Configurações da Imagem ############################################################################################

# Estrutura de arquivos da imagem
COPY rootfs/. /

RUN chmod +x /opt/blazar/entrypoint/* \
    && rm -rf /tmp/*

# Executa os scripts do diretório /etc/my_init.d
CMD ["/opt/blazar/entrypoint/my_init"]
WORKDIR /opt/web
EXPOSE 80 443 3000 4000
