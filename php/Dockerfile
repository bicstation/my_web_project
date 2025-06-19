# ベースとなるPHPイメージ
FROM php:8.2-fpm-alpine

# コンテナのタイムゾーンを設定（必要であれば）
ARG TIMEZONE=Asia/Tokyo
RUN apk add --no-cache tzdata \
    && cp /usr/share/zoneinfo/${TIMEZONE} /etc/localtime \
    && echo "${TIMEZONE}" > /etc/timezone

# ★★★ 修正点: PHP拡張機能のビルドに必要なシステムライブラリを追加 ★★★
RUN apk add --no-cache \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zlib-dev \
    # exif拡張機能が必要とする追加のライブラリ（libexifなど）
    # Alpineではphp-exifが別途存在するか、これらのlibでビルドできることが多い
    # 必要に応じて`apk search`で追加ライブラリを確認
    && rm -rf /var/cache/apk/*

# PHPに必要な拡張機能をインストール
# MySQLi (MySQL接続用) と gd (画像処理用) はよく使われます
RUN docker-php-ext-install pdo pdo_mysql opcache gd exif \
    && docker-php-ext-enable opcache

# Composerをインストール
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# ===============================================
# ★★★ ここから追加するPython関連のインストール ★★★
# Alpine Linux のパッケージマネージャー 'apk' を使用
RUN apk add --no-cache python3 py3-pip

# pip を使ってPythonの依存ライブラリをインストール
# --break-system-packages は、Dockerイメージビルド時にもシステムパッケージ管理の警告を抑制
RUN pip3 install mysql-connector-python python-dotenv --break-system-packages
# ★★★ ここまで追加するPython関連のインストール ★★★
# ===============================================

# アプリケーションのコードをコンテナにコピー
# ローカルの /var/www/html と同期するための VOLUME 設定がある場合は不要かもしれません
# 必要に応じて適切なパスを設定してください。
# COPY . /var/www/html

# 作業ディレクトリを設定
WORKDIR /var/www/html

# 権限設定（オプション: アプリケーションの要件による）
# RUN chown -R www-data:www-data /var/www/html

# コンテナ起動時にPHP-FPMを実行
CMD ["php-fpm"]
