# syntax=docker/dockerfile:1
# -----------------------------------------------------------------------------
# RetailPulse Jenkins controller image
#
# Dedicated image — never install Jenkins inside the Laravel/FrankenPHP
# container. Includes PHP 8.3, Composer, Node.js/npm, Docker CLI, and Git so
# Declarative Pipelines can lint/test/build/deploy without DinD
# (Docker-out-of-Docker via the mounted host socket).
# -----------------------------------------------------------------------------

FROM jenkins/jenkins:lts-jdk21

USER root

ARG DOCKER_GID=999
ARG NODE_MAJOR=20
ARG COMPOSER_VERSION=2.8.8
ARG PHP_VERSION=8.3

# docker group so the jenkins user can talk to the mounted socket
RUN groupadd -g "${DOCKER_GID}" docker \
    && usermod -aG docker jenkins

RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        gnupg \
        git \
        unzip \
        zip \
        jq \
        openssh-client \
        python3 \
        lsb-release \
        apt-transport-https \
    && install -m 0755 -d /etc/apt/keyrings \
    # Docker CLI
    && curl -fsSL https://download.docker.com/linux/debian/gpg \
        | gpg --dearmor -o /etc/apt/keyrings/docker.gpg \
    && chmod a+r /etc/apt/keyrings/docker.gpg \
    && echo \
        "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
        https://download.docker.com/linux/debian bookworm stable" \
        > /etc/apt/sources.list.d/docker.list \
    # Node.js 20
    && curl -fsSL https://deb.nodesource.com/setup_${NODE_MAJOR}.x | bash - \
    # PHP 8.3 (Sury) — bookworm default is 8.2; RetailPulse targets 8.3
    && curl -fsSLo /tmp/debsuryorg-archive-keyring.deb \
        https://packages.sury.org/debsuryorg-archive-keyring.deb \
    && dpkg -i /tmp/debsuryorg-archive-keyring.deb \
    && echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
        > /etc/apt/sources.list.d/php.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        docker-ce-cli \
        docker-compose-plugin \
        nodejs \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-opcache \
        php${PHP_VERSION}-readline \
        php${PHP_VERSION}-sqlite3 \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-redis \
    && curl -fsSL "https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar" \
        -o /usr/local/bin/composer \
    && chmod +x /usr/local/bin/composer \
    && php -v \
    && composer --version \
    && node -v \
    && npm -v \
    && docker --version \
    && rm -rf /var/lib/apt/lists/* /tmp/debsuryorg-archive-keyring.deb

# Suggested plugins for Declarative Pipelines + Git + Docker + Blue Ocean-light
COPY plugins.txt /usr/share/jenkins/ref/plugins.txt
RUN jenkins-plugin-cli --plugin-file /usr/share/jenkins/ref/plugins.txt

# Quick sanity path for pipeline agents (php/composer already on PATH)
RUN ln -sf /usr/bin/php${PHP_VERSION} /usr/local/bin/php || true

USER jenkins

HEALTHCHECK --interval=30s --timeout=10s --start-period=120s --retries=5 \
    CMD curl -fsS http://127.0.0.1:8080/login >/dev/null || exit 1
