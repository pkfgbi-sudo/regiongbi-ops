#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Один цикл работы исполнителя: забрать задания, показать, что нового,
# отдать результат. Сам ничего не выполняет — выполняет агент, а это его
# транспорт: git до и git после.
#
#   bash bin/agent-cycle.sh pull     — забрать новое, напечатать список заданий
#   bash bin/agent-cycle.sh push "текст коммита"
#
# Зачем отдельным скриптом: чтобы агент не изобретал каждый раз свои git-команды
# и не оставил репозиторий в расхождении с origin.
# ---------------------------------------------------------------------------
set -euo pipefail

REPO=${REPO:-/srv/regiongbi/repo}
cd "$REPO"

case "${1:-pull}" in

pull)
  git fetch --quiet origin
  # Локальные правки исполнителя не выбрасываем: если они есть, это отчёты,
  # которые ещё не отправлены. Сначала сохраняем их, потом подтягиваем.
  if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "ВНИМАНИЕ: есть незакоммиченные изменения, отправьте их перед pull:"
    git status --short
    exit 1
  fi
  git merge --ff-only --quiet origin/main || {
    echo "ветка разошлась с origin — разбираться вручную, автоматически не сливаю"
    exit 1
  }

  echo "=== задания в inbox:"
  if compgen -G "inbox/*.md" >/dev/null; then
    for f in inbox/*.md; do
      printf '  %-40s %s\n' "$(basename "$f")" "$(head -1 "$f" | sed 's/^# *//')"
    done
  else
    echo "  пусто"
  fi

  if [ -f state/LOCK ]; then
    echo
    echo "=== стоит LOCK: $(cat state/LOCK)"
    echo "    если он старше двух часов — считать зависшим, снять и написать в отчёт"
  fi
  ;;

push)
  MSG=${2:-"отчёт исполнителя $(date '+%F %H:%M')"}
  git add -A
  if git diff --cached --quiet; then
    echo "нечего отправлять"
    exit 0
  fi
  git -c user.name='regiongbi-server' -c user.email='server@regiongbi.local' \
      commit --quiet -m "$MSG"
  git push --quiet origin HEAD:main
  echo "отправлено: $MSG"
  ;;

*)
  echo "использование: agent-cycle.sh pull | push \"текст коммита\""
  exit 1
  ;;
esac
