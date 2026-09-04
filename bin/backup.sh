#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Офсайт-бэкап regiongbi.ru: база, mu-плагины, .htaccess, robots.txt,
# инструменты и пакеты — с Бегета на этот сервер.
#
#   bash /srv/regiongbi/bin/backup.sh
#
# Зачем отдельная машина: сейчас дампы лежат на том же хостинге, что и сайт.
# Потеря аккаунта означает потерю и сайта, и бэкапов одновременно.
#
# Скрипт падает с ненулевым кодом, если дамп подозрительно мал или не
# распаковывается — молчаливый пустой бэкап хуже отсутствия бэкапа.
# ---------------------------------------------------------------------------
set -euo pipefail

CFG=${CFG:-/srv/regiongbi/config.env}
[ -r "$CFG" ] || { echo "нет файла настроек $CFG"; exit 1; }
# shellcheck disable=SC1090
. "$CFG"
: "${BEGET_HOST:?в config.env не заполнен BEGET_HOST}"

MIN_DUMP_BYTES=${MIN_DUMP_BYTES:-1000000}     # 1 МБ: меньше — значит дамп битый
STAMP=$(date +%Y%m%d-%H%M)
DAY=$(date +%Y-%m-%d)
DEST="$BACKUP_DIR/$DAY"
LOG="$LOG_DIR/backup-$DAY.log"
SSH=(ssh -i "$BEGET_KEY" -o BatchMode=yes -o ConnectTimeout=20 "$BEGET_USER@$BEGET_HOST")

mkdir -p "$DEST" "$LOG_DIR"
exec > >(tee -a "$LOG") 2>&1
echo "=== $(date '+%F %T')  бэкап начат"

fail() { echo "ОШИБКА: $*"; exit 1; }

echo "--- 1. дамп базы на стороне Бегета"
"${SSH[@]}" "cd $SITE_PATH && wp db export ~/regiongbi.ru/tmp-backup.sql --quiet" \
  || fail "wp db export не отработал"

echo "--- 2. упаковка файлов на стороне Бегета"
"${SSH[@]}" "cd ~/regiongbi.ru && tar czf tmp-backup-files.tar.gz \
    --ignore-failed-read \
    public_html/.htaccess public_html/robots.txt \
    public_html/wp-content/mu-plugins tools packages 2>/dev/null || true" \
  || fail "не удалось упаковать файлы"

echo "--- 3. перенос на сервер"
scp -i "$BEGET_KEY" -o BatchMode=yes -q \
    "$BEGET_USER@$BEGET_HOST:~/regiongbi.ru/tmp-backup.sql" \
    "$DEST/db-$STAMP.sql" || fail "не скачался дамп базы"
scp -i "$BEGET_KEY" -o BatchMode=yes -q \
    "$BEGET_USER@$BEGET_HOST:~/regiongbi.ru/tmp-backup-files.tar.gz" \
    "$DEST/files-$STAMP.tar.gz" || fail "не скачался архив файлов"

echo "--- 4. уборка на Бегете"
"${SSH[@]}" "rm -f ~/regiongbi.ru/tmp-backup.sql ~/regiongbi.ru/tmp-backup-files.tar.gz" || true

echo "--- 5. проверка"
SZ=$(stat -c%s "$DEST/db-$STAMP.sql")
[ "$SZ" -ge "$MIN_DUMP_BYTES" ] || fail "дамп базы всего $SZ байт — это не похоже на рабочую базу"
head -c 200 "$DEST/db-$STAMP.sql" | grep -q "SQL dump\|CREATE TABLE\|MySQL" \
  || fail "начало дампа не похоже на SQL"
tar tzf "$DEST/files-$STAMP.tar.gz" >/dev/null || fail "архив файлов не распаковывается"
FILES=$(tar tzf "$DEST/files-$STAMP.tar.gz" | wc -l)
MU=$(tar tzf "$DEST/files-$STAMP.tar.gz" | grep -c 'mu-plugins/.*\.php' || true)
[ "$MU" -ge 10 ] || fail "в архиве только $MU mu-плагинов, ожидалось не меньше 10"

echo "--- 6. сжатие дампа"
zstd -q -19 --rm "$DEST/db-$STAMP.sql"

echo "--- 7. чистка старых бэкапов (храним $KEEP_DAYS дней)"
find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -mtime "+$KEEP_DAYS" -print -exec rm -rf {} + || true

TOTAL=$(du -sh "$BACKUP_DIR" | cut -f1)
echo "=== готово: база $(numfmt --to=iec "$SZ"), файлов в архиве $FILES, mu-плагинов $MU"
echo "=== всего в хранилище: $TOTAL"
