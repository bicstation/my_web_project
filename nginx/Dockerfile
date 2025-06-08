# my_web_project/nginx/Dockerfile

FROM nginx:stable-alpine

# Nginx の設定ファイルをコンテナ内にコピー
# conf.d ディレクトリは Nginx のデフォルト設定でインクルードされる
#COPY ./conf.d/default.conf /etc/nginx/conf.d/default.conf
COPY ./conf.d/tipers.live.conf /etc/nginx/conf.d/tipers.live.conf

# アプリケーションのコードをコンテナ内にコピー (volumes でマウントされるので必須ではないが、ビルド時に含めることも可能)
# COPY ../app /var/www/html