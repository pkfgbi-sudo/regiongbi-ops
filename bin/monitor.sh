#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Ежедневная проверка живости regiongbi.ru снаружи.
#
#   bash /srv/regiongbi/bin/monitor.sh          # тихо, если всё хорошо
#   bash /srv/regiongbi/bin/monitor.sh -v       # печатать все строки
#   bash /srv/regiongbi/bin/monitor.sh --keys   # только ключи проблем, по строке
#
# Коды возврата:
#   0 — проблем не найдено;
#   1 — найдены проблемы (список выше, ключи слева);
#   2 — ЗАМЕР НЕ УДАЛСЯ: сайт или сеть не ответили, состояние неизвестно.
#       Это не «проблем нет»: тот, кто решает по коду возврата, обязан считать
#       двойку неудачей (см. deploy.sh, задание 015).
#   3 — неверный ключ запуска.
#
# У каждой проблемы есть КЛЮЧ — короткая устойчивая строка без пробелов,
# одинаковая от прогона к прогону: otkryt_license_txt, cena_ks_10_9 и т.п.
# По ключам deploy.sh сравнивает состояние до и после заливки и откатывает
# только тогда, когда появился ключ, которого до заливки не было. Поэтому
# ключи нельзя переименовывать «для красоты»: переименованный ключ выглядит
# как новая проблема и вызовет ложный откат.
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

VERBOSE=0; KEYS=0
for a in "$@"; do
  case "$a" in
    -v)     VERBOSE=1 ;;
    --keys) KEYS=1 ;;
    *) echo "monitor.sh: не знаю ключ запуска «$a». Есть -v и --keys." >&2; exit 3 ;;
  esac
done

UA='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36'
H=(-H "Accept: text/html,application/xhtml+xml" -H "Accept-Language: ru-RU,ru" -H "User-Agent: $UA")

# Короче этого страница сайта не бывает: самая маленькая в обходе 04.09.2026
# отдала 84 килобайта. Тело в 5 КБ — это заглушка хостинга или обрыв, то есть
# неудавшийся замер, а не «признака нет» (раздел 5 AGENTS.md).
MIN_BODY=${MIN_BODY:-5000}

mkdir -p "$LOG_DIR"
LOG="$LOG_DIR/monitor-$(date +%Y-%m).log"
PROBLEMS=()   # ключ<TAB>текст
FAILS=()      # ключ<TAB>текст неудавшегося замера

note() { [ $VERBOSE -eq 1 ] && [ $KEYS -eq 0 ] && echo "   $*"; return 0; }
# Проблема: признак найден и он плохой.
bad()  { local k=$1; shift; PROBLEMS+=("$k"$'\t'"$*"); [ $KEYS -eq 1 ] || echo "ПРОБЛЕМА: [$k] $*"; return 0; }
# Замер не удался: про признак ничего не известно.
sbo()  { local k=$1; shift; FAILS+=("$k"$'\t'"$*"); [ $KEYS -eq 1 ] || echo "ЗАМЕР НЕ УДАЛСЯ: [$k] $*"; return 0; }

# /oplata-i-dostavka/ -> oplata_i_dostavka, / -> root
klyuch() {
  local p=${1#/}; p=${p%/}
  [ -z "$p" ] && p=root
  printf '%s' "$p" | tr -- '-/.' '___'
}

echo "=== $(date '+%F %T') проверка $SITE_URL" >>"$LOG"

# --- 1. коды ответа ключевых адресов
for p in / /catalog/ /resheniya/ /kontakty/ /blog/ /oplata-i-dostavka/ /sitemap_index.xml; do
  k=$(klyuch "$p")
  read -r code time <<<"$(curl -s -o /dev/null -w '%{http_code} %{time_total}' \
      --max-time 25 "${H[@]}" "$SITE_URL$p")"
  if [ -z "${code:-}" ] || [ "$code" = "000" ]; then
    sbo "zamer_$k" "$p — ответа нет вообще (curl не получил код)"
  elif [ "$code" != "200" ]; then
    bad "otvet_$k" "$p отдаёт $code"
  else
    note "$p — 200 за ${time}s"
    echo "$p $code $time" >>"$LOG"
  fi
done

# --- 2. служебные файлы ядра должны быть закрыты
for p in /license.txt /readme.html; do
  k=$(klyuch "$p")
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "${H[@]}" "$SITE_URL$p")
  if [ -z "${code:-}" ] || [ "$code" = "000" ]; then
    sbo "zamer_$k" "$p — ответа нет вообще (curl не получил код)"
  elif [ "$code" = "200" ]; then
    bad "otkryt_$k" "$p снова доступен — похоже, обновилось ядро и правило в .htaccess слетело"
  else
    note "$p закрыт ($code)"
  fi
done

# --- 3. содержимое трёх страниц: H1, og:image, товарная разметка
check_page() {
  local url="$1" k="$2" want_product="$3" otvet code html len rc
  otvet=$(curl -s -w $'\n%{http_code}' --max-time 25 "${H[@]}" "$url"); rc=$?
  code=${otvet##*$'\n'}
  html=${otvet%$'\n'*}
  len=${#html}
  # Сначала отделяем неудавшийся замер от отрицательного результата.
  if [ $rc -ne 0 ] || [ "${code:-000}" != "200" ] || [ "$len" -lt "$MIN_BODY" ]; then
    sbo "zamer_$k" "$url — замер не удался: curl $rc, код ${code:-нет}, тело $len байт"
    return
  fi
  local h1
  h1=$(grep -o '<h1[ >]' <<<"$html" | wc -l)
  [ "$h1" -eq 1 ] || bad "h1_$k" "$url — заголовков H1: $h1 (должен быть ровно один)"
  grep -q 'property="og:image"' <<<"$html" || bad "og_image_$k" "$url — нет og:image"
  if [ "$want_product" = yes ]; then
    grep -q '"@type"[[:space:]]*:[[:space:]]*"Product"' <<<"$html" \
      || bad "product_$k" "$url — пропала товарная разметка Product"
    grep -q '"price"' <<<"$html" || bad "cena_$k" "$url — в разметке нет цены"
  fi
  note "$url — H1:$h1, og:image есть, тело $len байт"
}
check_page "$SITE_URL/catalog/koltsa-kolodeznye-ks/ks-10-9/" ks_10_9 yes
check_page "$SITE_URL/catalog/vodopropusknye-truby-zk/zk-3-200/" zk_3_200 yes
check_page "$SITE_URL/blog/" blog no

# --- 4. карта сайта: сколько адресов знает поисковик
otvet=$(curl -s -w $'\n%{http_code}' --max-time 25 "${H[@]}" "$SITE_URL/sitemap_index.xml"); rc=$?
code=${otvet##*$'\n'}; smap=${otvet%$'\n'*}
if [ $rc -ne 0 ] || [ "${code:-000}" != "200" ] || [ ${#smap} -lt 100 ]; then
  sbo "zamer_karta_sayta" "карта сайта не скачалась: curl $rc, код ${code:-нет}, тело ${#smap} байт"
else
  maps=$(grep -c '<sitemap>' <<<"$smap" || true)
  [ "${maps:-0}" -ge 2 ] || bad "karta_razdelov" "в индексе карты сайта всего $maps разделов"
  note "карт в индексе: $maps"
fi

# --- 5. скорость главной, прогретой
for _ in 1 2; do
  read -r code warm <<<"$(curl -s -o /dev/null -w '%{http_code} %{time_total}' \
      --max-time 25 "${H[@]}" "$SITE_URL/")"
done
if [ -z "${code:-}" ] || [ "$code" != "200" ]; then
  sbo "zamer_skorost" "скорость главной не замерена: код ${code:-нет}"
else
  slow=$(awk -v t="$warm" 'BEGIN{print (t>1.5)?1:0}')
  [ "$slow" = "1" ] && bad "glavnaya_medlenno" "главная отдаётся за ${warm}s — это заметно медленнее обычного"
  note "главная прогретая: ${warm}s"
fi

# --- итог
# В режиме --keys на stdout уходят ТОЛЬКО ключи проблем, по строке на ключ:
# это вход для сравнения в deploy.sh. Неудавшиеся замеры туда не попадают —
# они не проблемы, а неизвестность, и видны по коду возврата 2 и по stderr.
if [ $KEYS -eq 1 ]; then
  if [ ${#PROBLEMS[@]} -gt 0 ]; then
    for pr in "${PROBLEMS[@]}"; do printf '%s\n' "${pr%%$'\t'*}"; done
  fi
  if [ ${#FAILS[@]} -gt 0 ]; then
    for f in "${FAILS[@]}"; do
      printf 'ЗАМЕР НЕ УДАЛСЯ: [%s] %s\n' "${f%%$'\t'*}" "${f#*$'\t'}" >&2
    done
  fi
fi

{
  if [ ${#FAILS[@]} -gt 0 ]; then
    echo "--- $(date '+%F %T') замеров не удалось: ${#FAILS[@]}, проблем: ${#PROBLEMS[@]}"
    printf '  сбой замера %s\n' "${FAILS[@]}"
  elif [ ${#PROBLEMS[@]} -gt 0 ]; then
    echo "--- $(date '+%F %T') найдено проблем: ${#PROBLEMS[@]}"
  else
    echo "ok $(date '+%F %T') все проверки пройдены"
  fi
  [ ${#PROBLEMS[@]} -gt 0 ] && printf '  %s\n' "${PROBLEMS[@]}"
} >>"$LOG"

if [ ${#FAILS[@]} -gt 0 ]; then
  [ $KEYS -eq 1 ] || {
    echo
    echo "Замеров не удалось: ${#FAILS[@]}. Это НЕ значит «проблем нет»: состояние"
    echo "сайта по этим признакам неизвестно. Найденных проблем при этом: ${#PROBLEMS[@]}."
  }
  exit 2
fi

if [ ${#PROBLEMS[@]} -eq 0 ]; then
  [ $VERBOSE -eq 1 ] && [ $KEYS -eq 0 ] && echo "Всё в порядке."
  exit 0
fi

[ $KEYS -eq 1 ] || {
  echo
  echo "Найдено проблем: ${#PROBLEMS[@]}. Правки на сайте выполняет владелец или"
  echo "сессия с доступом к Бегету — эта проверка ничего не чинит сама."
}
exit 1
