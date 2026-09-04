#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Автозапуск исполнителя по расписанию.
#
# Ставится в cron под пользователем zhbi. Запускает Claude Code ТОЛЬКО когда в
# inbox/ действительно есть задание: пустой прогон впустую тратит лимит подписки.
#
# Защиты:
#   flock      — два прогона одновременно не пойдут;
#   timeout    — зависший прогон убивается через час;
#   счётчик    — одно и то же задание не долбится вечно: после третьей неудачи
#                скрипт останавливается сам и кладёт в state/ZASTRYALO.txt
#                запись, которую видит облачная сессия.
#
# После прогона уходит короткое письмо-уведомление на pkfgbi@gmail.com через
# bin/notify.sh (задание 007): по одному письму на каждый новый отчёт в outbox/,
# а если прогон упал или отчётов не появилось — одно письмо со статусом
# «не выполнено» и последними строками лога. Отчёт остаётся в outbox/, письмо
# только сообщает, что он там появился.
# ---------------------------------------------------------------------------
set -uo pipefail

REPO=${REPO:-/srv/regiongbi/repo}
LOGDIR=${LOGDIR:-/srv/regiongbi/logs}
TMPDIR_=${TMPDIR_:-/srv/regiongbi/tmp}
mkdir -p "$LOGDIR" "$TMPDIR_"
LOG="$LOGDIR/agent-$(date +%F).log"
say() { echo "$(date '+%F %T') $*" >> "$LOG"; }

NOTIFY=/srv/regiongbi/bin/notify.sh

# Лог прогона — это вывод агента, в него может попасть что угодно. Наружу
# через чужой SMTP такое не отправляем: режем строки, похожие на пароли,
# токены и конфиги (раздел 1 AGENTS.md).
bez_sekretov() {
  grep -viE 'passw|parol|token|secret|api[_-]?key|auth[_-]?key|salt|wp-config|lidfly|smtp|db_(user|name|host)' \
    || true
}

pismo() {                       # pismo "тема" ; тело со stdin
  local tema="$1" telo
  telo=$(cat)
  if [ ! -x "$NOTIFY" ]; then
    say "письмо не отправлено: нет $NOTIFY"
    return 1
  fi
  if printf '%s\n' "$telo" | "$NOTIFY" "$tema" >>"$LOG" 2>&1; then
    say "письмо отправлено: $tema"
  else
    say "ОШИБКА: письмо не ушло ($tema) — смотрите строки notify.sh выше"
  fi
}

# Достать из отчёта номер, заголовок, статус и два-три предложения итога.
pismo_po_otchetu() {
  local f="$1" nomer zagolovok status itog nesdelano
  nomer=$(basename "$f" | cut -d- -f1)
  zagolovok=$(head -1 "$f" | sed 's/^#\s*//; s/^Задание [0-9]*\s*—\s*//')
  status=$(grep -m1 '^Статус:' "$f" | sed 's/^Статус:\s*//')
  [ -n "$status" ] || status="статус не указан"
  # итог: первый абзац после строки Статус и до первого заголовка «## »
  itog=$(awk '/^Статус:/{v=1;next} v&&/^## /{exit} v&&NF{print}' "$f" | head -4)
  # запасной вариант, если сразу после статуса идёт заголовок
  [ -n "$itog" ] || itog=$(awk '/^## Что сделано/{v=1;next} v&&/^## /{exit} v&&NF{print}' "$f" | head -3)
  nesdelano=$(awk '/^## Не сделано/{v=1;next} v&&/^## /{exit} v&&NF{print}' "$f" \
    | head -2 | paste -sd' ' - | cut -c1-180)
  [ ${#nesdelano} -ge 180 ] && nesdelano="$nesdelano…"

  pismo "regiongbi: $nomer — $status" <<PISMO
Задание: $nomer — $zagolovok
Статус: $status
Время: $(date '+%F %H:%M')

$itog

Не сделано: ${nesdelano:-—}

Отчёт целиком: $f
PISMO
}

exec 9>"$TMPDIR_/agent.lock"
flock -n 9 || { say "предыдущий прогон ещё идёт — пропускаю"; exit 0; }

CLAUDE=$(command -v claude || echo "$HOME/.local/bin/claude")
[ -x "$CLAUDE" ] || { say "ОШИБКА: claude не найден"; exit 1; }

cd "$REPO" || { say "ОШИБКА: нет $REPO"; exit 1; }

# Незакоммиченные изменения — след упавшего прогона. Не затираем: это отчёт.
if ! git diff --quiet || ! git diff --cached --quiet; then
  say "есть незакоммиченные изменения — прогон пропущен, разбираться вручную"
  exit 1
fi

git fetch --quiet origin || { say "ОШИБКА: git fetch не прошёл"; exit 1; }
git merge --ff-only --quiet origin/main || { say "ОШИБКА: ветка разошлась с origin"; exit 1; }

compgen -G "inbox/*.md" >/dev/null || { say "inbox пуст — нечего делать"; exit 0; }

# Счётчик попыток по текущему составу inbox
SPISOK=$(ls -1 inbox/*.md | md5sum | cut -c1-8)
SCHET_FILE="$TMPDIR_/popytki-$SPISOK"
N=$(( $(cat "$SCHET_FILE" 2>/dev/null || echo 0) + 1 ))
echo "$N" > "$SCHET_FILE"
if [ "$N" -gt 3 ]; then
  say "тот же состав inbox не поддался трижды — останавливаюсь"
  {
    echo "$(date '+%F %H:%M') застряло: задания в inbox не выполнены за 3 прогона"
    ls -1 inbox/*.md
    echo "лог: $LOG"
  } > state/ZASTRYALO.txt
  git add -A && git -c user.name='regiongbi-server' -c user.email='server@regiongbi.local' \
    commit --quiet -m "исполнитель застрял на заданиях в inbox" && git push --quiet origin HEAD:main

  # state/ZASTRYALO.txt никто не увидит вовремя — поэтому письмо (задание 007)
  NOMERA=$(ls -1 inbox/*.md | xargs -n1 basename | cut -d- -f1 | paste -sd, -)
  pismo "regiongbi: $NOMERA — не выполнено" <<PISMO
Задание: $NOMERA (из inbox)
Статус: не выполнено — сработала защита «застряло»
Время: $(date '+%F %H:%M')

Один и тот же состав inbox не поддался за три прогона подряд.
Исполнитель остановился сам и больше не будет пытаться, пока состав
inbox не изменится. Нужен разбор человеком или облачной сессией.

Подробности: state/ZASTRYALO.txt в репозитории.
Лог прогонов: $LOG
PISMO
  exit 1
fi

# Состав outbox до прогона — чтобы после понять, какие отчёты новые
DO_OTCHETY="$TMPDIR_/outbox-do-$$.txt"
ls -1 outbox/*.md 2>/dev/null | sort > "$DO_OTCHETY"
ZADANIYA_BYLI=$(ls -1 inbox/*.md | xargs -n1 basename | cut -d- -f1 | paste -sd, -)

say "старт, попытка $N, задания: $(ls -1 inbox/*.md | tr '\n' ' ')"

timeout 3600 "$CLAUDE" -p "$(cat <<'PROMPT'
Прочитай AGENTS.md целиком — он главнее любого задания. Затем выполни задания
из inbox/ по возрастанию номера, по одному до конца.

По каждому заданию: отчёт в outbox/<номер>-<ГГГГММДД-ЧЧММ>.md в формате из
раздела 6 AGENTS.md, само задание перенеси в done/, обнови state/site.json.
Когда всё сделано — отправь одной командой: bash bin/agent-cycle.sh push "краткое описание".

Уточняющих вопросов задавать некому: чего не хватает — оставь невыполненным и
опиши в отчёте, что именно нужно и от кого. Ничего сверх заданий не делай.
PROMPT
)" --permission-mode bypassPermissions >> "$LOG" 2>&1
KOD=$?
say "завершено, код $KOD"

# Задания ушли в done — значит получилось, счётчик обнуляем
# --- письма (задание 007)
NOVYE=$(ls -1 outbox/*.md 2>/dev/null | sort | comm -13 "$DO_OTCHETY" - || true)
rm -f "$DO_OTCHETY"

if [ -n "$NOVYE" ]; then
  # по письму на каждый новый отчёт; больше пяти за прогон не бывает,
  # но ограничение оставлено, чтобы сбой не превратился в рассылку
  echo "$NOVYE" | head -5 | while read -r f; do
    [ -n "$f" ] && pismo_po_otchetu "$f"
  done
  CHISLO=$(echo "$NOVYE" | grep -c . || true)
  [ "$CHISLO" -gt 5 ] && say "новых отчётов $CHISLO, письма ушли только по первым пяти"
fi

if [ "$KOD" -ne 0 ] && [ -z "$NOVYE" ]; then
  # прогон упал и ничего не написал — письмо всё равно уходит
  pismo "regiongbi: ${ZADANIYA_BYLI:-—} — не выполнено" <<PISMO
Задание: ${ZADANIYA_BYLI:-—} (из inbox)
Статус: не выполнено
Время: $(date '+%F %H:%M')

Прогон исполнителя завершился с кодом $KOD, новых отчётов в outbox/
не появилось. Задания остались в inbox, следующий запуск попробует снова
(попытка $N из 3).

Последние строки лога:
$(tail -6 "$LOG" | bez_sekretov | cut -c1-160)

Лог: $LOG
PISMO
elif [ "$KOD" -ne 0 ]; then
  say "код $KOD, но отчёты появились — письма ушли по отчётам"
fi

compgen -G "inbox/*.md" >/dev/null || rm -f "$SCHET_FILE"
exit $KOD
