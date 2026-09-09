#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Задание 026, пункт 2. Пакет pvk-8-ssylki: две текстовые ссылки на карточку
ПВК-8 в прозе разделов — в таблицы цен ничего не дописывается, чисел не
прибавляется (в отчёте 025 именно на этом работа и остановилась).

  /catalog/kolodtsy-unifitsirovannye/ (#102) — абзац «Как разобраться в марках»
  /catalog/kryshki-kolodtsev-pp/      (#110) — абзац «Как выбрать крышку»

Приём тот же, что в 024–025: строгая замена, каждое «было» обязано встретиться
в содержимом страницы ровно один раз, иначе AssertionError и пакет не
собирается.

Запуск: python3 tools/w026/sobrat-ssylki-pvk-8.py [dump.json]
"""
import json, os, re, sys

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DUMP = sys.argv[1] if len(sys.argv) > 1 else '/srv/regiongbi/tmp/w026/w026-do.json'
URL = '/catalog/kolodtsy-unifitsirovannye/pvk-8/'

ZAMENY = [
    ('kolodtsy-unifitsirovannye',
     'ВД-8 — компактный колодец малого диаметра под простые узлы.',
     'ВД-8 — компактный колодец малого диаметра под простые узлы; сверху его '
     'закрывает круглая плита <a href="%s">ПВК-8</a>.' % URL),
    ('kryshki-kolodtsev-pp',
     'ПВК и ПВГ — варианты под конкретные схемы колодцев.',
     '<a href="%s">ПВК-8</a> и ПВГ — варианты под конкретные схемы колодцев: '
     'ПВК-8 — круглая плита к дождеприёмному колодцу ВД-8.' % URL),
]

pages = {p['slug']: p for p in json.load(open(DUMP, encoding='utf-8'))}
items, log = [], []

for slug, bylo, stalo in ZAMENY:
    p = pages[slug]
    c = p['content']
    n = c.count(bylo)
    assert n == 1, 'на странице %s «%s…» встречается %d раз' % (slug, bylo[:40], n)
    assert c.count(URL) == 0, 'на странице %s ссылка на ПВК-8 уже стоит' % slug
    novoe = c.replace(bylo, stalo)
    assert novoe.count(URL) == 1, 'на %s вышла не одна ссылка' % slug
    ssylok_do, ssylok_posle = c.count('<a href'), novoe.count('<a href')
    assert ssylok_posle == ssylok_do + 1, 'на %s число ссылок выросло не на одну' % slug
    items.append({'url': p['path'], 'content': novoe})
    log.append((slug, p['id'], len(c), len(novoe), ssylok_do, ssylok_posle))

pkg = {
    'package': 'pvk-8-ssylki',
    'primechanie': ('Задание 026, пункт 2: две текстовые ссылки на карточку ПВК-8 в прозе '
                    'разделов колодцев и крышек колодцев. Ни одна таблица цен не трогается, '
                    'чисел на страницах не прибавляется.'),
    'items': items,
}
out = os.path.join(REPO, 'packages', 'pvk-8-ssylki.json')
with open(out, 'w', encoding='utf-8') as f:
    json.dump(pkg, f, ensure_ascii=False, indent=2)
    f.write('\n')

for slug, pid, a, b, sa, sb in log:
    print('%-28s #%-5s длина %5d -> %5d, ссылок %2d -> %2d' % (slug, pid, a, b, sa, sb))
print('позиций в пакете:', len(items), '->', out)
