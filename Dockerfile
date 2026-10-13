# Railway detects this Dockerfile automatically. Public PHP only; database scripts stay private.
FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY . /var/www/html/
RUN printf '%s\n' '<Directory /var/www/html>' 'AllowOverride All' 'Require all granted' '</Directory>' \
    > /etc/apache2/conf-available/dentia.conf \
    && a2enconf dentia
EXPOSE 8080
COPY deploy/start.sh /usr/local/bin/start-dentia
RUN chmod +x /usr/local/bin/start-dentia
CMD ["/usr/local/bin/start-dentia"]
