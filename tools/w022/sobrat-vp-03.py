#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
sobrat-vp-03.py — пакет задания 022: убрать из двух карточек ВП фразы
с объёмами отгрузок.

Работает от выгрузки живого сайта (tools/w019/rz-dump-ceny.php), а не от
пакета vp-01: карточки после задания 020 могли править другие пакеты, и
переписывать содержимое целиком нельзя — правится ровно одна фраза.

Замена точная и проверяемая: каждое «было» обязано встретиться в содержимом
ровно один раз. Не один — позиция не собирается, печатается предупреждение.
Ничего, кроме post_content, пакет не несёт: ни меты, ни SEO-полей, ни
заголовков — по заданию «больше в этих карточках ничего не трогать».

Запуск:  python3 tools/w022/sobrat-vp-03.py <dump.json> <packages/vp-03.json>
"""
import json
import sys

ZAMENY = [
    {
        "slug": "vp-22-6",
        "bylo": "ВП 22-6 — самая ходовая марка узкого ряда: за месяц уходит больше трёхсот штук.",
        "stalo": "ВП 22-6 — самая ходовая марка узкого ряда.",
    },
    {
        "slug": "vp-28-12",
        "bylo": "За месяц отгружается больше сотни штук — это один из самых востребованных типоразмеров в денежном выражении.",
        "stalo": "Один из самых востребованных типоразмеров широкого ряда.",
    },
]


def main():
    if len(sys.argv) < 3:
        sys.exit("использование: sobrat-vp-03.py <dump.json> <out.json>")
    dump = json.load(open(sys.argv[1], encoding="utf-8"))
    po_slug = {p["slug"]: p for p in dump}

    items, preduprezhdeniy = [], 0
    for z in ZAMENY:
        p = po_slug.get(z["slug"])
        if p is None:
            print("ВНИМАНИЕ: страницы %s нет в выгрузке" % z["slug"])
            preduprezhdeniy += 1
            continue
        n = p["content"].count(z["bylo"])
        if n != 1:
            print("ВНИМАНИЕ: %s — фраза встречается %d раз, ожидали 1; не собираю"
                  % (z["slug"], n))
            preduprezhdeniy += 1
            continue
        novoe = p["content"].replace(z["bylo"], z["stalo"])
        items.append({
            "url": p["path"],
            "slug": p["slug"],
            "content": novoe,
        })
        print("%-9s #%-5d %d -> %d байт, замен 1"
              % (p["slug"], p["id"], len(p["content"]), len(novoe)))

    paket = {
        "package": "vp-03",
        "opisanie": "задание 022 — убрать объёмы отгрузок из карточек ВП 22-6 и ВП 28-12",
        "items": items,
    }
    with open(sys.argv[2], "w", encoding="utf-8") as f:
        json.dump(paket, f, ensure_ascii=False, indent=1)
    print("-" * 60)
    print("позиций: %d, предупреждений: %d, файл %s"
          % (len(items), preduprezhdeniy, sys.argv[2]))
    sys.exit(1 if preduprezhdeniy else 0)


if __name__ == "__main__":
    main()
