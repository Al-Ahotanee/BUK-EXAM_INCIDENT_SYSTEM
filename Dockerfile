FROM php:8.2-apache

# --- System deps + PostgreSQL PDO driver ---
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# --- App code ---
WORKDIR /var/www/html
COPY . /var/www/html/

# uploads/ stores incident evidence files. NOTE: Render's free filesystem is
# ephemeral, so uploaded files will not survive a restart/redeploy. Fine for
# a demo/academic deployment; see DEPLOY.md for a persistent-storage upgrade path.
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/uploads

# --- Render injects $PORT at runtime; Apache must bind to it ---
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 10000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
