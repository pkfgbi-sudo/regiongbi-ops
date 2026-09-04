#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Короткое письмо-уведомление о прогоне исполнителя.
#
#   bash bin/notify.sh "regiongbi: 005 — выполнено" < telo.txt
#   echo "текст" | bash bin/notify.sh "regiongbi: проверка канала уведомлений"
#   bash bin/notify.sh "тема" telo.txt
#   NOTIFY_DRY=1 bash bin/notify.sh "тема" telo.txt   # сухой прогон, письмо не уходит
#
# Письмо уходит через SMTP самого сайта: на Бегете вызывается wp_mail().
# Отдельный ящик и пароль не нужны и здесь не хранятся.
#
# Тема и тело уезжают на Бегет ЧЕРЕЗ STDIN в виде base64, а не склейкой в
# командную строку: в аргументах ssh кириллица и кавычки теряются. В командной
# строке едет только ASCII-код на PHP, без пользовательских данных.
#
# Выход: 0 — wp_mail вернул true; 1 — вернул false; 2 — ошибка вызова или ssh.
#
# ВНИМАНИЕ: письмо уходит наружу через чужой сервер. Паролям, токенам, путям
# к прокси-файлу LidFly и содержимому wp-config.php в нём места нет
# (раздел 1 AGENTS.md). Скрипт не читает конфиги сайта и ничего не подставляет
# в тело сам — что дали, то и отправит.
# ---------------------------------------------------------------------------
set -uo pipefail

CFG=${CFG:-/srv/regiongbi/config.env}
[ -r "$CFG" ] && . "$CFG"
BEGET_HOST=${BEGET_HOST:-}
BEGET_USER=${BEGET_USER:-}
BEGET_KEY=${BEGET_KEY:-}
SITE_PATH=${SITE_PATH:-}
KOMU=${NOTIFY_TO:-pkfgbi@gmail.com}

TEMA=${1:-}
if [ -z "$TEMA" ]; then
  echo "использование: notify.sh \"тема\" [файл-с-телом]   # без файла тело читается со stdin" >&2
  exit 2
fi
for v in BEGET_HOST BEGET_USER BEGET_KEY SITE_PATH; do
  [ -n "${!v}" ] || { echo "notify.sh: в $CFG нет $v" >&2; exit 2; }
done

if [ $# -ge 2 ]; then
  [ -r "$2" ] || { echo "notify.sh: не читается файл тела $2" >&2; exit 2; }
  TELO=$(cat "$2")
else
  TELO=$(cat)
fi

# Тело письма — не длиннее пятнадцати строк (условие задания 007).
TELO=$(printf '%s\n' "$TELO" | head -15)

B64=$(printf '%s\n%s\n' "$TEMA" "$TELO" | base64 -w0)

RTMP="/tmp/rz-notify-$$-$RANDOM.b64"
# В PHP-коде ниже нет одинарных кавычек — он целиком заезжает в 'wp eval ...'.
PHP='$raw=base64_decode(file_get_contents("'"$RTMP"'"));'
PHP+='$p=explode("\n",$raw,2);$s=trim($p[0]);$b=isset($p[1])?$p[1]:"";'
PHP+='if($s===""){echo "PUSTAYA_TEMA\n";exit(1);}'
if [ -n "${NOTIFY_DRY:-}" ]; then
  # Сухой прогон: письмо не уходит, Бегет печатает то, что доехало.
  # Нужен, чтобы проверять сборку письма, не заваливая ящик.
  PHP+='echo "--- TEMA: ",$s,"\n--- TELO:\n",$b,"\n--- konec\n";echo "true\n";'
else
  PHP+='$ok=wp_mail("'"$KOMU"'",$s,$b);echo $ok?"true\n":"false\n";'
fi
CMD="cat > $RTMP && cd $SITE_PATH && wp eval '$PHP'; KOD=\$?; rm -f $RTMP; exit \$KOD"

ERRF=$(mktemp); trap 'rm -f "$ERRF"' EXIT
VYVOD=$(printf '%s' "$B64" | ssh -i "$BEGET_KEY" -o BatchMode=yes -o ConnectTimeout=20 \
        "$BEGET_USER@$BEGET_HOST" "$CMD" 2>"$ERRF")
SSHKOD=$?
OTVET=$(printf '%s' "$VYVOD" | tr -d '\r' | tail -1)

if [ -n "${NOTIFY_DRY:-}" ]; then
  printf '%s\n' "$VYVOD"
  [ "$OTVET" = true ] && { echo "сухой прогон: письмо НЕ отправлено, транспорт цел"; exit 0; }
fi

case "$OTVET" in
  true)  echo "письмо отправлено: $TEMA"; exit 0 ;;
  false) echo "notify.sh: wp_mail вернул false — SMTP сайта не принял письмо" >&2; exit 1 ;;
  *)     echo "notify.sh: невнятный ответ Бегета (ssh код $SSHKOD): '$OTVET'" >&2
         sed 's/^/notify.sh: ssh: /' "$ERRF" >&2
         exit 2 ;;
esac
