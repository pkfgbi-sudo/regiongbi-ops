#!/usr/bin/env bash
# Проверка задания 030 по СЫРОМУ HTML, последовательно, с браузерными
# заголовками (раздел 5 AGENTS.md). Ничего не меняет.
set -u
. /srv/regiongbi/config.env
UA='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36'
H=(-H "Accept: text/html,application/xhtml+xml" -H "Accept-Language: ru-RU,ru" -H "User-Agent: $UA")
OUT=${OUT:-/tmp/rz-w030}
mkdir -p "$OUT"
for p in "$@"; do
  f="$OUT/$(echo "$p" | tr -c 'a-zA-Z0-9' '_').html"
  # -L: карточки лежат под разделами, короткий адрес отдаёт 301.
  read -r code url <<<"$(curl -sL -o "$f" -w '%{http_code} %{url_effective}' --max-time 25 "${H[@]}" "$SITE_URL$p")"
  echo "$p  код $code  тело $(wc -c <"$f") байт  адрес $url  -> $f"
  sleep 2
done
