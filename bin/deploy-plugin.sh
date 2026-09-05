#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Заливка одного mu-плагина на regiongbi.ru.
#
#   bash /srv/regiongbi/bin/deploy-plugin.sh <файл.php>        # пробный прогон
#   bash /srv/regiongbi/bin/deploy-plugin.sh <файл.php> --go   # заливка
#
# Пример:  deploy-plugin.sh packages/mu-plugins/mu-rz-theme.php
#          deploy-plugin.sh packages/mu-plugins/mu-rz-theme.php --go
#
# Зачем отдельный скрипт (задание 015). deploy.sh умеет только пакеты
# packages/*.json; файлы mu-плагинов до сих пор заливались руками, и 05.09.2026
# два файла уехали на Бегет сразу под боевыми именами — то есть начали
# исполняться раньше, чем прошли php -l. Синтаксическая ошибка в этот момент
# уронила бы сайт в 500, и разворачивать пришлось бы бэкап вместо того, чтобы
# просто не включать файл.
#
# Поэтому порядок жёсткий: файл едет под именем *.php.new, которое WordPress
# не подхватывает; php -l делается НА БЕГЕТЕ по этому временному файлу; в
# боевое имя он переезжает одним mv и только при коде 0.
#
# Команды одного шага идут одним сеансом ssh: правило 9 раздела 3 AGENTS.md,
# хостинг банит за перебор подключений (05.09.2026 сутки простоя).
# ---------------------------------------------------------------------------
set -euo pipefail

CFG=${CFG:-/srv/regiongbi/config.env}
[ -r "$CFG" ] || { echo "нет файла настроек $CFG"; exit 1; }
# shellcheck disable=SC1090
. "$CFG"
: "${BEGET_HOST:?в config.env не заполнен BEGET_HOST}"

SRC=${1:-}
GO=${2:-}
[ -n "$SRC" ] || { echo "укажите файл: deploy-plugin.sh packages/mu-plugins/mu-rz-theme.php"; exit 1; }
[ -f "$SRC" ] || { echo "нет файла $SRC"; exit 1; }
SRC=$(cd "$(dirname "$SRC")" && pwd)/$(basename "$SRC")
NAME=$(basename "$SRC")
case "$NAME" in
  *.php) : ;;
  *) echo "имя $NAME не оканчивается на .php — это не mu-плагин"; exit 1 ;;
esac

SSH=(ssh -i "$BEGET_KEY" -o BatchMode=yes -o ConnectTimeout=20 "$BEGET_USER@$BEGET_HOST")
BIN_DIR=$(cd "$(dirname "$0")" && pwd)
MONITOR="$BIN_DIR/monitor.sh"
# shellcheck disable=SC1090
. "$BIN_DIR/rz-kalitka.sh"
LOG="$LOG_DIR/deploy-plugin-$(date +%Y-%m-%d).log"
mkdir -p "$LOG_DIR"
exec > >(tee -a "$LOG") 2>&1

SNIM=$(mktemp -d /tmp/rz-plugin-XXXXXX)
trap 'rm -rf "$SNIM"' EXIT

MU="$SITE_PATH/wp-content/mu-plugins"
TMPN="$MU/$NAME.new"
BOEV="$MU/$NAME"
STAMP=$(date +%Y%m%d-%H%M%S)
B="$SITE_ROOT/backups/$STAMP-$NAME"
MD5_LOC=$(md5sum "$SRC" | cut -d' ' -f1)

echo "=== $(date '+%F %T')  mu-плагин $NAME"
echo "    отсюда: $SRC  (md5 $MD5_LOC, $(wc -c <"$SRC") байт)"
echo "    туда:   $BOEV"

# --- 1+2. бэкап и отправка файла под ВРЕМЕННЫМ именем
#
# scp идёт первым, потому что бэкап и php -l делаются в одном сеансе с
# проверкой, а класть файл всё равно нужно до неё. Имя $NAME.new не
# оканчивается на .php — WordPress такой файл не подключает, значит между
# отправкой и проверкой на сайте ничего не исполняется.
echo "--- 1. отправляю файл под временным именем $NAME.new"
scp -i "$BEGET_KEY" -o BatchMode=yes -q "$SRC" "$BEGET_USER@$BEGET_HOST:$TMPN"
echo "    отправлен"

echo "--- 2. бэкап и 3. проверка синтаксиса на Бегете (один сеанс)"
if ! "${SSH[@]}" "
  set -e
  mkdir -p $B
  cd $SITE_PATH
  wp db export $B/db-before.sql --quiet
  # Без -p: на Бегете cp -p падает на попытке сохранить владельца
  # («Operation not permitted»), а нам нужно содержимое, а не права.
  if [ -f $BOEV ]; then cp $BOEV $B/$NAME; echo 'BYL: da'; else echo 'BYL: net'; fi
  echo '--- md5 доехавшего временного файла'
  md5sum $TMPN
  echo '--- php -l по временному файлу'
  php -l $TMPN
"; then
  echo
  echo "!!! ОСТАНОВЛЕНО: бэкап не снялся или php -l нашёл ошибку в $NAME.new."
  echo "    Боевой файл $BOEV НЕ тронут, переименования не было."
  echo "    Временная копия осталась на Бегете для разбора: $TMPN"
  exit 1
fi
echo "    синтаксис чистый, бэкап в $B"

if [ "$GO" != "--go" ]; then
  cat <<EOF

---------------------------------------------------------------------------
Пробный прогон закончен. Файл лежит на Бегете как $NAME.new и не исполняется.
Если md5 выше совпал с местным ($MD5_LOC) — запустите ту же команду с --go:

    bash /srv/regiongbi/bin/deploy-plugin.sh $SRC --go
---------------------------------------------------------------------------
EOF
  exit 0
fi

# --- снимок проблем ДО включения
echo "--- 4а. снимок известных проблем ДО включения"
if ! kalitka_snimok "$SNIM/do.keys" "до включения"; then
  echo
  echo "!!! Замер до включения не удался — не с чем будет сравнивать после."
  echo "    Плагин НЕ включён, боевой файл не тронут. Бэкап: $B"
  exit 1
fi

# --- 4. включение одним движением + 5. кэш (один сеанс)
echo "--- 4. включаю (mv $NAME.new -> $NAME) и 5. сбрасываю кэш"
VKL=0
"${SSH[@]}" "
  set -e
  cd $SITE_PATH
  mv $TMPN $BOEV
  echo '--- md5 боевого файла'
  md5sum $BOEV
  echo '--- WordPress грузится?'
  echo \"mu-плагинов в каталоге: \$(ls -1 $MU/*.php 2>/dev/null | wc -l)\"
  wp eval 'echo \"RZ_WP_OK\n\";'
  echo '--- кэш'
  wp cache-enabler clear
  wp cache flush
" || VKL=$?

# Осторожно: под set -e голая связка `[ … ] && VAR=…` роняет скрипт, когда
# условие ложно. Поэтому именно if.
OTKAT=""
if [ "$VKL" -ne 0 ]; then
  OTKAT="WordPress не загрузился или кэш не сбросился после включения (код $VKL)"
fi

# --- 6. внешняя проверка по сравнению снимков
if [ -z "$OTKAT" ]; then
  echo "--- 6. проверка сайта снаружи"
  if ! kalitka_snimok "$SNIM/posle.keys" "после включения"; then
    OTKAT="замер после включения не удался"
  elif ! kalitka_sravnit "$SNIM/do.keys" "$SNIM/posle.keys"; then
    OTKAT="появились новые проблемы"
  fi
fi

# --- 7. откат файлом, если что-то новое сломалось
if [ -z "$OTKAT" ]; then
  echo "=== $(date '+%F %T')  $NAME включён, md5 $MD5_LOC"
  echo "    откат: см. $B/$NAME (если файл там есть) или переименование $BOEV"
  exit 0
fi

echo
echo "!!! ПРОВЕРКА НЕ ПРОЙДЕНА ($OTKAT) — возвращаю прежний файл"
# Прежнего файла могло и не быть: тогда плагин не удаляем (правило «не удалять»),
# а переименовываем в .otkat-<время> — WordPress перестаёт его подключать.
"${SSH[@]}" "
  cd $SITE_PATH
  if [ -f $B/$NAME ]; then
    cp $B/$NAME $BOEV && echo 'вернул прежний файл из бэкапа'
  else
    mv $BOEV $BOEV.otkat-$STAMP && echo 'прежнего файла не было — новый переименован в $NAME.otkat-$STAMP'
  fi
  wp cache-enabler clear
  wp cache flush
" || echo "    ОТКАТ НЕ УДАЛСЯ. Прежний файл лежит в $B/$NAME, вернуть руками."

echo "--- проверка после отката"
if kalitka_snimok "$SNIM/otkat.keys" "после отката" \
   && kalitka_sravnit "$SNIM/do.keys" "$SNIM/otkat.keys"; then
  echo "    сайт вернулся в то же состояние, что до включения"
else
  echo "    сайт всё ещё отвечает не так, как до включения — нужен человек"
fi

echo "=== $(date '+%F %T')  $NAME ОТКАЧЕН, сайт не изменён"
exit 1
