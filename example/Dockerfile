FROM php:8-alpine

ENV COMPOSER_ALLOW_SUPERUSER 1

RUN apk update && apk upgrade && apk add bash

RUN wget https://get.symfony.com/cli/installer -O - | bash \
    && mv /root/.symfony/bin/symfony /usr/local/bin/symfony \
    && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --filename=composer \
    && php -r "unlink('composer-setup.php');" \
    && mv composer /usr/local/bin/composer

EXPOSE 8000

# Set working directory
WORKDIR /app

# Copy source files
COPY ./app .

CMD ["symfony", "serve", "--no-tls"]
