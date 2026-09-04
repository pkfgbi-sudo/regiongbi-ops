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
# ---------------------------------------------------------------------------
set -uo pipefail

REPO=${REPO:-/srv/regiongbi/repo}
LOGDIR=/srv/regiongbi/logs
TMPDIR_=/srv/regiongbi/tmp
mkdir -p "$LOGDIR" "$TMPDIR_"
LOG="$LOGDIR/agent-$(date +%F).log"
say() { echo "$(date '+%F %T') $*" >> "$LOG"; }

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
  exit 1
fi

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
compgen -G "inbox/*.md" >/dev/null || rm -f "$SCHET_FILE"
exit $KOD
