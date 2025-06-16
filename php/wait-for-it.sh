#!/bin/sh
# wait-for-it.sh

# usage: wait-for-it.sh host:port [-t timeout] [-- command args]
# example: wait-for-it.sh db:3306 -t 30 -- npm start

set -e

host="$1"
shift
cmd="$@"

until nc -z "$host" 2>/dev/null; do
  echo >&2 "Waiting for $host..."
  sleep 1
done

echo >&2 "$host is up - executing command"
exec $cmd