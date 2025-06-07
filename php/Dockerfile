FROM php:8.3-fpm-alpine

# システムパッケージのインストール
# oniguruma-dev: mbstringの依存関係
# libzip-dev: zip拡張の依存関係 (将来的にPHPMailerなどで必要になる可能性あり)
# gd-dev: gd拡張の依存関係
# libxslt-dev: xsl拡張の依存関係
# freetype-dev, libjpeg-turbo-dev, libpng-dev: gd拡張の画像処理ライブラリ
RUN apk add --no-cache \
    oniguruma-dev \
    libzip-dev \
    gd-dev \
    libxslt-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev && \
    # 必要なPHP拡張をインストール
    docker-php-ext-install mysqli pdo pdo_mysql mbstring gd xsl zip && \
    # composer のインストール
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    php -r "unlink('composer-setup.php');"

WORKDIR /var/www/html