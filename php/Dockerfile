FROM php:8.3-fpm-alpine
# PHP 8.3 のFPM版イメージ (軽量なAlpine Linuxベース)

# 必要なPHP拡張をインストール (例としてmysqli, pdo_mysql)
# RUN docker-php-ext-install はスペース区切りで複数の拡張を指定できます
RUN docker-php-ext-install mysqli pdo pdo_mysql

WORKDIR /var/www/html # コンテナの作業ディレクトリを設定