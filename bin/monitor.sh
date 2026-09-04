#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Ежедневная проверка живости regiongbi.ru снаружи.
#
#   bash /srv/regiongbi/bin/monitor.sh          # тихо, если всё хорошо
#   bash /srv/regiongbi/bin/monitor.sh -v       # печатать все строки
#
# Выход 0 — всё в порядке, 1 — есть проблемы (cron пришлёт письмо).
#
# ВАЖНО про замер скорости: Cache Enabler не кэширует запрос без заголовка
# Accept: text/html. Голый curl даёт около 500 мс вместо реальных 70 и уводит
# в ложную оптимизацию. Поэтому все запросы идут с браузерными заголовками.
# ---------------------------------------------------------------------------
set -uo pipefail

CFG=${CFG:-/srv/regiongbi/config.env}
[ -r "$CFG" ] && . "$CFG"
SITE_URL=${SITE_URL:-https://regiongbi.ru}
LOG_DIR=${LOG_DIR:-/srv/regiongbi/logs}
VERBOSE=0; [ "${1:-}" = "-v" ] && VERBOSE=1

UA='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36'
H=(-H "Accept: text/html,application/xhtml+xml" -H "Accept-Language: ru-RU,ru" -H "User-Agent: $UA")

mkdir -p "$LOG_DIR"
LOG="$LOG_DIR/monitor-$(date +%Y-%m).log"
PROBLEMS=()
note() { [ $VERBOSE -eq 1 ] && echo "   $*"; return 0; }
bad()  { PROBLEMS+=("$*"); echo "ПРОБЛЕМА: $*"; }

echo "=== $(date '+%F %T') проверка $SITE_URL" >>"$LOG"

# --- 1. коды ответа ключевых адресов
for p in / /catalog/ /resheniya/ /kontakty/ /blog/ /oplata-i-dostavka/ /sitemap_index.xml; do
  read -r code time <<<"$(curl -s -o /dev/null -w '%{http_code} %{time_total}' \
      --max-time 25 "${H[@]}" "$SITE_URL$p")"
  if [ "$code" != "200" ]; then
    bad "$p отдаёт $code"
  else
    note "$p — 200 за ${time}s"
    echo "$p $code $time" >>"$LOG"
  fi
done

# --- 2. служебные файлы ядра должны быть закрыты
for p in /license.txt /readme.html; do
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "${H[@]}" "$SITE_URL$p")
  if [ "$code" = "200" ]; then
    bad "$p снова доступен — похоже, обновилось ядро и правило в .htaccess слетело"
  else
    note "$p закрыт ($code)"
  fi
done

# --- 3. содержимое трёх страниц: H1, og:image, товарная разметка
check_page() {
  local url="$1" want_product="$2" html
  html=$(curl -s --max-time 25 "${H[@]}" "$url") || { bad "$url не скачался"; return; }
  local h1
  h1=$(grep -o '<h1[ >]' <<<"$html" | wc -l)
  [ "$h1" -eq 1 ] || bad "$url — заголовков H1: $h1 (должен быть ровно один)"
  grep -q 'property="og:image"' <<<"$html" || bad "$url — нет og:image"
  if [ "$want_product" = yes ]; then
    grep -q '"@type"[[:space:]]*:[[:space:]]*"Product"' <<<"$html" \
      || bad "$url — пропала товарная разметка Product"
    grep -q '"price"' <<<"$html" || bad "$url — в разметке нет цены"
  fi
  note "$url — H1:$h1, og:image есть"
}
check_page "$SITE_URL/catalog/koltsa-kolodeznye-ks/ks-10-9/" yes
check_page "$SITE_URL/catalog/vodopropusknye-truby-zk/zk-3-200/" yes
check_page "$SITE_URL/blog/" no

# --- 4. карта сайта: сколько адресов знает поисковик
smap=$(curl -s --max-time 25 "${H[@]}" "$SITE_URL/sitemap_index.xml")
maps=$(grep -c '<sitemap>' <<<"$smap" || true)
[ "${maps:-0}" -ge 2 ] || bad "в индексе карты сайта всего $maps разделов"
note "карт в индексе: $maps"

# --- 5. скорость главной, прогретой
warm=$(curl -s -o /dev/null -w '%{time_total}' --max-time 25 "${H[@]}" "$SITE_URL/")
warm=$(curl -s -o /dev/null -w '%{time_total}' --max-time 25 "${H[@]}" "$SITE_URL/")
slow=$(awk -v t="$warm" 'BEGIN{print (t>1.5)?1:0}')
[ "$slow" = "1" ] && bad "главная отдаётся за ${warm}s — это заметно медленнее обычного"
note "главная прогретая: ${warm}s"

# --- итог
if [ ${#PROBLEMS[@]} -eq 0 ]; then
  echo "ok $(date '+%F %T') все проверки пройдены" >>"$LOG"
  [ $VERBOSE -eq 1 ] && echo "Всё в порядке."
  exit 0
fi
{
  echo "--- $(date '+%F %T') найдено проблем: ${#PROBLEMS[@]}"
  printf '  %s\n' "${PROBLEMS[@]}"
} >>"$LOG"
echo
echo "Найдено проблем: ${#PROBLEMS[@]}. Правки на сайте выполняет владелец или"
echo "сессия с доступом к Бегету — эта проверка ничего не чинит сама."
exit 1
