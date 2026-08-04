FROM php:8.3-fpm-alpine

RUN set -eux; \
    mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"; \
    apk add --no-cache nginx; \
    mkdir -p /var/lib/nginx /var/log/nginx; \
    chown -R www-data:www-data /var/lib/nginx /var/log/nginx /etc/nginx

COPY nginx.conf /etc/nginx/nginx.conf
COPY hosting.ini /usr/local/etc/php/conf.d/hosting.ini
COPY start-hosting.sh /usr/local/bin/start-hosting

RUN chmod 0755 /usr/local/bin/start-hosting

USER www-data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1:8080/ || exit 1

ENTRYPOINT ["/usr/local/bin/start-hosting"]
