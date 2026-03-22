FROM ubuntu:24.04

LABEL maintainer="Dost Team"

ARG WWWGROUP=1000
ARG NODE_VERSION=22
ARG PHP_VERSION=8.4

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# ── Base utilities ────────────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    curl gnupg gosu ca-certificates zip unzip git \
    supervisor sqlite3 libcap2-bin libpng-dev python3 \
    dnsutils librsvg2-bin postgresql-client rsync \
    && apt-get -y autoremove && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── ondrej/php PPA (PHP 8.4) ──────────────────────────────────────────────────
RUN curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x14aa40ec0831756756d7f66c4f4ea0aae5267a6c' | \
    gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" \
    > /etc/apt/sources.list.d/ppa_ondrej_php.list

RUN apt-get update && apt-get install -y \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-pgsql \
    php${PHP_VERSION}-sqlite3 \
    php${PHP_VERSION}-gd \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-imap \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-soap \
    php${PHP_VERSION}-intl \
    php${PHP_VERSION}-readline \
    php${PHP_VERSION}-ldap \
    php${PHP_VERSION}-msgpack \
    php${PHP_VERSION}-igbinary \
    php${PHP_VERSION}-redis \
    php${PHP_VERSION}-pcov \
    php${PHP_VERSION}-imagick \
    php${PHP_VERSION}-xdebug \
    && apt-get -y autoremove && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── Apache2 with PHP-FPM proxy modules ───────────────────────────────────────
RUN apt-get update && apt-get install -y apache2 \
    && a2enmod rewrite proxy proxy_fcgi setenvif headers \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && apt-get -y autoremove && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── Composer ─────────────────────────────────────────────────────────────────
RUN curl -sLS https://getcomposer.org/installer \
    | php -- --install-dir=/usr/bin/ --filename=composer

# ── Node.js 22 LTS ───────────────────────────────────────────────────────────
RUN curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - \
    && apt-get install -y nodejs \
    && apt-get -y autoremove && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── sail user (matches host UID via WWWUSER at runtime) ───────────────────────
# Remove default ubuntu user (occupies UID 1000 on Ubuntu 24.04) so sail can
# be cleanly remapped to the host user's UID at container startup.
RUN userdel --remove ubuntu 2>/dev/null || true \
    && groupdel ubuntu 2>/dev/null || true \
    && groupadd --force -g $WWWGROUP sail \
    && useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail

# ── Git — trust the mounted workspace regardless of UID mismatch ─────────────
# WWWUSER is remapped at runtime (host UID ≠ container UID 1337).
# --system writes to /etc/gitconfig, so it applies to root, sail, and any
# runtime UID — no per-user config needed.
RUN git config --system --add safe.directory /var/www/html

# ── Config files ──────────────────────────────────────────────────────────────
COPY apache.conf      /etc/apache2/sites-available/000-default.conf
COPY php.ini          /etc/php/${PHP_VERSION}/fpm/conf.d/99-dost.ini
COPY php.ini          /etc/php/${PHP_VERSION}/cli/conf.d/99-dost.ini
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY start-container  /usr/local/bin/start-container
RUN chmod +x /usr/local/bin/start-container

EXPOSE 80 8080

ENTRYPOINT ["start-container"]

