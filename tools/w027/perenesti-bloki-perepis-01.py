#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Задание 027, раздел «Опасное место». В содержимом шести переписываемых карточек
лежат два блока, которых в пакете нет:

    <!--rz:articles-->  «Полезное по теме»  — три ссылки на статьи блога
    <!--rz:siblings-->  «Рядом в ряду»      — таблица соседних марок с ценами

Ни один mu-плагин их не выводит (проверено grep по packages/mu-plugins: маркеров
rz:articles и rz:siblings там нет; mu-rz-related.php работает по своему маркеру
<!-- rz-related --> и только на страницах разделов). Значит блоки хранятся в
контенте, и заливка пакета без них их снесёт.

Скрипт дословно переносит хвост живой страницы, начиная с первого маркера
<!--rz:, в конец соответствующей позиции пакета. Источник — выгрузка базы, а не
старый пакет: цены в блоке «Рядом в ряду» правились заданием 021, и верна та
версия, что сейчас на сайте.

Запуск: python3 tools/w027/perenesti-bloki-perepis-01.py [dump.json]
"""
import json, os, re, sys

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DUMP = sys.argv[1] if len(sys.argv) > 1 else '/srv/regiongbi/tmp/w026/w026-posle.json'
PKG  = os.path.join(REPO, 'packages', 'perepis-01.json')

live = {p['slug']: p for p in json.load(open(DUMP, encoding='utf-8'))}
pkg  = json.load(open(PKG, encoding='utf-8'))

log = []
for it in pkg['items']:
    slug = it['slug']
    c = live[slug]['content']

    i = c.find('<!--rz:')
    assert i > 0, '%s: блоков rz: в живом контенте нет' % slug
    hvost = c[i:]

    marks = re.findall(r'<!--(/?)rz:(\w+)-->', hvost)
    assert [m[1] for m in marks] == ['articles', 'articles', 'siblings', 'siblings'], \
        '%s: неожиданный набор маркеров %s' % (slug, marks)
    assert hvost.rstrip().endswith('<!--/rz:siblings-->'), '%s: после блоков что-то есть' % slug
    for m in ('rz:articles', 'rz:siblings'):
        assert it['content'].count(m) == 0, '%s: блок %s уже в пакете' % (slug, m)

    it['content'] = it['content'].rstrip() + '\n\n' + hvost
    log.append((slug, len(c), len(hvost), len(it['content'])))

hvost_fayla = '\n' if open(PKG, encoding='utf-8').read().endswith('\n') else ''
with open(PKG, 'w', encoding='utf-8') as f:
    json.dump(pkg, f, ensure_ascii=False, indent=2)
    f.write(hvost_fayla)

for slug, a, b, c in log:
    print('%-10s живой контент %5d, перенесено %4d знаков, в пакете стало %5d' % (slug, a, b, c))
print('позиций дополнено:', len(log))
