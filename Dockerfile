FROM node:22-bookworm-slim AS frontend
WORKDIR /build/frontend
RUN corepack enable
COPY frontend/package.json frontend/pnpm-lock.yaml ./
RUN corepack pnpm@10.11.0 install --frozen-lockfile
COPY frontend/ ./
RUN VITE_API_MODE=php corepack pnpm@10.11.0 build

FROM php:8.3-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libsqlite3-dev libcurl4-openssl-dev ca-certificates \
    && docker-php-ext-install mbstring pdo_sqlite pdo_mysql curl \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY api.php scanner.php url_scanner.php health.php .htaccess ./
COPY database/ ./database/
COPY backend/categories.json ./backend/categories.json
COPY deployment/access.php deployment/init-db.php ./deployment/
COPY deployment/start.sh /usr/local/bin/auditoria-start
COPY deployment/php.ini /usr/local/etc/php/conf.d/auditoria.ini
COPY --from=frontend /build/frontend/dist/ ./
RUN chmod +x /usr/local/bin/auditoria-start
ENV AUDITORIA_ENV=production AUDITORIA_DATA_DIR=/var/data PORT=8080
EXPOSE 8080
CMD ["/usr/local/bin/auditoria-start"]
