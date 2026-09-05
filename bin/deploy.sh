#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Заливка пакета на regiongbi.ru с сервера.
#
#   bash /srv/regiongbi/bin/deploy.sh <пакет>          # разбор и пробный прогон
#   bash /srv/regiongbi/bin/deploy.sh <пакет> --go     # запись на сайт
#
# Пример:  deploy.sh dorogi-01
#          deploy.sh dorogi-01 --go
#
# Без --go скрипт НИЧЕГО не пишет: делает бэкап, показывает, что изменится,
# и останавливается. Так и задумано: правки на боевом сайте проходят через
# ваше «да», а не только через мою уверенность. 31 августа 113 страниц легли
# в 500 именно из-за правки, которую никто не посмотрел глазами.
#
# КАЛИТКА (задание 015). Откат делается не по «есть ли проблемы на сайте», а по
# «появились ли новые после заливки»: снимок ключей monitor.sh снимается до и
# после, сравниваются множества. Прежние проблемы заливку не блокируют,
# исчезнувшие — не повод для отката. Если monitor.sh не смог отработать
# (код 2), это не «проблем нет», а неизвестность: до заливки — отказ начинать,
# после заливки — откат.
# ---------------------------------------------------------------------------
set -euo pipefail

CFG=${CFG:-/srv/regiongbi/config.env}
[ -r "$CFG" ] || { echo "нет файла настроек $CFG"; exit 1; }
# shellcheck disable=SC1090
. "$CFG"
: "${BEGET_HOST:?в config.env не заполнен BEGET_HOST}"

PKG=${1:-}
GO=${2:-}
[ -n "$PKG" ] || { echo "укажите имя пакета, например: deploy.sh dorogi-01"; exit 1; }

SSH=(ssh -i "$BEGET_KEY" -o BatchMode=yes -o ConnectTimeout=20 "$BEGET_USER@$BEGET_HOST")
BIN_DIR=$(cd "$(dirname "$0")" && pwd)
MONITOR="$BIN_DIR/monitor.sh"
# shellcheck disable=SC1090
. "$BIN_DIR/rz-kalitka.sh"
LOG="$LOG_DIR/deploy-$(date +%Y-%m-%d).log"
mkdir -p "$LOG_DIR"
exec > >(tee -a "$LOG") 2>&1

SNIM=$(mktemp -d /tmp/rz-deploy-XXXXXX)
trap 'rm -rf "$SNIM"' EXIT

echo "=== $(date '+%F %T')  пакет $PKG"

# --- 1. свежий репозиторий
echo "--- 1. обновляю репозиторий"
if [ -d "$REPO_DIR/.git" ]; then
  git -C "$REPO_DIR" fetch --quiet origin
  git -C "$REPO_DIR" reset --hard --quiet origin/main
else
  echo "репозиторий не склонирован в $REPO_DIR"; exit 1
fi
git -C "$REPO_DIR" log -1 --format='    последняя правка: %h %ad %s' --date=short

JSON="$REPO_DIR/packages/$PKG.json"
[ -f "$JSON" ] || { echo "нет файла $JSON"; exit 1; }
python3 -c "import json,sys;d=json.load(open(sys.argv[1]));print('    позиций:',len(d['items']))" "$JSON"

# какой публикатор нужен: страницы или записи блога
if python3 - "$JSON" <<'EOF'
import json,sys
d=json.load(open(sys.argv[1]))
sys.exit(0 if any('slug' in i for i in d['items']) else 1)
EOF
then TOOL=rz-blog.php; KIND="записи блога"
else TOOL=rzpub.php;   KIND="страницы"
fi
echo "    тип: $KIND, публикатор $TOOL"

# --- 2. отправка файлов
echo "--- 2. отправляю пакет и инструменты"
scp -i "$BEGET_KEY" -o BatchMode=yes -q "$JSON" \
    "$BEGET_USER@$BEGET_HOST:$SITE_ROOT/packages/$PKG.json"
if [ -f "$REPO_DIR/tools/$TOOL" ]; then
  scp -i "$BEGET_KEY" -o BatchMode=yes -q "$REPO_DIR/tools/$TOOL" \
      "$BEGET_USER@$BEGET_HOST:$SITE_ROOT/tools/$TOOL"
fi

# --- 3. бэкап до правки
echo "--- 3. бэкап базы перед правкой"
B="$SITE_ROOT/backups/$(date +%Y%m%d)-$PKG"
"${SSH[@]}" "mkdir -p $B && cd $SITE_PATH && wp db export $B/db-before.sql --quiet" \
  || { echo "бэкап не сделан — заливка отменена"; exit 1; }
echo "    сохранён в $B/db-before.sql"

# --- 4. пробный прогон
echo "--- 4. пробный прогон (ничего не пишется)"
"${SSH[@]}" "cd $SITE_PATH && wp eval-file $SITE_ROOT/tools/$TOOL $SITE_ROOT/packages/$PKG.json dry" \
  | tail -n 25

if [ "$GO" != "--go" ]; then
  cat <<EOF

---------------------------------------------------------------------------
Пробный прогон закончен, на сайте ничего не изменилось.
Если разбор выше выглядит правильно — запустите ту же команду с --go:

    bash /srv/regiongbi/bin/deploy.sh $PKG --go
---------------------------------------------------------------------------
EOF
  exit 0
fi

# --- 5. снимок проблем до заливки
#
# Базовая линия для калитки. Снимается после пробного прогона и до первой
# записи, чтобы между снимком и заливкой не было ничего постороннего.
echo "--- 5. снимок известных проблем ДО заливки"
if ! kalitka_snimok "$SNIM/do.keys" "до заливки"; then
  echo
  echo "!!! Замер до заливки не удался — не с чем будет сравнивать после."
  echo "    Заливка отменена, на сайте ничего не изменено."
  echo "    Дамп базы на всякий случай остался в $B/db-before.sql"
  exit 1
fi

# --- 6. заливка
echo "--- 6. заливка"
"${SSH[@]}" "cd $SITE_PATH && wp eval-file $SITE_ROOT/tools/$TOOL $SITE_ROOT/packages/$PKG.json" \
  | tail -n 30

# --- 7. товарная разметка, если пакет её касается
if grep -q '"_rz_product"\|"meta"' "$JSON"; then
  echo "--- 7. проверка товарной разметки"
  "${SSH[@]}" "cd $SITE_PATH && wp eval '
    \$ok=0;\$bad=0;
    foreach(get_posts(array(\"post_type\"=>\"page\",\"post_status\"=>\"publish\",\"posts_per_page\"=>-1,\"fields\"=>\"ids\")) as \$i){
      \$r=get_post_meta(\$i,\"_rz_product\",true);
      if(\$r===\"\") continue;
      if(!is_string(\$r) || !is_array(json_decode(\$r,true))){ \$bad++; continue; }
      \$ok++; }
    echo \"страниц с разметкой: \$ok, битых: \$bad\n\";'"
fi

# --- 8. кэш
echo "--- 8. сброс кэша"
"${SSH[@]}" "cd $SITE_PATH && wp cache-enabler clear >/dev/null 2>&1; wp cache flush >/dev/null 2>&1; echo ok"

# --- 9. живая проверка и автоматический откат
#
# Здесь главная страховка. 31 августа 113 страниц отдавали 500 около полусуток
# не потому, что никто не посмотрел глазами, а потому что никто не проверил
# после заливки. Глаз этого и не ловит — ловит проверка.
#
# Откатываем в двух случаях: появился ключ проблемы, которого до заливки не
# было, — или замер после заливки не удался (состояние неизвестно, а неизвестность
# на боевом сайте считается неудачей).
echo "--- 9. проверка сайта снаружи"
OTKAT=""
if ! kalitka_snimok "$SNIM/posle.keys" "после заливки"; then
  OTKAT="замер после заливки не удался"
elif ! kalitka_sravnit "$SNIM/do.keys" "$SNIM/posle.keys"; then
  OTKAT="появились новые проблемы"
fi

if [ -z "$OTKAT" ]; then
  echo "    проверка пройдена"
else
  echo
  echo "!!! ПРОВЕРКА НЕ ПРОЙДЕНА ($OTKAT) — откатываю базу на состояние до заливки"
  if "${SSH[@]}" "cd $SITE_PATH && wp db import $B/db-before.sql --quiet && wp cache-enabler clear >/dev/null 2>&1; wp cache flush >/dev/null 2>&1; echo restored"; then
    echo "    база восстановлена из $B/db-before.sql"
  else
    echo "    ОТКАТ НЕ УДАЛСЯ. Дамп лежит в $B/db-before.sql, восстанавливать руками:"
    echo "    cd $SITE_PATH && wp db import $B/db-before.sql"
  fi

  echo "--- проверка после отката"
  if kalitka_snimok "$SNIM/otkat.keys" "после отката" \
     && kalitka_sravnit "$SNIM/do.keys" "$SNIM/otkat.keys"; then
    echo "    сайт вернулся в то же состояние, что до заливки"
  else
    echo "    сайт всё ещё отвечает не так, как до заливки — нужен человек"
  fi

  echo "=== $(date '+%F %T')  пакет $PKG ОТКАЧЕН, сайт не изменён"
  exit 1
fi

echo "=== $(date '+%F %T')  пакет $PKG залит"
