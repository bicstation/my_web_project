# nginx/Dockerfile

FROM nginx:stable-alpine

WORKDIR /etc/nginx

# conf.d ディレクトリは Nginx のデフォルト設定でインクルードされる
COPY ./conf.d/default.conf /etc/nginx/conf.d/default.conf
# 以下、tipes.live から tiper.live にファイル名を修正
COPY ./conf.d/duga.tiper.live.conf /etc/nginx/conf.d/duga.tiper.live.conf
COPY ./conf.d/admin.tiper.live.conf /etc/nginx/conf.d/admin.tiper.live.conf
COPY ./conf.d/tiper.live.conf /etc/nginx/conf.d/tiper.live.conf
COPY ./conf.d/blog.tiper.live.conf /etc/nginx/conf.d/blog.tiper.live.conf


# アプリケーションのコードをコンテナ内にコピー (volumes でマウントされるので必須ではないが、ビルド時に含めることも可能)
# volumes を使う場合は、この COPY はコメントアウトするか削除しても良い
# COPY ../app /var/www/html

# Nginx のデフォルト設定ファイルを削除して、カスタム設定を優先 (通常はコメントアウトしたままでOK)
# RUN rm /etc/nginx/conf.d/default.conf

# Nginx をフォアグラウンドで実行
CMD ["nginx", "-g", "daemon off;"]
