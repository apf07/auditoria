FROM php:8.3-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY .htaccess index.php login.php register.php logout.php api.php health.php ./
COPY app/ ./app/
COPY assets/ ./assets/
COPY database/ ./database/
COPY scripts/setup.php scripts/set-role.php ./scripts/
COPY deployment/start.sh /usr/local/bin/audita-start
COPY deployment/php.ini /usr/local/etc/php/conf.d/audita.ini
RUN chmod +x /usr/local/bin/audita-start
ENV AUDITORIA_ENV=production AUDITORIA_TRUST_PROXY=1 PORT=8080
EXPOSE 8080
CMD ["/usr/local/bin/audita-start"]
