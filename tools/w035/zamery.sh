#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Задание 035, пункт 4. Серия замеров скорости отдачи страницы — с браузерными
# заголовками, последовательно, по одному адресу (разделы 5 и 3.9 AGENTS.md).
# Печатает код ответа, размер тела, время и метку кэша Cache Enabler, чтобы
# холодный замер не путался с прогретым.
#
#   bash tools/w035/zamery.sh <сколько> <адрес> [адрес ...]
# ---------------------------------------------------------------------------
set -u
N=${1:?сколько замеров}; shift
UA='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36'
H=(-H "Accept: text/html,application/xhtml+xml" -H "Accept-Language: ru-RU,ru" -H "User-Agent: $UA")
SITE=${SITE_URL:-https://regiongbi.ru}

for p in "$@"; do
  echo "--- $SITE$p"
  vse=""
  for i in $(seq 1 "$N"); do
    out=$(curl -s -D /tmp/rz-zamer-h -o /tmp/rz-zamer-b -w '%{http_code} %{size_download} %{time_total}' --max-time 30 "${H[@]}" "$SITE$p")
    set -- $out
    kod=$1; bytes=$2; t=$3
    kesh=$(grep -i '^x-cache-handler:' /tmp/rz-zamer-h | tr -d '\r' | cut -d' ' -f2-)
    [ "$kod" = 200 ] && [ "$bytes" -gt 5000 ] && vse="$vse $t" || kesh="$kesh ЗАМЕР НЕ УДАЛСЯ"
    printf '  %d) код %s, %7s байт, %6s с, кэш: %s\n' "$i" "$kod" "$bytes" "$t" "${kesh:-нет метки}"
    set -- "$@"
  done
  echo "$vse" | tr ' ' '\n' | grep -v '^$' | sort -n | awk '
    {a[NR]=$1; s+=$1}
    END {if (NR) printf "  итого %d удачных: мин %.2f  медиана %.2f  макс %.2f  среднее %.2f с\n",
         NR, a[1], a[int((NR+1)/2)], a[NR], s/NR}'
done
