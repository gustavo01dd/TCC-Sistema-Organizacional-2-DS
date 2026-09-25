FROM php:8.2-fpm

# driver do MySQL/MariaDB para o PDO
RUN docker-php-ext-install pdo_mysql

# deixa as variáveis de ambiente do docker-compose (DB_HOST etc.) chegarem ao PHP
RUN printf '[www]\nclear_env = no\n' > /usr/local/etc/php-fpm.d/zzz-clima.conf

WORKDIR /var/www/public